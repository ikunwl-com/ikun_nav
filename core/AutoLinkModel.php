<?php
/**
 * 友链自动收录模型
 * 当用户从挂了本站友链的外站点击进入时，自动检测 Referer，
 * 抓取对方首页验证回链，检查 TDK 违禁词，通过后自动收录。
 */
class AutoLinkModel
{
    /** 搜索引擎等非目标来源域名（不收录） */
    private const SEARCH_ENGINE_DOMAINS = [
        'baidu.com', 'google.com', 'bing.com', 'sogou.com', 'so.com',
        'yahoo.com', 'yandex.com', 'duckduckgo.com', 'baiducontent.com',
        'm.baidu.com', 'wap.baidu.com', 'image.baidu.com',
        'google.com.hk', 'm.google.com',
    ];

    /** 频率限制：同一域名 6 小时内最多处理 3 次 */
    private const RATE_LIMIT_MAX = 3;
    private const RATE_LIMIT_WINDOW = 21600;

    /**
     * 主入口：检测 Referer 并尝试自动收录
     * @param string $refererOverride 可选的 URL 参数传入的原始来路（避免 API pixel 请求自身 Referer 为空）
     * @return array{success: bool, action: string, message: string}
     */
    public function process(string $refererOverride = ''): array
    {
        // 0. 检查功能是否开启
        if (setting('autolink_enable', '0') !== '1') {
            return ['success' => false, 'action' => 'disabled', 'message' => '友链自动收录未开启'];
        }

        // 1. 获取 Referer（优先使用 URL 参数传入的，否则回退到 $_SERVER）
        $referer = $refererOverride;
        if (empty($referer)) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
        }
        if (empty($referer)) {
            return ['success' => false, 'action' => 'no_referer', 'message' => '无来路'];
        }

        // 2. 提取域名
        $domain = Security::extractDomain($referer);
        if (empty($domain)) {
            return ['success' => false, 'action' => 'invalid_referer', 'message' => '来路域名无效'];
        }

        // 3. 排除本站自身
        $selfDomain = $this->getSelfDomain();
        if (strcasecmp($domain, $selfDomain) === 0) {
            return ['success' => false, 'action' => 'self', 'message' => '本站来路'];
        }

        // 4. 排除搜索引擎
        if ($this->isSearchEngine($domain)) {
            return ['success' => false, 'action' => 'search_engine', 'message' => '搜索引擎来路'];
        }

        // 5. 排除内网地址
        $refererHost = parse_url($referer, PHP_URL_HOST) ?? '';
        $refererHost = preg_replace('/:\d+$/', '', $refererHost);
        if (Security::isInternalHost($refererHost)) {
            return ['success' => false, 'action' => 'internal', 'message' => '内网来路'];
        }

        // 6. 全局 IP 屏蔽
        if (setting('block_all_ip', '0') === '1' && filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return ['success' => false, 'action' => 'ip_blocked', 'message' => '纯IP已被全局屏蔽'];
        }

        // 7. 黑名单检查
        $clientIp = Security::getClientIP();
        $blacklist = new BlacklistModel();
        if ($blacklist->isBlocked($clientIp, $domain)) {
            return ['success' => false, 'action' => 'blacklisted', 'message' => '命中黑名单'];
        }

        // 8. 频率限制
        if (!Security::rateLimit("autolink:{$domain}", self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW)) {
            return ['success' => false, 'action' => 'rate_limited', 'message' => '频率限制'];
        }

        // 9. 重复检测：已收录或待审核状态不重复收录
        $existingSite = $this->findExistingSite($domain, $refererHost);
        if ($existingSite) {
            return ['success' => false, 'action' => 'exists', 'message' => "已收录(ID={$existingSite['id']})"];
        }

        // 10. 抓取对方首页 HTML
        $html = $this->fetchPageHtml($referer);
        if (empty($html)) {
            return ['success' => false, 'action' => 'fetch_failed', 'message' => '抓取对方页面失败'];
        }

        // 11. 回链验证：检查对方页面是否包含本站链接
        if (!$this->verifyBackLink($html, $selfDomain)) {
            return ['success' => false, 'action' => 'no_backlink', 'message' => '未检测到回链'];
        }

        // 12. TDK 提取 + 违禁词检查
        $tdk = $this->parseTDK($html);
        $bannedWords = $this->getBannedWords();
        if (!empty($bannedWords) && $this->containsBannedWords($tdk, $bannedWords)) {
            return ['success' => false, 'action' => 'banned_word', 'message' => 'TDK含违禁词'];
        }

        // 13. 自动收录
        $result = $this->addSite($domain, $referer, $tdk);
        if ($result['success']) {
            Logger::log('autolink', "自动收录成功：{$domain}（ID={$result['id']}）IP={$clientIp}");
            return ['success' => true, 'action' => 'added', 'message' => "已收录(ID={$result['id']})"];
        }

        return ['success' => false, 'action' => 'error', 'message' => $result['error'] ?? '收录失败'];
    }

    /**
     * 获取本站域名
     */
    private function getSelfDomain(): string
    {
        if (defined('SITE_URL')) {
            $host = parse_url(SITE_URL, PHP_URL_HOST);
            if ($host) {
                return strtolower(preg_replace('/^www\./i', '', $host));
            }
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return strtolower(preg_replace('/^www\./i', '', $host));
    }

    /**
     * 判断是否搜索引擎来路
     */
    private function isSearchEngine(string $domain): bool
    {
        foreach (self::SEARCH_ENGINE_DOMAINS as $se) {
            if ($domain === $se || $this->endsWith($domain, '.' . $se)) {
                return true;
            }
        }
        // 通用搜索特征
        if (preg_match('/(search|google|baidu|bing|sogou|360sou|so\.com)/i', $domain)) {
            return true;
        }
        return false;
    }

    /**
     * 兼容 PHP < 8.0 的 str_ends_with
     */
    private function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 查找已存在的站点（published 或 pending 状态）
     * 支持 www/非 www 互认
     */
    private function findExistingSite(string $domain, string $rawHost): ?array
    {
        $tbl = Database::table('sites');

        $hostVariants = [$rawHost];
        if (preg_match('/^www\./i', $rawHost)) {
            $hostVariants[] = preg_replace('/^www\./i', '', $rawHost);
        } else {
            $hostVariants[] = 'www.' . $rawHost;
        }

        $likeClauses = [];
        $likeParams = [];
        foreach ($hostVariants as $h) {
            $likeClauses[] = "url LIKE ?";
            $likeParams[] = '%//' . $h . '%';
        }

        return Database::queryOne(
            "SELECT id, status FROM {$tbl} WHERE (" . implode(' OR ', $likeClauses) . ") AND status IN ('published', 'pending') LIMIT 1",
            $likeParams
        );
    }

    /**
     * 抓取对方页面 HTML
     */
    private function fetchPageHtml(string $url): string
    {
        // SSRF 防护
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (empty($host) || Security::isInternalHost($host)) {
            return '';
        }

        $urlsToTry = [];
        if (preg_match('/^https?:\/\//i', $url)) {
            $urlsToTry[] = $url;
        } else {
            $urlsToTry[] = 'https://' . $url;
            $urlsToTry[] = 'http://' . $url;
        }

        foreach ($urlsToTry as $tryUrl) {
            $html = $this->curlFetch($tryUrl, 6);
            if (!empty($html)) {
                // 编码转换
                $charset = '';
                if (preg_match('/<meta[^>]+charset=["\']?([^"\'>\s]+)/i', $html, $m)) {
                    $charset = strtoupper(trim($m[1]));
                } elseif (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([^"\'>\s]+)/i', $html, $m)) {
                    $charset = strtoupper(trim($m[1]));
                }
                if ($charset && $charset !== 'UTF-8') {
                    $html = @mb_convert_encoding($html, 'UTF-8', $charset);
                }
                return $html;
            }
        }

        return '';
    }

    /**
     * cURL 抓取页面
     */
    private function curlFetch(string $url, int $timeout = 6): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
                CURLOPT_ENCODING       => '',
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
            return $html ?: '';
        }

        // file_get_contents fallback
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header'  => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\n",
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        return @file_get_contents($url, false, $ctx) ?: '';
    }

    /**
     * 回链验证：检查对方页面 HTML 是否包含指向本站的链接
     * 匹配方式：
     * 1. href 中包含本站域名
     * 2. 文本中包含本站完整 URL
     */
    private function verifyBackLink(string $html, string $selfDomain): bool
    {
        // 获取本站完整 URL
        $baseUrl = $this->getBaseUrl();

        // 方式1：href 属性中包含本站域名或 URL
        // 匹配 href="https://mysite.com..." 或 href="http://mysite.com..." 或 href="//mysite.com..."
        $domainEsc = preg_quote($selfDomain, '/');
        $pattern = '/href\s*=\s*["\']?\s*(?:https?:)?\/\/[^"\'>\s]*' . $domainEsc . '/i';
        if (preg_match($pattern, $html)) {
            return true;
        }

        // 方式2：页面文本中包含本站完整 URL
        $urlEsc = preg_quote($baseUrl, '/');
        if (preg_match('/' . $urlEsc . '/i', $html)) {
            return true;
        }

        // 方式3：href 中包含本站完整 URL（可能带路径）
        $baseUrlEsc = preg_quote(rtrim($baseUrl, '/'), '/');
        if (preg_match('/href\s*=\s*["\']?\s*' . $baseUrlEsc . '/i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * 从 HTML 中解析 TDK
     */
    private function parseTDK(string $html): array
    {
        $result = ['title' => '', 'description' => '', 'keywords' => ''];

        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $result['title'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<meta[^>]+\s*name=["\']description["\'][^>]*content=["\']([^"\']*)/is', $html, $m)
            || preg_match('/<meta[^>]+\s*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $html, $m)
            || preg_match('/<meta[^>]+\s*property=["\']og:description["\'][^>]*content=["\']([^"\']*)/is', $html, $m)) {
            $result['description'] = trim($m[1]);
        }
        if (preg_match('/<meta[^>]+\s*name=["\']keywords["\'][^>]*content=["\']([^"\']*)/is', $html, $m)
            || preg_match('/<meta[^>]+\s*content=["\']([^"\']*)["\'][^>]*name=["\']keywords["\']/is', $html, $m)) {
            $result['keywords'] = trim($m[1]);
        }

        return $result;
    }

    /**
     * 获取违禁词列表
     */
    private function getBannedWords(): array
    {
        $raw = setting('autolink_banned_words', '');
        if (empty($raw)) {
            return [];
        }
        // 支持换行、逗号分隔
        $words = preg_split('/[\n\r,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter(array_map('trim', $words));
    }

    /**
     * 检查 TDK 是否包含违禁词
     */
    private function containsBannedWords(array $tdk, array $bannedWords): bool
    {
        $text = mb_strtolower($tdk['title'] . ' ' . $tdk['description'] . ' ' . $tdk['keywords']);
        foreach ($bannedWords as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            if (mb_strpos($text, mb_strtolower($word)) !== false) {
                Logger::log('autolink', "违禁词命中：{$word}");
                return true;
            }
        }
        return false;
    }

    /**
     * 获取本站完整 URL
     */
    private function getBaseUrl(): string
    {
        if (defined('SITE_URL')) {
            return rtrim(SITE_URL, '/');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * 将站点写入数据库
     */
    private function addSite(string $domain, string $referer, array $tdk): array
    {
        try {
            $settings = new SettingsModel();
            $needReview = $settings->get('autolink_need_review', '1') === '1';
            $defaultCatId = (int)$settings->get('autolink_default_category', '0');

            // 默认分类未配置时取第一个可见分类
            if ($defaultCatId <= 0) {
                $catModel = new CategoryModel();
                $cats = $catModel->getSidebarCategories();
                $defaultCatId = !empty($cats) ? (int)$cats[0]['id'] : 1;
            }

            // 站点名称：优先用 TDK 标题，取分隔符前的主标题
            $name = $domain;
            if (!empty($tdk['title'])) {
                $titleParts = preg_split('/[\s\-_|｜,，、]+/u', $tdk['title'], 2);
                $mainTitle = trim($titleParts[0]);
                if (!empty($mainTitle) && mb_strlen($mainTitle) <= 100) {
                    $name = $mainTitle;
                }
            }

            // 标签
            $tags = [];
            if (!empty($tdk['keywords'])) {
                $tags = array_slice(
                    array_filter(array_map('trim', explode(',', $tdk['keywords']))),
                    0, 10
                );
            }

            $url = 'https://' . $domain;
            $status = $needReview ? 'pending' : 'published';

            $siteModel = new SiteModel();
            $siteId = $siteModel->create([
                'name'        => Security::cleanString($name, 100),
                'url'         => $url,
                'category_id' => $defaultCatId,
                'description' => Security::cleanString($tdk['description'] ?? '', 200),
                'tags'        => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'status'      => $status,
                'submit_ip'   => Security::getClientIP(),
            ]);

            if ($siteId > 0) {
                // 异步获取权重（非阻塞，失败不影响收录）
                $this->fetchWeightAsync($siteId, $domain, $siteModel);
                return ['success' => true, 'id' => $siteId];
            }

            return ['success' => false, 'error' => '创建失败'];
        } catch (Exception $e) {
            Logger::log('autolink', "收录异常：{$domain} - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 同步获取权重并更新站点（复用 fetchMetaData）
     */
    private function fetchWeightAsync(int $siteId, string $domain, SiteModel $siteModel): void
    {
        try {
            // 复用 api/index.php 中的 fetchMetaData 函数（如果可用）
            if (function_exists('fetchMetaData')) {
                $meta = fetchMetaData('https://' . $domain);
                $updateData = [];
                foreach (['br_pc', 'br_mobile', 'br_360', 'br_shenma'] as $bf) {
                    if (isset($meta[$bf])) {
                        $updateData[$bf] = max(0, min(10, (int)$meta[$bf]));
                    }
                }
                if (!empty($updateData)) {
                    $siteModel->update($siteId, $updateData);
                }
            }
        } catch (Exception $e) {
            // 权重获取失败不影响收录
            Logger::log('autolink', "权重获取失败：{$domain} - " . $e->getMessage());
        }
    }
}
