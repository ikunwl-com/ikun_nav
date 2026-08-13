<?php
/**
 * 网站地图（Sitemap）生成器
 * 符合 sitemaps.org 0.9 协议规范
 * 支持：XML Sitemap 生成、文件缓存、分片（大站场景）
 */
class SitemapModel
{
    /** 缓存文件存放目录（相对于项目根目录） */
    private const CACHE_DIR = 'data/sitemap';

    /** 单个 sitemap 文件最大 URL 数量（协议上限 50000） */
    private const MAX_URLS_PER_FILE = 50000;

    /** 缓存有效期（秒），默认 6 小时 */
    private const CACHE_TTL = 21600;

    /** 缓存文件名（主 sitemap） */
    private const CACHE_FILE = 'sitemap.xml';

    /** sitemap index 文件名 */
    private const INDEX_FILE = 'sitemap-index.xml';

    /**
     * 获取缓存目录的绝对路径
     */
    private static function getCacheDir(): string
    {
        return dirname(__DIR__) . '/' . self::CACHE_DIR;
    }

    /**
     * 确保缓存目录存在
     */
    private static function ensureCacheDir(): void
    {
        $dir = self::getCacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * 获取当前网站根 URL（带域名）
     */
    private static function getBaseUrl(): string
    {
        $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if (empty($base)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $protocol . '://' . $host;
        }
        return $base;
    }

    /**
     * 将相对 URL 转为绝对 URL
     */
    private static function absoluteUrl(string $relativeUrl): string
    {
        $base = self::getBaseUrl();
        // 如果已经是绝对 URL，直接返回
        if (preg_match('/^https?:\/\//i', $relativeUrl)) {
            return $relativeUrl;
        }
        return $base . '/' . ltrim($relativeUrl, '/');
    }

    /**
     * XML 转义
     */
    private static function xmlEscape(string $str): string
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * 收集所有需要加入 sitemap 的 URL
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: float}>
     */
    public function collectUrls(): array
    {
        $urls = [];

        // 1. 首页
        $urls[] = [
            'loc'        => self::absoluteUrl('/'),
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => 1.0,
        ];

        // 2. 提交收录页（优先读取 submit 插件配置）
        $settingsModel = new SettingsModel();
        $pluginEnable = Plugin::config('submit', 'enable_submit', null);
        $enableSubmit = ($pluginEnable !== null)
            ? ($pluginEnable === '1')
            : ($settingsModel->get('enable_submit', '1') === '1');
        if ($enableSubmit) {
            $urls[] = [
                'loc'        => self::absoluteUrl(Rewrite::url('submit')),
                'lastmod'    => date('Y-m-d'),
                'changefreq' => 'monthly',
                'priority'   => 0.6,
            ];
        }

        // 3. 虫洞联盟页
        $wormholeEnable = $settingsModel->get('wormhole_enable', '0');
        if ($wormholeEnable === '1') {
            $urls[] = [
                'loc'        => self::absoluteUrl(Rewrite::url('wormhole')),
                'lastmod'    => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority'   => 0.6,
            ];
        }

        // 4. 分类页 + 分类分页
        $catModel = new CategoryModel();
        $siteModel = new SiteModel();
        $categories = $catModel->getSidebarCategories();

        foreach ($categories as $cat) {
            $catUrl = Rewrite::url('category', ['slug' => $cat['slug']]);
            $siteCount = (int)($cat['site_count'] ?? 0);
            $perPage = (int)$settingsModel->get('default_per_page', 12);
            $perPage = max(6, min(60, $perPage));

            // 分类第一页
            $urls[] = [
                'loc'        => self::absoluteUrl($catUrl),
                'lastmod'    => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority'   => 0.8,
            ];

            // 分类分页（第 2 页起）
            $totalPages = (int)ceil($siteCount / max(1, $perPage));
            for ($p = 2; $p <= $totalPages; $p++) {
                $pageUrl = Rewrite::url('category', ['slug' => $cat['slug'], 'page' => $p]);
                $urls[] = [
                    'loc'        => self::absoluteUrl($pageUrl),
                    'lastmod'    => date('Y-m-d'),
                    'changefreq' => 'weekly',
                    'priority'   => 0.6,
                ];
            }
        }

        // 5. 站点详情页（所有已发布站点）
        $tblSites = Database::table('sites');
        $sql = "SELECT id, name, updated_at, created_at FROM {$tblSites}
                WHERE status = 'published'
                ORDER BY created_at DESC";
        $sites = Database::query($sql);

        foreach ($sites as $site) {
            $siteUrl = Rewrite::url('site', ['id' => (int)$site['id']]);
            $lastmod = $site['updated_at'] ?: $site['created_at'];
            $urls[] = [
                'loc'        => self::absoluteUrl($siteUrl),
                'lastmod'    => date('Y-m-d', strtotime($lastmod)),
                'changefreq' => 'weekly',
                'priority'   => 0.7,
            ];
        }

        // 6. 文章页（文章发布插件启用时）
        if (class_exists('Plugin') && Plugin::isEnabled('article') && class_exists('ArticleModel')) {
            $articleModel = new ArticleModel();
            $articles = $articleModel->getList(1, 10000);

            // 文章列表页
            $urls[] = [
                'loc'        => self::absoluteUrl(Rewrite::url('article_list')),
                'lastmod'    => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority'   => 0.6,
            ];

            // 文章详情页
            foreach ($articles as $art) {
                $artUrl = Rewrite::url('article', ['id' => (int)$art['id']]);
                $lastmod = $art['updated_at'] ?: $art['created_at'];
                $urls[] = [
                    'loc'        => self::absoluteUrl($artUrl),
                    'lastmod'    => date('Y-m-d', strtotime($lastmod)),
                    'changefreq' => 'weekly',
                    'priority'   => 0.6,
                ];
            }
        }

        return $urls;
    }

    /**
     * 生成 sitemap XML 内容
     * @param array $urls URL 列表（已收集的）
     * @param int $offset 起始偏移
     * @param int $limit  最大数量
     * @return string XML 内容
     */
    public function buildXml(array $urls, int $offset = 0, int $limit = self::MAX_URLS_PER_FILE): string
    {
        $slice = array_slice($urls, $offset, $limit);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($slice as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . self::xmlEscape($url['loc']) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . self::xmlEscape($url['lastmod']) . "</lastmod>\n";
            }
            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . self::xmlEscape($url['changefreq']) . "</changefreq>\n";
            }
            if (isset($url['priority'])) {
                $xml .= '    <priority>' . number_format((float)$url['priority'], 1) . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";
        return $xml;
    }

    /**
     * 生成 sitemap index XML（分片模式）
     * @param int $totalUrls URL 总数
     * @param string $baseUrl 网站根 URL
     * @return string XML index 内容
     */
    public function buildIndexXml(int $totalUrls, string $baseUrl): string
    {
        $shardCount = (int)ceil($totalUrls / self::MAX_URLS_PER_FILE);
        $now = date('Y-m-d');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        for ($i = 1; $i <= $shardCount; $i++) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . self::xmlEscape($baseUrl . '/sitemap-' . $i . '.xml') . "</loc>\n";
            $xml .= '    <lastmod>' . $now . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }

        $xml .= '</sitemapindex>' . "\n";
        return $xml;
    }

    /**
     * 生成并写入 sitemap 到缓存文件
     * @return array{success: bool, url_count: int, file: string, is_index: bool, error?: string}
     */
    public function generate(): array
    {
        try {
            $urls = $this->collectUrls();
            $totalUrls = count($urls);

            self::ensureCacheDir();
            $cacheDir = self::getCacheDir();

            // 超过单文件上限时启用分片模式
            if ($totalUrls > self::MAX_URLS_PER_FILE) {
                // 生成分片文件
                $shardCount = (int)ceil($totalUrls / self::MAX_URLS_PER_FILE);
                for ($i = 1; $i <= $shardCount; $i++) {
                    $offset = ($i - 1) * self::MAX_URLS_PER_FILE;
                    $xml = $this->buildXml($urls, $offset, self::MAX_URLS_PER_FILE);
                    $shardFile = $cacheDir . '/sitemap-' . $i . '.xml';
                    file_put_contents($shardFile, $xml, LOCK_EX);
                }

                // 生成 index 文件作为主入口
                $baseUrl = self::getBaseUrl();
                $indexXml = $this->buildIndexXml($totalUrls, $baseUrl);
                $indexFile = $cacheDir . '/' . self::CACHE_FILE;
                file_put_contents($indexFile, $indexXml, LOCK_EX);

                return [
                    'success'  => true,
                    'url_count' => $totalUrls,
                    'file'     => self::CACHE_FILE,
                    'is_index' => true,
                ];
            }

            // 普通模式：单文件
            $xml = $this->buildXml($urls);
            $file = $cacheDir . '/' . self::CACHE_FILE;
            file_put_contents($file, $xml, LOCK_EX);

            // 清理可能存在的旧分片文件
            $this->cleanShards();

            return [
                'success'   => true,
                'url_count' => $totalUrls,
                'file'      => self::CACHE_FILE,
                'is_index'  => false,
            ];
        } catch (Exception $e) {
            return [
                'success'   => false,
                'url_count' => 0,
                'file'      => '',
                'is_index'  => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * 清理分片文件
     */
    private function cleanShards(): void
    {
        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            return;
        }
        $files = glob($cacheDir . '/sitemap-*.xml');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        $indexFile = $cacheDir . '/' . self::INDEX_FILE;
        if (file_exists($indexFile)) {
            @unlink($indexFile);
        }
    }

    /**
     * 获取 sitemap 缓存文件路径
     * @return string|null 文件路径或 null（不存在）
     */
    public function getCacheFile(): ?string
    {
        $file = self::getCacheDir() . '/' . self::CACHE_FILE;
        return file_exists($file) ? $file : null;
    }

    /**
     * 获取 sitemap 内容（带缓存检查）
     * 如果缓存不存在或过期，自动重新生成
     * @return array{xml: string, content_type: string, generated: bool}
     */
    public function getContent(): array
    {
        $cacheFile = $this->getCacheFile();

        // 缓存有效，直接读取
        if ($cacheFile && (time() - filemtime($cacheFile) < self::CACHE_TTL)) {
            $content = file_get_contents($cacheFile);
            if ($content !== false) {
                return [
                    'xml'           => $content,
                    'content_type'  => 'application/xml; charset=utf-8',
                    'generated'     => false,
                ];
            }
        }

        // 缓存不存在或过期，重新生成
        $result = $this->generate();
        if ($result['success']) {
            $cacheFile = $this->getCacheFile();
            if ($cacheFile) {
                $content = file_get_contents($cacheFile);
                if ($content !== false) {
                    return [
                        'xml'           => $content,
                        'content_type'  => 'application/xml; charset=utf-8',
                        'generated'     => true,
                    ];
                }
            }
        }

        // 生成失败，尝试返回过期的缓存
        if ($cacheFile) {
            $content = file_get_contents($cacheFile);
            if ($content !== false) {
                return [
                    'xml'           => $content,
                    'content_type'  => 'application/xml; charset=utf-8',
                    'generated'     => false,
                ];
            }
        }

        return [
            'xml'           => '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n",
            'content_type'  => 'application/xml; charset=utf-8',
            'generated'     => false,
        ];
    }

    /**
     * 获取分片 sitemap 内容
     * @param int $shard 分片序号（从 1 开始）
     * @return array{xml: string, found: bool}
     */
    public function getShardContent(int $shard): array
    {
        $file = self::getCacheDir() . '/sitemap-' . $shard . '.xml';
        if (file_exists($file)) {
            return [
                'xml'   => file_get_contents($file) ?: '',
                'found' => true,
            ];
        }
        return ['xml' => '', 'found' => false];
    }

    /**
     * 获取 sitemap 状态信息（供后台展示）
     * @return array{exists: bool, url_count: int, last_generated: string, file_size: int, is_index: bool, cache_ttl: int}
     */
    public function getStatus(): array
    {
        $cacheFile = $this->getCacheFile();

        if (!$cacheFile) {
            return [
                'exists'         => false,
                'url_count'      => 0,
                'last_generated' => '',
                'file_size'      => 0,
                'is_index'       => false,
                'cache_ttl'      => self::CACHE_TTL,
            ];
        }

        $content = file_get_contents($cacheFile);
        $isIndex = $content && strpos($content, '<sitemapindex') !== false;

        // 统计 URL 数量
        $urlCount = 0;
        if ($isIndex) {
            $urlCount = (int)preg_match_all('/<sitemap>/', $content ?: '');
            $urlCount *= self::MAX_URLS_PER_FILE; // 近似值
        } else {
            $urlCount = (int)preg_match_all('/<url>/', $content ?: '');
        }

        // 加上分片文件的 URL
        if ($isIndex) {
            $shards = glob(self::getCacheDir() . '/sitemap-*.xml');
            if ($shards) {
                $urlCount = 0;
                foreach ($shards as $sf) {
                    $sfContent = file_get_contents($sf);
                    $urlCount += (int)preg_match_all('/<url>/', $sfContent ?: '');
                }
            }
        }

        return [
            'exists'         => true,
            'url_count'      => $urlCount,
            'last_generated' => date('Y-m-d H:i:s', filemtime($cacheFile)),
            'file_size'      => filesize($cacheFile),
            'is_index'       => $isIndex,
            'cache_ttl'      => self::CACHE_TTL,
        ];
    }

    /**
     * 获取 sitemap 访问 URL（供后台展示和 robots.txt 用）
     */
    public function getSitemapUrl(): string
    {
        return self::getBaseUrl() . '/sitemap.xml';
    }

    /**
     * 获取 robots.txt 内容（包含 sitemap 声明）
     */
    public function generateRobotsTxt(): string
    {
        $baseUrl = self::getBaseUrl();
        $lines = [];
        $lines[] = 'User-agent: *';
        $lines[] = 'Allow: /';
        $lines[] = 'Disallow: /admin/';
        $lines[] = 'Disallow: /install/';
        $lines[] = 'Disallow: /api/';
        $lines[] = 'Disallow: /core/';
        $lines[] = 'Disallow: /config/';
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $baseUrl . '/sitemap.xml';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
