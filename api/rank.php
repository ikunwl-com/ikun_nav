<?php
/**
 * 本地 5118 权重查询 API
 * 调用 5118 官方 API（https://apis.5118.com/weight）获取权重数据
 * 默认从后台设置读取 api_key_5118，也支持前端传入
 *
 * 5118 返回结构：
 *   { "errcode": "0", "errmsg": "", "data": { "result": [ {"type":"BaiduPCWeight","weight":"5"}, ... ] } }
 * 字段映射（参考 MetaApi.php）：
 *   BaiduPCWeight -> br_pc, BaiduMobileWeight -> br_mobile,
 *   HaoSouWeight  -> br_360, SMWeight -> br_shenma, TouTiaoWeight -> toutiao
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

// 频率限制：每 IP 每分钟最多 10 次
if (!Security::rateLimit("rank:{$ip}", 10, 60)) {
    Security::jsonOutput(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$url = Security::cleanString($input['url'] ?? '');
if (empty($url)) {
    Security::jsonOutput(['success' => false, 'message' => 'URL 不能为空'], 400);
}

// 提取域名（5118 只接受纯域名）
$domain = Security::extractDomain($url);
if (empty($domain)) {
    Security::jsonOutput(['success' => false, 'message' => '域名格式无效'], 400);
}

// SSRF 防护
if (Security::isInternalHost($domain)) {
    Security::jsonOutput(['success' => false, 'message' => '不允许查询内网地址'], 400);
}

// 域名格式校验
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*(\.[a-zA-Z0-9][a-zA-Z0-9\-]*)+$/', $domain)) {
    Security::jsonOutput(['success' => false, 'message' => '域名格式无效'], 400);
}

// 获取 API Key：优先从前端传入，其次从后台设置读取
$apiKey = Security::cleanString($input['api_key'] ?? '') ?: setting('api_key_5118', '');
if (empty($apiKey)) {
    Security::jsonOutput(['success' => false, 'message' => '未配置 5118 API Key，请在后台设置'], 400);
}

// ===== 文件缓存（7 天，与 MetaApi.php 一致）=====
$cacheDir = sys_get_temp_dir() . '/nav_rank_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0700, true);
}
$cacheFile = $cacheDir . '/' . md5($domain) . '.json';
$cacheExpire = 7 * 24 * 3600; // 7 天

if (file_exists($cacheFile)) {
    $cacheContent = @file_get_contents($cacheFile);
    if ($cacheContent) {
        $cacheData = json_decode($cacheContent, true);
        if ($cacheData && (time() - $cacheData['cached_at']) < $cacheExpire) {
            Logger::log('api_5118', "缓存命中 [domain={$domain}] IP={$ip}");
            $cacheData['from_cache'] = true;
            Security::jsonOutput($cacheData['data'] + ['from_cache' => true, 'success' => true]);
        }
    }
}

Logger::log('api_5118', "缓存失效/不存在 [domain={$domain}] IP={$ip}");

// ===== 调用 5118 API（官方格式） =====
// 参考 5118 官方 PHP 示例：https://www.5118.com/apistore/detail/69429f16-24f0-e711-80c8-1866da4dbcc0/-1
$apiUrl = 'https://apis.5118.com/weight';
$headers = [
    // 官方格式：Authorization:{apikey}（不带 APIKEY 前缀）
    'Authorization:' . $apiKey,
    // 官方格式：Content-Type:application/x-www-form-urlencoded; charset=UTF-8
    'Content-Type:application/x-www-form-urlencoded; charset=UTF-8',
];
// 官方格式：body 参数名是 url=...（不是 domain=...）
$postData = 'url=' . urlencode($domain);

Logger::log('api_5118', "请求5118 [domain={$domain}] IP={$ip}");

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_HEADER         => false,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

Logger::log('api_5118', "5118响应 [domain={$domain}] HTTP={$httpCode}");

if ($err) {
    Logger::log('api_5118', "curl错误 [domain={$domain}] err={$err} IP={$ip}");
    Security::jsonOutput(['success' => false, 'message' => '获取失败，请稍后重试'], 502);
}

if ($httpCode !== 200) {
    Logger::log('api_5118', "HTTP非200 [domain={$domain}] HTTP={$httpCode} IP={$ip}");
    Security::jsonOutput(['success' => false, 'message' => '5118 接口返回 HTTP ' . $httpCode], 502);
}

$rawData = json_decode($response, true);
if (!$rawData || !isset($rawData['errcode'])) {
    $rawSnippet = substr($response, 0, 500);
    Logger::log('api_5118', "JSON解析失败 [domain={$domain}] raw={$rawSnippet} IP={$ip}");
    Security::jsonOutput([
        'success' => false,
        'message' => 'API 响应格式错误（非 JSON）',
    ], 502);
}

// 5118 errcode 为字符串 "0" 表示成功
// 业务层错误（限流、参数错误等）→ HTTP 200 + success=false，不让浏览器报 502
if ((string)($rawData['errcode'] ?? '-1') !== '0') {
    // 提取 5118 返回的业务错误信息
    $errmsg = $rawData['errmsg'] ?? '5118 API 返回错误';
    $errcode = $rawData['errcode'] ?? '';
    // 错误码映射，给更友好的提示
    $friendly = [
        '429'  => '服务每秒调用量超限（QPS限制），请稍后重试',
        '1001' => 'API Key 无效或已过期',
        '1002' => '账户余额不足',
    ];
    if (isset($friendly[$errcode])) {
        $errmsg = $friendly[$errcode];
    }
    Logger::log('api_5118', "5118业务错误 [domain={$domain}] errcode={$errcode} errmsg={$errmsg} IP={$ip}");
    Security::jsonOutput([
        'success' => false,
        'message' => $errmsg,
        'errcode' => $errcode,
    ], 200);
}

// ===== 解析权重数据（参考 MetaApi.php getWeight() 的字段映射）=====
$weightMap = [
    'BaiduPCWeight'     => 'br_pc',
    'BaiduMobileWeight' => 'br_mobile',
    'HaoSouWeight'      => 'br_360',
    'SMWeight'          => 'br_shenma',
    'TouTiaoWeight'     => 'toutiao',
];

$result = [
    'domain'     => $domain,
    'br_pc'      => 0,
    'br_mobile'  => 0,
    'br_360'     => 0,
    'br_shenma'  => 0,
    'toutiao'    => 0,
];

$resultList = $rawData['data']['result'] ?? [];
if (is_array($resultList)) {
    foreach ($resultList as $item) {
        $type   = $item['type'] ?? '';
        $weight = $item['weight'] ?? '0';
        if (isset($weightMap[$type])) {
            $field = $weightMap[$type];
            // 提取数字部分（5118 可能返回 "5" 或 "权重5" 等格式）
            $result[$field] = intval(preg_replace('/\D/', '', $weight));
        }
    }
}

// 写入缓存
$cachePayload = [
    'cached_at' => time(),
    'data'      => $result,
];
@file_put_contents($cacheFile, json_encode($cachePayload, JSON_UNESCAPED_UNICODE), LOCK_EX);

Logger::log('api_5118', "请求成功 [domain={$domain}] IP={$ip}");

Security::jsonOutput([
    'success'    => true,
    'from_cache' => false,
] + $result);
