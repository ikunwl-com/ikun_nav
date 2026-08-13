<?php
/**
 * 懒人导航 - 前台通用辅助函数
 */

/**
 * 获取数据库连接
 */
function getDatabase() {
    return Database::getInstance();
}

/**
 * 检查是否已安装（配置存在且数据库可连接）
 * 优化：复用 Database 单例连接，避免每次新建 PDO 连接
 */
function isInstalled() {
    // 检查数据库配置是否存在
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        return false;
    }

    // 复用 Database 单例连接（连接失败会抛异常，捕获后返回 false）
    try {
        Database::getInstance();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * 获取配置值（从数据库，共享 SettingsModel 缓存）
 * 确保 setMany 写入后立即可见，无需手动清理缓存
 */
function getConfig($key, $default = null) {
    // 优先使用 SettingsModel 的单例缓存（包含完整的 defaults 合并逻辑）
    $settingsModel = new SettingsModel();
    return $settingsModel->get($key, $default);
}

/**
 * 判断调试模式
 */
function isDebug() {
    if (defined('APP_DEBUG')) {
        return APP_DEBUG === true;
    }
    return getConfig('debug_mode', 'false') === 'true';
}

/**
 * URL重定向
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 获取配置值（别名，简化前台调用）
 */
function setting(string $key, $default = null) {
    return getConfig($key, $default);
}

/**
 * 获取站点URL（用于外部链接）
 */
function getSiteUrl(string $path = ''): string {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : getCurrentSiteUrl();
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

/**
 * 获取当前站点URL
 */
function getCurrentSiteUrl(): string {
    if (defined('SITE_URL')) {
        return rtrim(SITE_URL, '/');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * 从URL提取域名
 */
function getDisplayDomain(string $url): string {
    $parsed = parse_url($url);
    return $parsed['host'] ?? $url;
}

/**
 * 解析URL中的域名
 */
function parseDomain(string $url): string {
    return getDisplayDomain($url);
}

/**
 * 获取分类URL（根据伪静态模式）
 */
function getCategoryUrl(string $slug): string {
    $baseUrl = rtrim(getCurrentSiteUrl(), '/');
    $rewriteConfig = Rewrite::getConfig();
    $rewriteMode = $rewriteConfig['mode'] ?? 'dynamic';
    
    if ($rewriteMode === 'rewrite') {
        return $baseUrl . '/' . $slug . '.html';
    } else {
        return $baseUrl . '/?cat=' . urlencode($slug);
    }
}

/**
 * 标准化URL
 */
function normalizeSiteUrl(string $url): string {
    if (empty($url)) return '#';
    if (!preg_match('/^https?:\/\//i', $url)) {
        return 'https://' . $url;
    }
    return $url;
}

/**
 * 获取权重徽章CSS类
 */
function getWeightBadgeClass(int $br): string {
    if ($br >= 7) return 'weight-9';
    if ($br >= 6) return 'weight-7';
    if ($br >= 5) return 'weight-5';
    if ($br >= 4) return 'weight-4';
    if ($br >= 3) return 'weight-3';
    if ($br >= 2) return 'weight-2';
    if ($br >= 1) return 'weight-1';
    return 'weight-0';
}

/**
 * 获取权重颜色
 */
function getBrColor(int $br): string {
    if ($br >= 7) return '#dc2626';
    if ($br >= 5) return '#2563eb';
    if ($br >= 3) return '#16a34a';
    if ($br >= 1) return '#d97706';
    return '#9ca3af';
}

/**
 * 渲染站点图标
 */
function renderSiteIcon(string $name, int $size = 36): string {
    $firstChar = mb_substr($name, 0, 1);
    $color = getSiteColor($name);
    return '<div class="site-icon" style="width:' . $size . 'px;height:' . $size . 'px;background:' . $color . '15;color:' . $color . '">' . htmlspecialchars($firstChar) . '</div>';
}

/**
 * 获取站点颜色
 */
function getSiteColor(string $name): string {
    $colors = ['#667eea', '#f093fb', '#f5576c', '#4facfe', '#43e97b', '#fa709a', '#fee140', '#30cfd0', '#a8edea', '#ff9a9e'];
    $hash = 0;
    for ($i = 0; $i < mb_strlen($name); $i++) {
        $hash = mb_ord(mb_substr($name, $i, 1)) + (($hash << 5) - $hash);
    }
    return $colors[abs($hash) % count($colors)];
}

/**
 * 渲染分页
 */
function renderPagination(int $current, int $total, string $urlTemplate): string {
    if ($total <= 1) return '';
    
    // 统一占位符：同时支持 {%page%}（Rewrite 生成）和 %d（传统用法）
    $urlTemplate = str_replace('{%page%}', '%d', $urlTemplate);
    
    $html = '<div class="pagination">';
    
    // 上一页
    if ($current > 1) {
        $prevUrl = str_replace('%d', $current - 1, $urlTemplate);
        $html .= '<a href="' . htmlspecialchars($prevUrl) . '" class="page-item">&laquo;</a>';
    }
    
    // 页码
    $range = 2;
    $start = max(1, $current - $range);
    $end = min($total, $current + $range);
    
    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars(str_replace('%d', 1, $urlTemplate)) . '" class="page-item">1</a>';
        if ($start > 2) $html .= '<span class="page-ellipsis">...</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $url = str_replace('%d', $i, $urlTemplate);
        $active = $i === $current ? ' active' : '';
        $html .= '<a href="' . htmlspecialchars($url) . '" class="page-item' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $total) {
        if ($end < $total - 1) $html .= '<span class="page-ellipsis">...</span>';
        $html .= '<a href="' . htmlspecialchars(str_replace('%d', $total, $urlTemplate)) . '" class="page-item">' . $total . '</a>';
    }
    
    // 下一页
    if ($current < $total) {
        $nextUrl = str_replace('%d', $current + 1, $urlTemplate);
        $html .= '<a href="' . htmlspecialchars($nextUrl) . '" class="page-item">&raquo;</a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * 渲染站点卡片列表
 */
function renderSiteCards(array $sites, int $showWeight = 1): string {
    $html = '';
    foreach ($sites as $site) {
        $maxBr = getMaxBr($site);
        $tags = parseTags($site['tags'] ?? '[]');
        $domain = getDisplayDomain($site['url']);
        $color = getSiteColor($site['name']);
        $firstChar = mb_substr($site['name'], 0, 1);
        
        $html .= '<a href="' . Theme::url('site', ['id' => (int)$site['id'], 'slug' => $site['category_slug'] ?? '']) . '" class="card">';
        $html .= '<div class="card-header">';
        $html .= '<div class="site-icon" style="width:36px;height:36px;background:' . $color . '15;color:' . $color . '">' . htmlspecialchars($firstChar) . '</div>';
        $html .= '<div class="card-title-wrap">';
        $html .= '<span class="card-title">' . Theme::e($site['name']) . '</span>';
        $html .= '</div>';
        if (!empty($showWeight) && $maxBr > 0) {
            $html .= '<span class="weight-badge ' . getWeightBadgeClass($maxBr) . '">BR ' . $maxBr . '</span>';
        }
        $html .= '</div>';
        $html .= '<div class="card-url">' . Theme::e($domain) . '</div>';
        $html .= '<div class="card-desc">' . Theme::e($site['description'] ?? '') . '</div>';
        $html .= '<div class="card-footer">';
        if (!empty($tags)) {
            $html .= '<div class="card-tags">';
            foreach (array_slice($tags, 0, 2) as $tag) {
                $html .= '<span class="tag">' . Theme::e($tag) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '<span class="card-views"><i class="ti ti-eye"></i>' . formatNumber((int)$site['views']) . '</span>';
        $html .= '</div></a>';
    }
    return $html;
}

/**
 * 格式化数字（1000 -> 1k, 1000000 -> 1M）
 */
function formatNumber(int $num): string {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'k';
    }
    return (string)$num;
}

/**
 * 格式化日期
 */
function formatDate(string $date, string $format = 'Y-m-d'): string {
    if (empty($date) || $date === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

/**
 * 解析标签字段（JSON 字符串 -> 数组）
 * 兼容已是数组或空值的输入
 */
function parseTags($tags): array {
    if (is_array($tags)) {
        return $tags;
    }
    if (empty($tags)) {
        return [];
    }
    $decoded = json_decode($tags, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * 将标签数组转为SEO关键词字符串
 */
function tagsToKeywords(array $tags): string {
    return implode(',', $tags);
}

/**
 * 获取带前缀的表名
 */
function table(string $name): string {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'nav_';
    return $prefix . $name;
}

/**
 * 获取站点最高权重
 */
function getMaxBr(array $site): int {
    $brs = [
        (int)($site['br_pc'] ?? 0),
        (int)($site['br_mobile'] ?? 0),
        (int)($site['br_360'] ?? 0),
        (int)($site['br_shenma'] ?? 0),
    ];
    return max($brs);
}

/**
 * 从网站标题中智能提取主标题
 * 支持 "主标题 - 副标题" 和 "副标题 - 主标题" 两种格式
 * 主标题通常在 6 个中文字符以内
 * @return string 提取后的主标题，最多 6 个中文字符
 */
function extractMainTitle(string $title): string {
    $title = trim($title);
    if (empty($title)) {
        return '';
    }
    // 按常见分隔符分割为最多 2 部分
    $parts = preg_split('/[\s\-ー|｜,，、]+/u', $title, 2);
    if (count($parts) < 2) {
        // 无分隔符，直接截取前 6 个字符
        return mb_substr($title, 0, 6);
    }
    $part1 = trim($parts[0]);
    $part2 = trim($parts[1] ?? '');
    $len1 = mb_strlen($part1);
    $len2 = mb_strlen($part2);
    if ($len1 <= 6) {
        return $part1;
    }
    if ($len2 <= 6) {
        return $part2;
    }
    // 两部分都超过 6 字，截取第一部分前 6 字
    return mb_substr($part1, 0, 6);
}
