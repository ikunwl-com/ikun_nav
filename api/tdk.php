<?php
/**
 * 本地 TDK 获取 API
 * 抓取目标网页直接解析 title / description / keywords / icon
 * 不依赖外部 API，纯本地实现
 */

require_once __DIR__ . '/../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// CORS：仅允许同源请求
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin && $host) {
    $originHost = parse_url($origin, PHP_URL_HOST) ?? '';
    if ($originHost && strcasecmp($originHost, $host) === 0) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

$ip = Security::getClientIP();

// 频率限制：优先读取 submit 插件配置，兼容旧版 settings.rate_limit_tdk
$pluginRate = Plugin::config('submit', 'tdk_rate_limit', null);
$tdkRateLimit = ($pluginRate !== null)
    ? (int)$pluginRate
    : (int)setting('rate_limit_tdk', 10);
if ($tdkRateLimit > 0 && !Security::rateLimit("tdk:{$ip}", $tdkRateLimit, 60)) {
    echo json_encode(['success' => false, 'message' => '请求过于频繁，请稍后再试']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$url = Security::cleanString($input['url'] ?? '');
if (empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL 不能为空']);
    exit;
}

// 确保 URL 有协议
if (!preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
}

// 验证 URL 格式
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'URL 格式无效']);
    exit;
}

// SSRF 防护：禁止内网地址
$host = parse_url($url, PHP_URL_HOST) ?: '';
if (empty($host) || Security::isInternalHost($host)) {
    echo json_encode(['success' => false, 'message' => '不允许查询内网地址']);
    exit;
}

// 抓取网页
$startTime = time();
Logger::log('api_tdk', "开始抓取 [url={$url}] IP={$ip}");

$html = null;
$ctx = stream_context_create([
    'http' => [
        'timeout'         => 10,
        'follow_location' => 0,
        'max_redirects'   => 0,
        'header'          => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                           . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n",
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$html = @file_get_contents($url, false, $ctx);

// file_get_contents 失败时尝试 cURL
if (!$html && function_exists('curl_init')) {
    Logger::log('api_tdk', "file_get_contents失败，切换cURL [url={$url}]");
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
}

if (!$html) {
    $elapsed = time() - $startTime;
    Logger::log('api_tdk', "抓取失败 [url={$url}] IP={$ip} 耗时={$elapsed}s");
    echo json_encode(['success' => false, 'message' => '无法获取网页内容，请检查 URL 是否可访问']);
    exit;
}

$elapsed = time() - $startTime;
Logger::log('api_tdk', "抓取成功 [url={$url}] IP={$ip} 耗时={$elapsed}s");

// 解析 TDK（参考 MetaApi.php extractTdk()，兼容 content 在前/在后两种写法）
$title       = '';
$description = '';
$keywords    = '';
$icon        = '';

// 提取 title
if (preg_match('/<title\s*[^>]*>([\s\S]*?)<\/title>/i', $html, $m)) {
    $title = html_entity_decode(trim(preg_replace('/\s+/', ' ', $m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 提取 meta keywords：name 在前 content 在后
if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\']([^"\']*)["\']/i', $html, $m)) {
    $keywords = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
} elseif (preg_match('/<meta\s+content=["\']([^"\']*)["\']\s+name=["\']keywords["\']/i', $html, $m)) {
    // content 在前 name 在后
    $keywords = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 提取 meta description：name 在前 content 在后
if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\']/i', $html, $m)) {
    $description = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
} elseif (preg_match('/<meta\s+content=["\']([^"\']*)["\']\s+name=["\']description["\']/i', $html, $m)) {
    // content 在前 name 在后
    $description = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 提取 favicon（兼容 rel 顺序差异）
$baseUrl = (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://' . (parse_url($url, PHP_URL_HOST) ?: '');
$iconPattern = '/<link\s+(?:[^>]*?\s+)?rel=["\'](?:icon|shortcut icon|ico)["\']\s+(?:[^>]*?\s+)?href=["\']([^"\']+)["\']/is';
if (preg_match($iconPattern, $html, $m)) {
    $icon = trim($m[1]);
    if (strpos($icon, 'http') !== 0) {
        $icon = rtrim($baseUrl, '/') . '/' . ltrim($icon, '/');
    }
} else {
    $icon = $baseUrl . '/favicon.ico';
}

// ===== 文件缓存（7 天，与 rank.php 一致）=====
$cacheDir = sys_get_temp_dir() . '/nav_tdk_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0700, true);
}
$domainKey = parse_url($url, PHP_URL_HOST) ?: md5($url);
$cacheFile = $cacheDir . '/' . md5($domainKey) . '.json';

$resultData = [
    'title'       => $title,
    'description' => $description,
    'keywords'    => $keywords,
    'icon'        => $icon,
];

@file_put_contents($cacheFile, json_encode([
    'cached_at' => time(),
    'data'      => $resultData,
], JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode([
    'success'     => true,
    'from_cache'  => false,
    'title'       => $title,
    'description' => $description,
    'keywords'    => $keywords,
    'icon'        => $icon,
], JSON_UNESCAPED_UNICODE);
