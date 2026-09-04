<?php
/**
 * API 路由入口
 * 所有 /api/* 请求通过此文件分发
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/WormholeModel.php';
require_once __DIR__ . '/../core/BlacklistModel.php';
require_once __DIR__ . '/../core/AutoLinkModel.php';
require_once __DIR__ . '/../core/ApiKeyModel.php';


// 错误处理：bootstrap 已根据 APP_DEBUG 设置了通用处理器，这里覆盖为 JSON 格式输出
if (APP_DEBUG) {
    ini_set('display_errors', '0');
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        error_log("API Error [$errno]: $errstr in $errfile:$errline");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $errstr,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    });
    set_exception_handler(function ($e) {
        error_log('API Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    });
} else {
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        error_log("API Error [$errno]: $errstr in $errfile:$errline");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '服务器内部错误']);
        exit;
    });
    set_exception_handler(function ($e) {
        error_log('API Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '服务器内部错误']);
        exit;
    });
}

// ========== 全局 CORS：限制为同源或白名单 ==========
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin) {
    $originHost = parse_url($origin, PHP_URL_HOST) ?? '';
    if ($originHost && (strcasecmp($originHost, $host) === 0 || !Security::isInternalHost($originHost))) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-API-Key, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');

$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// 预检请求直接返回
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';

// 路由表：endpoint => [允许方法, handler函数名]
$routes = [
    'sites'      => ['GET', 'api_sites'],
    'featured'   => ['GET', 'api_featured'],
    'site'       => ['GET', 'api_site_detail'],
    'search'     => ['GET', 'api_search'],
    'submit'     => ['POST', 'api_submit'],
    'click'      => ['POST', 'api_click'],
    'fetch-tdk'  => ['POST', 'api_fetch_tdk'],
    'update-meta'=> ['POST', 'api_update_meta'],
    'wormhole'   => ['GET', 'api_wormhole_display'],
    'wormhole.js'=> ['GET', 'api_wormhole_js'],
    'wormhole-teleport'  => ['GET', 'api_wormhole_teleport'],
    'wormhole-join'      => ['GET', 'api_wormhole_join'],
    'rate'               => ['POST', 'api_rate'],
    'feedback'           => ['POST', 'api_feedback'],
    'auto-link'          => ['GET', 'api_auto_link'],
];

// ===== 开放 API（需要 API Key 鉴权） =====
// 核心 open/* 接口与「已启用插件」声明的接口统一由 OpenApi 注册表（core/OpenApi.php）管理：
// 插件开启后其接口自动注册，无需修改本文件；关闭后自动失效。
$routes = array_merge($routes, OpenApi::coreRoutes());

// 仅对 open/* 请求补充加载已启用插件的接口（避免普通请求加载插件 api.php）
if (OpenApi::isOpen($endpoint)) {
    $routes = array_merge($routes, OpenApi::pluginRoutes());
}

// 插件守卫：某些 API 端点依赖特定插件，未启用时返回错误
$pluginGuardedEndpoints = [
    'wormhole'         => 'wormhole',
    'wormhole.js'      => 'wormhole',
    'wormhole-teleport'=> 'wormhole',
    'wormhole-join'    => 'wormhole',
    'auto-link'        => 'auto-link',
    'submit'           => 'submit',
];

if (isset($pluginGuardedEndpoints[$endpoint])) {
    $requiredPlugin = $pluginGuardedEndpoints[$endpoint];
    if (!Plugin::isEnabled($requiredPlugin)) {
        // 对图片类 API（pixel tracking / GIF）返回透明 GIF 而非 JSON
        if (in_array($endpoint, ['wormhole-join', 'auto-link'], true)) {
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
        Security::jsonOutput(['success' => false, 'message' => '该功能对应的插件未启用'], 403);
    }
}

if (!isset($routes[$endpoint])) {
    // 端点属于某个插件但该插件未启用时给出明确提示（而非笼统的“接口不存在”）
    $pluginDef = OpenApi::findPluginDef($endpoint);
    if ($pluginDef) {
        Security::jsonOutput([
            'success' => false,
            'code'    => 40301,
            'message' => '接口所属插件「' . ($pluginDef['plugin_title'] ?? $pluginDef['plugin']) . '」未启用，请在后台插件管理中启用后使用',
        ], 403);
    }
    Security::jsonOutput(['success' => false, 'message' => '接口不存在'], 404);
}

[$allowedMethod, $handler] = $routes[$endpoint];

if ($method !== $allowedMethod) {
    Security::jsonOutput(['success' => false, 'message' => '请求方法不允许'], 405);
}

// POST 接口 CSRF 校验（click/rate/feedback 除外，这些是公开 API）
// update-meta 需要 CSRF 校验以防止站点元数据被篡改
// open/* 开放接口使用 API Key 鉴权，豁免 CSRF（由下方 API Key 校验保护）
$csrfExempts = ['click', 'rate', 'feedback'];
if ($method === 'POST' && !OpenApi::isOpen($endpoint) && !in_array($endpoint, $csrfExempts)) {
    if ($endpoint === 'fetch-tdk' && !empty($_GET['internal'])) {
        // 内部调用：使用 HMAC 签名验证而非简单 GET 参数
        $sign = $_SERVER['HTTP_X_INTERNAL_SIGN'] ?? '';
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $expected = hash_hmac('sha256', 'internal-fetch-tdk', $csrfToken);
        if (!hash_equals($expected, $sign)) {
            Security::jsonOutput(['success' => false, 'message' => '内部调用验证失败'], 403);
        }
    } else {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!Security::verifyCSRFToken($csrfToken)) {
            Security::jsonOutput(['success' => false, 'message' => 'CSRF 校验失败'], 403);
        }
    }
}

// ========== API Key 鉴权（开放接口 open/*，含核心接口与已启用插件接口） ==========
if (OpenApi::isOpen($endpoint)) {
    $apiKeyModel = new ApiKeyModel();

    // 从 Header、GET 参数或 POST JSON 中获取 API Key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($apiKey === '' && $method === 'POST') {
        $apiKey = api_json_input()['api_key'] ?? '';
    }

    if (empty($apiKey)) {
        header('X-RateLimit-Limit: 0');
        header('X-RateLimit-Remaining: 0');
        Security::jsonOutput([
            'success' => false,
            'code' => 40101,
            'message' => '缺少 API Key，请在请求头 X-API-Key 或参数 api_key 中提供',
        ], 401);
    }

    // 验证 API Key 有效性
    if (!$apiKeyModel->validate($apiKey)) {
        header('X-RateLimit-Limit: 0');
        header('X-RateLimit-Remaining: 0');
        Security::jsonOutput([
            'success' => false,
            'code' => 40102,
            'message' => 'API Key 无效或已过期',
        ], 401);
    }

    // 检查调用频率限制
    if (!$apiKeyModel->checkRateLimit($apiKey)) {
        $remaining = $apiKeyModel->getRateLimitRemaining($apiKey);
        $minRemaining = $remaining['minute']['remaining'] ?? 0;
        $minLimit = $remaining['minute']['limit'] ?? 0;
        header('X-RateLimit-Limit: ' . $minLimit);
        header('X-RateLimit-Remaining: 0');
        header('Retry-After: 60');
        Security::jsonOutput([
            'success' => false,
            'code' => 42901,
            'message' => '调用频率超出限制，请稍后再试',
            'rate_limit' => $remaining,
        ], 429);
    }

    // 记录调用
    $apiKeyModel->recordCall($apiKey);

    // 返回限流信息到响应头
    $remaining = $apiKeyModel->getRateLimitRemaining($apiKey);
    header('X-RateLimit-Limit: ' . ($remaining['minute']['limit'] ?? 0));
    header('X-RateLimit-Remaining: ' . ($remaining['minute']['remaining'] ?? 0));
    header('X-RateLimit-Reset: ' . (time() + 60 - date('s')));
}

// 执行 handler
$handler();

// ==================== API Handlers ====================

/**
 * GET /api/sites?category={slug}&page=1&sort=br
 */
function api_sites(): void
{
    $catSlug = Security::cleanString($_GET['category'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $sort    = Security::cleanString($_GET['sort'] ?? 'br');

    $catModel  = new CategoryModel();
    $siteModel = new SiteModel();

    if ($catSlug === 'all') {
        $sites = $siteModel->getAllPublished($page, 24, $sort);
        $total = $siteModel->countAll(['status' => 'published']);
        $category = ['name' => '全部分类', 'seo_desc' => ''];
    } else {
        $category = $catModel->getBySlug($catSlug);
        if (!$category) {
            Security::jsonOutput(['success' => false, 'message' => '分类不存在'], 404);
        }
        // 推荐优先，不足补最新收录
        $sites = $siteModel->getCategorySites((int)$category['id'], 24, 'newest');
        $total = $siteModel->countByCategory((int)$category['id']);
    }

    // 浏览量统计
    $viewIds = array_column($sites, 'id');
    $siteModel->incrementViewsBatch($viewIds);

    $items = array_map('formatSite', $sites);

    Security::jsonOutput([
        'success'    => true,
        'data'       => $items,
        'total'      => $total,
        'page'       => $page,
        'totalPages' => (int)ceil($total / 24),
        'category'   => [
            'name'     => $category['name'] ?? '',
            'seo_desc' => $category['seo_desc'] ?? '',
        ],
    ]);
}

/**
 * GET /api/featured
 */
function api_featured(): void
{
    $siteModel = new SiteModel();
    $items     = $siteModel->getGlobalFeatured(12);
    $viewIds   = array_column($items, 'id');
    $siteModel->incrementViewsBatch($viewIds);

    Security::jsonOutput([
        'success' => true,
        'data'    => array_map('formatSite', $items),
    ]);
}

/**
 * GET /api/site?id=123
 */
function api_site_detail(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'message' => 'ID 不能为空'], 400);
    }

    $siteModel = new SiteModel();
    $site      = $siteModel->getSite($id);

    if (!$site || $site['status'] !== 'published') {
        Security::jsonOutput(['success' => false, 'message' => '站点不存在'], 404);
    }

    $catModel  = new CategoryModel();
    $category  = $catModel->getById((int)$site['category_id']);

    $related = $siteModel->getRelatedSites((int)$site['category_id'], $id, 6);

    // 获取评分统计
    $ratingStats = $siteModel->getRatingStats($id);

    Security::jsonOutput([
        'success'  => true,
        'data'     => array_merge(formatSite($site), [
            'category_name' => $category['name'] ?? '',
            'category_slug' => $category['slug'] ?? '',
            'description' => $site['description'] ?? '',
            'related'     => array_map('formatSite', $related),
            'rating_avg'  => $ratingStats['avg'],
            'rating_count'=> $ratingStats['count'],
        ]),
    ]);
}

/**
 * GET /api/search?q=xxx&page=1
 */
function api_search(): void
{
    $keyword = Security::cleanString($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));

    $siteModel = new SiteModel();
    $perPage   = 24;

    $sites = $siteModel->searchPaged($keyword, $page, $perPage);
    $total = $siteModel->searchCount($keyword);

    // 浏览量统计
    $viewIds = array_column($sites, 'id');
    $siteModel->incrementViewsBatch($viewIds);

    Security::jsonOutput([
        'success'    => true,
        'data'       => array_map('formatSite', $sites),
        'total'      => $total,
        'page'       => $page,
        'totalPages' => (int)ceil($total / $perPage),
        'keyword'    => $keyword,
    ]);
}

/**
 * POST /api/submit - 提交站点
 */
function api_submit(): void
{
    $settings = (new SettingsModel())->loadAll();
    // 优先读取 submit 插件的启用配置，兼容旧版 settings.enable_submit
    $pluginEnable = Plugin::config('submit', 'enable_submit', null);
    $enableSubmit = ($pluginEnable !== null)
        ? ($pluginEnable === '1')
        : (($settings['enable_submit'] ?? '1') === '1');
    if (!$enableSubmit) {
        Security::jsonOutput(['success' => false, 'message' => '当前暂停提交'], 403);
    }

    $ip = Security::getClientIP();
    $pluginRate = Plugin::config('submit', 'rate_limit', null);
    $rateLimit = ($pluginRate !== null)
        ? (int)$pluginRate
        : (int)($settings['rate_limit_submit'] ?? 5);
    if ($rateLimit > 0 && !Security::rateLimit("submit:{$ip}", $rateLimit, 3600)) {
        Security::jsonOutput(['success' => false, 'message' => '提交过于频繁，请稍后再试'], 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    $name = Security::cleanString($input['name'] ?? '');
    $url  = Security::safeUrl($input['url'] ?? '');
    $cat  = (int)($input['category_id'] ?? 0);
    $tags = Security::cleanTags($input['tags'] ?? '');
    $description = Security::cleanString($input['description'] ?? '', 200);
    $email = Security::cleanString($input['email'] ?? '', 100);
    $br_pc     = (int)($input['br_pc'] ?? 0);
    $br_mobile = (int)($input['br_mobile'] ?? 0);
    $br_360    = (int)($input['br_360'] ?? 0);
    $br_shenma = (int)($input['br_shenma'] ?? 0);

    if (empty($name) || empty($url)) {
        Security::jsonOutput(['success' => false, 'message' => '名称和网址不能为空'], 400);
    }
    if ($cat <= 0) {
        Security::jsonOutput(['success' => false, 'message' => '请选择分类'], 400);
    }

    $catModel = new CategoryModel();
    if (!$catModel->getById($cat)) {
        Security::jsonOutput(['success' => false, 'message' => '分类不存在'], 400);
    }

    // 审核开关：优先读取 submit 插件配置，兼容旧版 settings.need_review
    $pluginNeedReview = Plugin::config('submit', 'need_review', null);
    $needReview = ($pluginNeedReview !== null)
        ? ($pluginNeedReview === '1')
        : (($settings['need_review'] ?? '1') === '1');
    $status = $needReview ? 'pending' : 'published';

    $siteModel = new SiteModel();
    $id = $siteModel->create([
        'name'         => $name,
        'url'          => $url,
        'category_id'  => $cat,
        'description'  => $description,
        'tags'         => json_encode($tags, JSON_UNESCAPED_UNICODE),
        'title'        => $name,
        'keywords'     => implode(',', $tags),
        'status'       => $status,
        'submit_ip'    => $ip,
        'submit_email' => $email,
        'br_pc'        => $br_pc,
        'br_mobile'    => $br_mobile,
        'br_360'       => $br_360,
        'br_shenma'    => $br_shenma,
    ]);

    // 通知钩子：站点提交后触发（邮箱通知等插件可监听）
    Plugin::hook('site_submitted', [['id' => $id, 'name' => $name, 'url' => $url, 'category_id' => $cat, 'status' => $status, 'ip' => $ip, 'email' => $email]]);

    Security::jsonOutput([
        'success' => true,
        'message' => $status === 'pending' ? '提交成功，等待审核' : '提交成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/click?id=123
 * 记录点击（跳过CSRF）
 */
function api_click(): void
{
    $ip = Security::getClientIP();
    if (!Security::rateLimit("click:{$ip}", 60, 60)) {
        Security::jsonOutput(['success' => false, 'message' => '请求过于频繁'], 429);
    }

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'message' => 'ID 不能为空'], 400);
    }

    $siteModel = new SiteModel();
    $siteModel->incrementClicks($id);

    Security::jsonOutput(['success' => true]);
}

/**
 * POST /api/fetch-tdk - 后端获取 TDK 和权重
 * 前端通过此接口代理
 */
function api_fetch_tdk(): void
{
    $ip = Security::getClientIP();

    // 频率限制
    if (!Security::rateLimit("fetchtdk:{$ip}", 10, 60)) {
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

    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    // 本地抓取 TDK + 5118 权重
    $tdk  = fetchLocalTDK($url);
    $meta = fetchMetaData($url);

    Security::jsonOutput([
        'success'     => true,
        'title'       => $tdk['title']       ?? '',
        'description' => $tdk['description'] ?? '',
        'keywords'    => $tdk['keywords']    ?? '',
        'icon'        => '',
        'br_pc'       => $meta['br_pc']      ?? 0,
        'br_mobile'   => $meta['br_mobile']  ?? 0,
        'br_360'      => $meta['br_360']     ?? 0,
        'br_shenma'   => $meta['br_shenma']  ?? 0,
    ]);
}

/**
 * POST /api/?endpoint=update-meta - 前台一键更新站点 TDK + 权重
 * 需要 CSRF 校验
 */
function api_update_meta(): void
{
    $ip = Security::getClientIP();
    if (!Security::rateLimit("updatemeta:{$ip}", 5, 60)) {
        Security::jsonOutput(['success' => false, 'message' => '请求过于频繁'], 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $siteId = (int)($input['site_id'] ?? 0);
    $url    = Security::cleanString($input['url'] ?? '');

    if ($siteId <= 0) {
        Security::jsonOutput(['success' => false, 'message' => '站点ID不能为空'], 400);
    }
    if (empty($url)) {
        Security::jsonOutput(['success' => false, 'message' => 'URL不能为空'], 400);
    }

    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    // SSRF 防护
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    if (empty($host) || Security::isInternalHost($host)) {
        Security::jsonOutput(['success' => false, 'message' => '不允许的URL'], 400);
    }

    $siteModel = new SiteModel();
    $site = $siteModel->getSite($siteId);
    if (!$site) {
        Security::jsonOutput(['success' => false, 'message' => '站点不存在'], 404);
    }

    // 复用统一的 fetchMetaData 获取 TDK + 权重
    $meta = fetchMetaData($url);

    $updateData = [];

    // 更新 title（从目标站抓取，清理后作为 name）
    if (!empty($meta['title'])) {
        // 提取主标题：只取分隔符前的部分
        $title = preg_split('/[\s\-ー|｜,，、]+/u', $meta['title'], 2);
        $mainTitle = trim($title[0]);
        if (!empty($mainTitle) && mb_strlen($mainTitle) <= 100) {
            $updateData['name'] = Security::cleanString($mainTitle, 100);
        }
    }

    // 更新 description
    if (!empty($meta['description'])) {
        $updateData['description'] = Security::cleanString($meta['description'], 200);
    }

    // 更新 keywords（同步到 tags JSON 数组）
    if (!empty($meta['keywords'])) {
        $keywords = array_slice(
            array_filter(array_map('trim', explode(',', $meta['keywords']))),
            0,
            10
        );
        $updateData['tags'] = json_encode($keywords, JSON_UNESCAPED_UNICODE);
    }

    // 更新权重字段
    $brFields = ['br_pc', 'br_mobile', 'br_360', 'br_shenma'];
    foreach ($brFields as $field) {
        if (isset($meta[$field])) {
            $updateData[$field] = max(0, min(10, (int)$meta[$field]));
        }
    }

    if (!empty($updateData)) {
        $siteModel->update($siteId, $updateData);
    }

    // 返回更新后的完整数据
    $site = $siteModel->getSite($siteId);
    Security::jsonOutput([
        'success' => true,
        'message' => '数据已更新',
        'tdk_updated' => !empty($meta['title']) || !empty($meta['description']),
        'rank_updated' => isset($meta['br_pc']),
        'data' => [
            'title'       => $site['name'] ?? '',
            'description' => $site['description'] ?? '',
            'keywords'    => tagsToKeywords(parseTags($site['tags'] ?? '[]')),
            'br_pc'       => (int)$site['br_pc'],
            'br_mobile'   => (int)$site['br_mobile'],
            'br_360'      => (int)$site['br_360'],
            'br_shenma'   => (int)$site['br_shenma'],
        ]
    ]);
}

// ==================== 虫洞联盟 API ====================

/**
 * GET /api/?endpoint=wormhole
 * 返回联盟成员站点列表（JSON，用于 /wormhole/ 页面展示）
 */
function api_wormhole_display(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    $wormhole = new WormholeModel();
    $members  = $wormhole->getMembers();
    Logger::log('wormhole_display', '获取联盟成员列表，共 ' . count($members) . ' 个站点');

    $items = array_map(function ($m) {
        $url = $m['url'] ?? '';
        // 确保 URL 有协议头
        if ($url !== '' && !preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        // 解析标签
        $tags = [];
        if (!empty($m['tags'])) {
            $tagsRaw = is_string($m['tags']) ? json_decode($m['tags'], true) : $m['tags'];
            if (is_array($tagsRaw)) {
                $tags = $tagsRaw;
            }
        }
        return [
            'name'        => $m['name'] ?? '',
            'url'         => $url,
            'domain'      => getDisplayDomain($url),
            'title'       => $m['name'] ?? '',  // 站点名称作为标题
            'description' => $m['description'] ?? '',  // 站点描述
            'keywords'    => implode(',', $tags),  // 标签转为关键词
        ];
    }, $members);

    echo json_encode(['success' => true, 'count' => count($items), 'data' => $items]);
    exit;
}

/**
 * GET /api/?endpoint=wormhole.js
 * 返回虫洞联盟 JS 脚本
 * 功能：外站挂载后，在页面底部展示 12 个随机联盟站点
 */
function api_wormhole_js(): void
{
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    $settings = (new SettingsModel())->loadAll();
    $siteUrl  = rtrim($settings['site_url'] ?? 'https://site.ikunwl.com', '/');

    $apiUrl  = $siteUrl . '/api/?endpoint=wormhole-join';
    $dataUrl = $siteUrl . '/api/?endpoint=wormhole';

    $js = <<<'JS'
(function(){
    var d=document,
        api='__API_URL__',
        dataUrl='__DATA_URL__',
        siteUrl='__SITE_URL__';

    // 在同步阶段捕获脚本位置，否则异步回调后 currentScript 已失效
    var _script=d.currentScript;

    // ========== 自动上报（加入联盟）==========
    // 通过 fetch 访问加入接口，触发 Referer 上报
    try {
        var apiUrl = api;
        var ref = encodeURIComponent(window.location.href || '');
        apiUrl += (apiUrl.indexOf('?') > -1 ? '&' : '?') + 'ref=' + ref;
        // 使用 no-cors 模式，无需等待响应
        fetch(apiUrl, {mode: 'no-cors', referrerPolicy: 'no-referrer-when-downgrade'}).catch(function(){});
    } catch(e) {}

    // ========== 获取联盟成员并渲染 ==========
    function init(){
        if(!d.body)return;
        fetch(dataUrl)
            .then(function(r){return r.json();})
            .then(function(res){
                if(!res.success||!res.data||!res.data.length)return;
                var members=res.data;
                // Fisher-Yates 随机打乱
                for(var i=members.length-1;i>0;i--){
                    var j=Math.floor(Math.random()*(i+1));
                    var tmp=members[i];members[i]=members[j];members[j]=tmp;
                }
                // 截取前12个
                var list=members.slice(0,12);

                var box=d.createElement('div');
                box.id='wormhole-panel';
                box.style.cssText='padding:12px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';

                var title=d.createElement('div');
                title.textContent='🚀 虫洞联盟 · 随机站点';
                title.style.cssText='font-size:14px;color:#666;margin-bottom:10px;font-weight:500;padding-bottom:10px;border-bottom:1px solid #e0e0e0';
                box.appendChild(title);

                var listDiv=d.createElement('div');
                listDiv.style.cssText='display:flex;flex-wrap:wrap;gap:8px';

                for(var k=0;k<list.length;k++){
                    var m=list[k];
                    var a=d.createElement('a');
                    a.href=m.url||'#';
                    a.target='_blank';
                    a.rel='noopener nofollow';
                    a.textContent=m.name||'未知';
                    a.title=m.name||'';
                    a.style.cssText='display:inline-block;padding:5px 12px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;font-size:13px;color:#333;text-decoration:none;transition:all .2s';
                    a.onmouseover=function(){this.style.borderColor='#4e73df';this.style.color='#4e73df';this.style.boxShadow='0 2px 6px rgba(78,115,223,.1)';};
                    a.onmouseout=function(){this.style.borderColor='#e0e0e0';this.style.color='#333';this.style.boxShadow='none';};
                    listDiv.appendChild(a);
                }
                box.appendChild(listDiv);

                // 智能定位：优先插到 <script> 标签原本的位置（用户挂载处）
                if (_script && _script.parentNode) {
                    _script.parentNode.insertBefore(box, _script.nextSibling);
                } else if (d.body.lastChild) {
                    d.body.insertBefore(box, d.body.lastChild);
                } else {
                    d.body.appendChild(box);
                }
            }).catch(function(e){});
    }

    if(d.readyState==='loading'){
        d.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;

    $js = strtr($js, [
        '__API_URL__'  => $apiUrl,
        '__DATA_URL__' => $dataUrl,
        '__SITE_URL__' => $siteUrl,
    ]);
    echo $js;
    exit;
}

/**
 * GET /api/?endpoint=wormhole-teleport
 * 随机跳转到一个联盟站点
 * 支持参数:
 *   ref - 来源URL（JS/A-tag传入，用于自动加入联盟）
 *   action=redirect - 直接重定向到目标站点
 */
function api_wormhole_teleport(): void
{
    $wormhole = new WormholeModel();
    $member   = $wormhole->getRandomMember();

    // 获取来源：优先使用 GET ref 参数（JS/A-tag），其次使用 HTTP_REFERER
    $ref = $_GET['ref'] ?? '';
    if (empty($ref)) {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
    }

    // 检测 Referer，触发来源域名自动加入联盟
    $refDomain = '';
    if (!empty($ref)) {
        $refDomain = Security::extractDomain($ref);
        if ($refDomain && !Security::isInternalHost($refDomain)) {
            // 全局 IP 屏蔽：开启后拒绝所有纯 IP 地址的自动加入
            if (setting('block_all_ip', '0') === '1' && filter_var($refDomain, FILTER_VALIDATE_IP) !== false) {
                $refDomain = ''; // 清空，跳过后续自动加入逻辑
            }
            // 黑名单拦截：IP 或域名命中则跳过自动加入逻辑
            if ($refDomain) {
                $blacklist = new BlacklistModel();
                if ($blacklist->isBlocked(Security::getClientIP(), $refDomain)) {
                    $refDomain = ''; // 清空，跳过后续自动加入逻辑
                }
            }
        }
        if ($refDomain && !Security::isInternalHost($refDomain)) {
            // 异步触发加入逻辑（复用 wormhole-join 的处理）
            $tbl = Database::table('sites');
            $site = Database::queryOne(
                "SELECT id, wormhole_status FROM {$tbl} WHERE url LIKE ? AND status IN ('published', 'pending') LIMIT 1",
                ['%' . $refDomain . '%']
            );

            $wormholeEnabled = setting('wormhole_enable', '0') === '1';
            if ($wormholeEnabled && $site) {
                // 已收录站点：根据状态处理
                if ($site['wormhole_status'] === 'none') {
                    $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
                    if ($wormholeNeedReview) {
                        Database::execute(
                            "UPDATE {$tbl} SET wormhole_status = 'pending', wormhole_joined_at = NOW(), wormhole_last_check = NOW(), wormhole_check_fail = 0, wormhole_source_domain = ? WHERE id = ?",
                            [$refDomain, $site['id']]
                        );
                    } else {
                        $wormhole->joinAuto((int)$site['id'], $refDomain);
                    }
                } elseif ($site['wormhole_status'] === 'auto') {
                    $wormhole->markCheckPass((int)$site['id']);
                }
            } elseif ($wormholeEnabled && !$site) {
                // 未收录：自动创建并加入（复用 wormhole-join 逻辑）
                $catModel = new CategoryModel();
                $matchedCatId = $catModel->matchCategoryByKeywords($refDomain);
                if (!$matchedCatId) {
                    $fallbackRaw = trim(setting('wormhole_fallback_category', '1'));
                    $fallbackCat = null;
                    if (ctype_digit($fallbackRaw)) {
                        $fallbackCat = Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE id = ?", [(int)$fallbackRaw]);
                    }
                    if (!$fallbackCat) {
                        $fallbackCat = Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE slug = ?", [$fallbackRaw]);
                    }
                    $matchedCatId = $fallbackCat ? (int)$fallbackCat['id'] : (int)(Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE is_show = 1 ORDER BY sort_order LIMIT 1")['id'] ?? 1);
                }

                $siteModel = new SiteModel();
                $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
                $siteId = $siteModel->create([
                    'name' => $refDomain,
                    'url' => 'https://' . $refDomain,
                    'category_id' => $matchedCatId,
                    'status' => $wormholeNeedReview ? 'pending' : 'published',
                    'submit_ip' => Security::getClientIP(),
                ]);

                if ($siteId > 0) {
                    if ($wormholeNeedReview) {
                        Database::execute("UPDATE {$tbl} SET wormhole_status = 'pending', wormhole_joined_at = NOW(), wormhole_last_check = NOW(), wormhole_check_fail = 0, wormhole_source_domain = ? WHERE id = ?", [$refDomain, $siteId]);
                    } else {
                        $wormhole->joinAuto((int)$siteId, $refDomain);
                    }

                    // 获取TDK + 权重（title/keywords 自动同步 name/tags，只更新 description 和权重）
                    $meta = fetchMetaData('https://' . $refDomain);
                    if (!empty($meta['description']) || isset($meta['br_pc'])) {
                        $updateData = ['description' => Security::cleanString($meta['description'] ?? '', 200)];
                        foreach (['br_pc','br_mobile','br_360','br_shenma'] as $bf) {
                            if (isset($meta[$bf])) $updateData[$bf] = max(0, min(10, (int)$meta[$bf]));
                        }
                        $siteModel->update($siteId, $updateData);
                    }
                }
            }
        }
    }

    // 无成员或请求直接重定向
    if (!$member) {
        if (($_GET['action'] ?? '') === 'redirect') {
            header('Location: ' . siteUrl());
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '暂无联盟站点']);
        exit;
    }

    // 直接重定向模式（适合 A-tag 嵌入）
    if (($_GET['action'] ?? '') === 'redirect') {
        $target = $member['url'];
        if (!preg_match('/^https?:\/\//i', $target)) {
            $target = 'https://' . $target;
        }
        header('Location: ' . $target);
        exit;
    }

    // JSON 模式（适合 JS 调用）
    header('Content-Type: application/json; charset=utf-8');
    $wormhole->incrementClickOut((int)$member['id']);
    echo json_encode([
        'success' => true,
        'url'     => $member['url'],
        'name'    => $member['name'] ?? '',
    ]);
    exit;
}

/**
 * GET /api/?endpoint=wormhole-join&ref=xxx
 * 外站 JS 主动上报域名，触发自动加入联盟
 */
function api_wormhole_join(): void
{
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    // 写日志的快捷函数（统一使用 Logger 系统）
    $whLog = function(string $msg): void {
        Logger::log('wormhole_join', $msg);
    };

    // 记录原始请求参数（仅保留关键信息，不过度暴露 POST 内容）
    $refFromGet = $_GET['ref'] ?? '未提供';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'N/A';
    $whLog("[调试] 收到上报请求：ref={$refFromGet}，URI={$requestUri}");

    $domain = !empty($_GET['ref']) ? Security::extractDomain($_GET['ref']) : '';

    // 同时获取完整主机名（含 www/子域名），用于精确匹配避免子域名互相误判
    $refUrl = $_GET['ref'] ?? '';
    $parsed = parse_url($refUrl);
    $rawHost = ($parsed['host'] ?? '') ?: ($parsed['path'] ?? '');
    $rawHost = preg_replace('/:\d+$/', '', $rawHost);

    $whLog("[上报] 解析来源：原始域名={$rawHost}，处理后域名={$domain}");

    if (empty($domain) || Security::isInternalHost($domain)) {
        $whLog('[拒绝] 域名为空或属于内网地址');
        return;
    }

    // 过滤浏览器特殊伪协议（如 about:blank、javascript: 等）
    $badSchemes = ['about', 'javascript', 'data', 'blob', 'file', 'chrome', 'moz-extension'];
    foreach ($badSchemes as $scheme) {
        if (stripos($domain, $scheme . ':') === 0 || stripos($rawHost, $scheme . ':') === 0) {
            $whLog("[拒绝] 检测到非法伪协议：{$scheme}");
            return;
        }
    }

    // 全局 IP 屏蔽：开启后拒绝所有纯 IP 地址的自动收录
    if (setting('block_all_ip', '0') === '1') {
        if (filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            $whLog("[拒绝] 纯 IP 地址已被全局屏蔽：{$domain}");
            return;
        }
    }

    // 黑名单拦截：IP 或域名命中则静默忽略
    $clientIp = Security::getClientIP();
    $blacklist = new BlacklistModel();
    if ($blacklist->isBlocked($clientIp, $domain)) {
        $whLog("[拒绝] 命中黑名单（IP={$clientIp}，域名={$domain}）");
        return;
    }

    // 先查找该域名是否已收录
    $tbl = Database::table('sites');

    // 计算需要匹配的 host 变体：www 和非 www 互相识别
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
    $site = Database::queryOne(
        "SELECT id, wormhole_status, status FROM {$tbl} WHERE " . implode(' OR ', $likeClauses) . " LIMIT 1",
        $likeParams
    );
    $whLog("[查询] 站点匹配结果（变体：" . implode(', ', $hostVariants) . "）：" . ($site ? "已收录（ID={$site['id']}，联盟状态={$site['wormhole_status']}）" : "未收录"));

    // ========== 已收录站点 ==========
    if ($site && in_array($site['status'], ['published', 'pending'])) {
        // 已在联盟中（非 none）：忽略，仅更新检测时间
        if ($site['wormhole_status'] !== 'none') {
            if ($site['wormhole_status'] === 'auto') {
                $wormhole = new WormholeModel();
                $wormhole->markCheckPass((int)$site['id']);
                $whLog("[加入] 该站点已是联盟成员，更新检测时间：{$domain}");
            } else {
                $whLog("[加入] 该站点状态为【{$site['wormhole_status']}】，无需重复处理：{$domain}");
            }
            return;
        }

        // 已收录但未加入联盟：直接加入
        $whLog("[加入] 已收录但未加入联盟，正在处理：{$domain}");
        $wormhole = new WormholeModel();
        $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
        if ($wormholeNeedReview) {
            $sql = "UPDATE {$tbl} SET
                    wormhole_status = 'pending',
                    wormhole_joined_at = NOW(),
                    wormhole_last_check = NOW(),
                    wormhole_check_fail = 0,
                    wormhole_source_domain = ?
                    WHERE id = ?";
            $result = Database::execute($sql, [$domain, (int)$site['id']]) > 0;
            $whLog("[加入] 已设为待审核状态：{$domain}（" . ($result ? '成功' : '失败') . "）");
        } else {
            $result = $wormhole->joinAuto((int)$site['id'], $domain);
            $whLog("[加入] 已直接加入联盟：{$domain}（" . ($result ? '成功' : '失败') . "）");
        }
        return;
    }

    // ========== 未收录站点：需要新建 ==========
    // 频率限制：仅对新站点生效，优先读取 wormhole 插件配置
    $pluginWhRate = Plugin::config('wormhole', 'rate_limit', null);
    $rateLimit = ($pluginWhRate !== null)
        ? (int)$pluginWhRate
        : (int)setting('rate_limit_wormhole', 1);
    if ($rateLimit > 0 && !Security::rateLimit("wormhole_join:{$domain}", $rateLimit, 3600)) {
        $whLog("[加入] 触发频率限制（域名：{$domain}）");
        return;
    }

    // 未收录则自动创建站点并加入联盟
    if (!$site) {
        $whLog("[加入] 未收录，正在自动创建站点并加入联盟：{$domain}");
        $siteModel = new SiteModel();
        $catModel = new CategoryModel();

        // 智能分类匹配：先用域名匹配
        $matchedCatId = $catModel->matchCategoryByKeywords($domain);
        if (!$matchedCatId) {
            $fallbackRaw = setting('wormhole_fallback_category', '1');
            $fallbackRaw = trim($fallbackRaw);
            $fallbackCat = null;
            if (ctype_digit($fallbackRaw)) {
                $fallbackCat = Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE id = ?", [(int)$fallbackRaw]);
            }
            if (!$fallbackCat) {
                $fallbackCat = Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE slug = ?", [$fallbackRaw]);
            }
            $matchedCatId = $fallbackCat ? (int)$fallbackCat['id'] : (int)(Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE is_show = 1 ORDER BY sort_order LIMIT 1")['id'] ?? 1);
        }
        $catId = $matchedCatId;

        $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
        $siteId = $siteModel->create([
            'name' => $domain,
            'url' => 'https://' . $domain,
            'category_id' => $catId,
            'status' => $wormholeNeedReview ? 'pending' : 'published',
            'submit_ip' => Security::getClientIP(),
        ]);

        $whLog("[加入] 站点创建结果：{$domain}（ID={$siteId}）");

        if ($siteId > 0) {
            $wormhole = new WormholeModel();
            $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
            if ($wormholeNeedReview) {
                Database::execute("UPDATE {$tbl} SET wormhole_status = 'pending', wormhole_joined_at = NOW(), wormhole_last_check = NOW(), wormhole_check_fail = 0, wormhole_source_domain = ? WHERE id = ?", [$domain, $siteId]);
                $whLog("[加入] 已设为待审核状态：{$domain}");
            } else {
                $joinResult = $wormhole->joinAuto((int)$siteId, $domain);
                $whLog("[加入] 已加入联盟：{$domain}（" . ($joinResult ? '成功' : '失败') . "）");
            }

        // 自动获取 TDK + 权重并更新
        $meta = fetchMetaData('https://' . $domain);
        if (!empty($meta['title']) || !empty($meta['description']) || isset($meta['br_pc']) || !empty($meta['keywords'])) {
            $updateData = [];
            if (!empty($meta['title'])) {
                $mainTitle = extractMainTitle($meta['title']);
                if (!empty($mainTitle)) {
                    $updateData['name'] = Security::cleanString($mainTitle, 100);
                }
            }
            if (!empty($meta['description'])) {
                $updateData['description'] = Security::cleanString($meta['description'], 200);
            }
            if (!empty($meta['keywords'])) {
                $keywords = array_slice(array_filter(array_map('trim', explode(',', $meta['keywords']))), 0, 10);
                $updateData['tags'] = json_encode($keywords, JSON_UNESCAPED_UNICODE);
            }
            foreach (['br_pc','br_mobile','br_360','br_shenma'] as $bf) {
                if (isset($meta[$bf])) $updateData[$bf] = max(0, min(10, (int)$meta[$bf]));
            }
            if (!empty($updateData)) {
                $siteModel->update($siteId, $updateData);
                $whLog("[加入] 已更新站点信息：标题/描述/标签/权重，{$domain}");
            }
        }
        }
        return;
    }

    Logger::log('wormhole_join', "站点查找结果：" . ($site ? "已收录（ID={$site['id']}，状态={$site['wormhole_status']}）" : "未收录"));
    return;
}

// ==================== 评分 & 反馈 API ====================

/**
 * POST /api/rate - 提交站点评分（1-5星）
 */
function api_rate(): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $siteId = (int)($input['site_id'] ?? 0);
    $rating = (int)($input['rating'] ?? 0);
    $ip     = Security::getClientIP();

    if ($siteId <= 0) {
        Security::jsonOutput(['success' => false, 'message' => '站点ID不能为空'], 400);
    }
    if ($rating < 1 || $rating > 5) {
        Security::jsonOutput(['success' => false, 'message' => '评分需在 1~5 星之间'], 400);
    }

    $siteModel = new SiteModel();
    $result = $siteModel->submitRating($siteId, $rating, $ip);
    Security::jsonOutput($result);
}

/**
 * POST /api/feedback - 提交站点反馈
 */
function api_feedback(): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $siteId  = (int)($input['site_id'] ?? 0);
    $type    = Security::cleanString($input['type'] ?? 'other');
    $content = trim($input['content'] ?? '');
    $email   = Security::cleanString($input['email'] ?? '');
    $ip      = Security::getClientIP();

    if ($siteId <= 0) {
        Security::jsonOutput(['success' => false, 'message' => '站点ID不能为空'], 400);
    }
    if (empty($content)) {
        Security::jsonOutput(['success' => false, 'message' => '反馈内容不能为空'], 400);
    }

    $siteModel = new SiteModel();
    $ok = $siteModel->submitFeedback($siteId, $type, $content, $email, $ip);

    if ($ok) {
        // 通知钩子：反馈提交后触发（邮箱通知等插件可监听）
        Plugin::hook('feedback_submitted', [['site_id' => $siteId, 'type' => $type, 'content' => $content, 'email' => $email, 'ip' => $ip]]);
        Security::jsonOutput(['success' => true, 'message' => '反馈提交成功，我们会尽快处理']);
    } else {
        Security::jsonOutput(['success' => false, 'message' => '反馈提交失败'], 500);
    }
}

// ==================== 友链自动收录 API ====================

/**
 * GET /api/?endpoint=auto-link
 * 前台首页 JS 异步调用，检测 Referer 并自动收录友链站点
 * 返回 1x1 透明 GIF（类似 pixel tracking），避免 CORS 问题
 */
function api_auto_link(): void
{
    // 返回透明 GIF 图片（pixel tracking 模式）
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    // 输出 1x1 透明 GIF
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    // 如果功能未开启，直接返回
    if (setting('autolink_enable', '0') !== '1') {
        return;
    }

    // 连接数据库后可能抛异常，静默处理
    try {
        $autoLink = new AutoLinkModel();
        // 传入通过 URL 参数携带的原始来路（前台首页 JS 已预筛选）
        $refererOverride = $_GET['ref'] ?? '';
        $result = $autoLink->process($refererOverride);

        // 仅记录有意义的日志（跳过 no_referer / self / search_engine）
        $skipLog = ['no_referer', 'self', 'search_engine', 'disabled'];
        if (!in_array($result['action'], $skipLog, true)) {
            Logger::log('autolink', "[{$result['action']}] {$result['message']} IP=" . Security::getClientIP());
        }
    } catch (Throwable $e) {
        Logger::log('autolink', '自动收录异常：' . $e->getMessage());
    }
}

// ==================== 本地 TDK 抓取 ====================

/**
 * 本地抓取站点 TDK（标题/描述/关键词）
 * 直接请求目标站点 HTML，解析 <title> 和 meta 标签
 */
function fetchLocalTDK(string $url): array
{
    $result = ['title' => '', 'description' => '', 'keywords' => ''];

    // SSRF 防护
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    if (empty($host) || Security::isInternalHost($host)) {
        return $result;
    }

    // 尝试 http 和 https 两种协议
    $urlsToTry = [];
    if (preg_match('/^https?:\/\//i', $url)) {
        $urlsToTry[] = $url;
        // 如果原始是 https，也尝试 http（某些站点 http 更快）
        if (strpos($url, 'https://') === 0) {
            $urlsToTry[] = str_replace('https://', 'http://', $url);
        }
    } else {
        $urlsToTry[] = 'https://' . $url;
        $urlsToTry[] = 'http://' . $url;
    }

    $html = '';
    $lastError = '';
    
    foreach ($urlsToTry as $tryUrl) {
        // 重试 2 次
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $html = '';
            
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $tryUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
                    CURLOPT_ENCODING       => '', // 自动解压 gzip/deflate
                ]);
                $html = curl_exec($ch);
                $curlErr = curl_errno($ch);
                $curlErrMsg = curl_error($ch);
                curl_close($ch);
                
                if (!$html && $curlErr) {
                    $lastError = "curl:{$curlErr} {$curlErrMsg}";
                }
            } elseif (function_exists('file_get_contents')) {
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'header'  => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\n",
                        'follow_location' => 0,
                        'max_redirects' => 0,
                    ],
                    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);
                $html = @file_get_contents($tryUrl, false, $ctx);
            }
            
            if (!empty($html)) {
                break 2; // 抓取成功，跳出所有循环
            }
            
            // 第一次失败，等 0.5 秒重试
            if ($attempt === 1) {
                usleep(500000);
            }
        }
    }

    if (empty($html)) {
        Logger::log('wormhole_join', "TDK 抓取为空（{$url}），最后错误：{$lastError}");
        return $result;
    }

    // 记录 HTML 长度和前 200 字符摘要
    $headText = mb_substr(strip_tags($html), 0, 200);
    Logger::log('wormhole_join', "TDK 抓取成功（{$url}），HTML 长度 " . strlen($html));

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

    // 解析 title
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
        $result['title'] = trim(strip_tags($m[1]));
    }

    // 解析 meta description
    if (preg_match('/<meta[^>]+\s*name=["\']description["\'][^>]*content=["\']([^"\']*)/is', $html, $m)
        || preg_match('/<meta[^>]+\s*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $html, $m)
        || preg_match('/<meta[^>]+\s*property=["\']og:description["\'][^>]*content=["\']([^"\']*)/is', $html, $m)) {
        $result['description'] = trim($m[1]);
    }

    // 解析 meta keywords
    if (preg_match('/<meta[^>]+\s*name=["\']keywords["\'][^>]*content=["\']([^"\']*)/is', $html, $m)
        || preg_match('/<meta[^>]+\s*content=["\']([^"\']*)["\'][^>]*name=["\']keywords["\']/is', $html, $m)) {
        $result['keywords'] = trim($m[1]);
    }

    return $result;
}

/**
 * 获取站点元数据（TDK + 权重）
 * 供 update-meta 和自动加入共用
 */
function fetchMetaData(string $url): array
{
    $result = [
        'title'       => '',
        'description' => '',
        'keywords'    => '',
        'br_pc'       => 0,
        'br_mobile'   => 0,
        'br_360'      => 0,
        'br_shenma'   => 0,
    ];

    $apiKey = setting('api_key_5118', '');

    // 本地抓取 TDK
    $tdk = fetchLocalTDK($url);
    $result['title']       = $tdk['title']       ?? '';
    $result['description'] = $tdk['description'] ?? '';
    $result['keywords']    = $tdk['keywords']    ?? '';

    // 获取权重（与 api/rank.php 一致的调用方式）
    $domain = Security::extractDomain($url);
    $rankApiUrl = 'https://apis.5118.com/weight';
    $rankPostData = 'url=' . urlencode($domain);
    $rankResponse = null;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $rankApiUrl,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POSTFIELDS     => $rankPostData,
            CURLOPT_HTTPHEADER     => [
                'Authorization:' . $apiKey,
                'Content-Type:application/x-www-form-urlencoded; charset=UTF-8',
            ],
        ]);
        $rankResponse = curl_exec($ch);
        curl_close($ch);
    } elseif (function_exists('file_get_contents')) {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'timeout' => 8,
                'header'  => "Authorization: {$apiKey}\r\nContent-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n",
                'content' => $rankPostData,
            ],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $rankResponse = @file_get_contents($rankApiUrl, false, $ctx);
    }

    if ($rankResponse) {
        $rankRaw = json_decode($rankResponse, true);
        if (isset($rankRaw['errcode']) && (string)$rankRaw['errcode'] === '0' && !empty($rankRaw['data']['result'])) {
            $rankMap = [];
            $weightMap = [
                'BaiduPCWeight'     => 'br_pc',
                'BaiduMobileWeight' => 'br_mobile',
                'HaoSouWeight'      => 'br_360',
                'SMWeight'          => 'br_shenma',
            ];
            foreach ($rankRaw['data']['result'] as $r) {
                $type  = $r['type'] ?? '';
                $value = (int)($r['weight'] ?? 0);
                if (isset($weightMap[$type])) {
                    $rankMap[$weightMap[$type]] = $value;
                }
            }
            $result['br_pc']     = $rankMap['br_pc']     ?? 0;
            $result['br_mobile'] = $rankMap['br_mobile'] ?? 0;
            $result['br_360']    = $rankMap['br_360']    ?? 0;
            $result['br_shenma'] = $rankMap['br_shenma'] ?? 0;
        }
    }

    return $result;
}

// ==================== 辅助函数 ====================
// 注意：parseTags / getMaxBr / getDisplayDomain / renderSiteIcon /
// getWeightBadgeClass / formatNumber 已在 core/helpers.php 中统一定义，
// 此处不再重复声明，避免 "Cannot redeclare function" 致命错误。
// api/index.php 通过 require bootstrap.php 已加载上述函数。

/**
 * 格式化站点数据用于 API 输出
 */
function formatSite(array $site): array
{
    $tags = parseTags($site['tags'] ?? '[]');
    $maxBr = getMaxBr($site);

    return [
        'id'         => (int)$site['id'],
        'name'       => $site['name'],
        'url'        => $site['url'],
        'domain'     => getDisplayDomain($site['url']),
        'category_id' => (int)$site['category_id'],
        'category_slug' => $site['category_slug'] ?? '',
        'title'      => $site['name'] ?? '',
        'description'=> $site['description'] ?? '',
        'keywords'   => tagsToKeywords(parseTags($site['tags'] ?? '[]')),
        'br_pc'      => (int)($site['br_pc'] ?? 0),
        'br_mobile'  => (int)($site['br_mobile'] ?? 0),
        'br_360'     => (int)($site['br_360'] ?? 0),
        'br_shenma'  => (int)($site['br_shenma'] ?? 0),
        'max_br'     => $maxBr,
        'views'      => (int)($site['views'] ?? 0),
        'clicks'     => (int)($site['clicks'] ?? 0),
        'tags'       => $tags,
        'status'     => $site['status'] ?? 'published',
    ];
}

// ==================== 开放 API Handlers（需要 API Key） ====================

/**
 * GET /api/open/sites?category=all&page=1&limit=20&sort=views
 * 开放接口：获取站点列表
 */
function api_open_sites(): void
{
    $catSlug = Security::cleanString($_GET['category'] ?? 'all');
    $page    = max(1, min(100, (int)($_GET['page'] ?? 1)));
    $limit   = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $sort    = Security::cleanString($_GET['sort'] ?? 'newest');

    // 允许的排序字段
    $allowedSorts = ['views', 'clicks', 'br', 'newest', 'name'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'newest';
    }

    $catModel  = new CategoryModel();
    $siteModel = new SiteModel();
    $tbl = Database::table('sites');
    $catTbl = Database::table('categories');
    $offset = ($page - 1) * $limit;

    // 排序映射
    $orderBy = match ($sort) {
        'views'     => 's.views DESC, s.created_at DESC',
        'clicks'    => 's.clicks DESC, s.created_at DESC',
        'br'        => '(COALESCE(s.br_pc, 0) + COALESCE(s.br_mobile, 0)) DESC, s.created_at DESC',
        'name'      => 's.name ASC',
        'newest'    => 's.created_at DESC',
        default     => 's.created_at DESC',
    };

    $where = "WHERE s.status = 'published'";
    $params = [];

    if ($catSlug === 'all') {
        $category = ['id' => 0, 'name' => '全部分类', 'slug' => 'all'];
        $total = (int)Database::scalar("SELECT COUNT(*) FROM {$tbl} WHERE status = 'published'");
    } else {
        $category = $catModel->getBySlug($catSlug);
        if (!$category) {
            Security::jsonOutput([
                'success' => false,
                'code' => 40401,
                'message' => '分类不存在',
            ], 404);
        }
        $where .= " AND s.category_id = ?";
        $params[] = $category['id'];
        $total = $siteModel->countByCategory($category['id']);
    }

    $sql = "SELECT s.*, c.slug AS category_slug
            FROM {$tbl} s
            LEFT JOIN {$catTbl} c ON s.category_id = c.id
            {$where}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}";

    $sites = Database::query($sql, $params);
    $data = array_map('formatSite', $sites);

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => [
            'list' => $data,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total / $limit),
            'category' => [
                'id' => (int)($category['id'] ?? 0),
                'name' => $category['name'] ?? '',
                'slug' => $category['slug'] ?? $catSlug,
            ],
            'sort' => $sort,
        ],
    ]);
}

/**
 * GET /api/open/site?id={site_id}
 * 开放接口：获取站点详情
 */
function api_open_site_detail(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        Security::jsonOutput([
            'success' => false,
            'code' => 40001,
            'message' => '缺少站点ID参数',
        ], 400);
    }

    $siteModel = new SiteModel();
    $site = $siteModel->getSite($id);

    if (!$site || $site['status'] !== 'published') {
        Security::jsonOutput([
            'success' => false,
            'code' => 40401,
            'message' => '站点不存在或未发布',
        ], 404);
    }

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => formatSite($site),
    ]);
}

/**
 * GET /api/open/rank?type=views&limit=20
 * 开放接口：获取排行榜
 */
function api_open_rank(): void
{
    $type  = Security::cleanString($_GET['type'] ?? 'views');
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

    // 允许的排行榜类型
    $allowedTypes = ['views', 'clicks', 'br_pc', 'br_mobile', 'newest'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'views';
    }

    $siteModel = new SiteModel();
    $tbl = Database::table('sites');

    $orderBy = match ($type) {
        'views'     => 'views DESC',
        'clicks'    => 'clicks DESC',
        'br_pc'     => 'br_pc DESC, br_mobile DESC',
        'br_mobile' => 'br_mobile DESC, br_pc DESC',
        'newest'    => 'created_at DESC',
        default     => 'views DESC',
    };

    $sql = "SELECT s.*, c.slug as category_slug
            FROM {$tbl} s
            LEFT JOIN " . Database::table('categories') . " c ON s.category_id = c.id
            WHERE s.status = 'published'
            ORDER BY {$orderBy}
            LIMIT ?";

    $sites = Database::query($sql, [$limit]);
    $data = array_map('formatSite', $sites);

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => [
            'type' => $type,
            'list' => $data,
            'count' => count($data),
        ],
    ]);
}

/**
 * GET /api/open/categories
 * 开放接口：获取分类列表
 */
function api_open_categories(): void
{
    $catModel = new CategoryModel();
    // 默认返回前台展示分类；all=1 返回全部分类（含隐藏），便于管理端使用
    $all = (($_GET['all'] ?? '') === '1' || (($_GET['all'] ?? '') === 'true'));
    $categories = $all ? $catModel->getAll() : $catModel->getSidebarCategories();

    $data = array_map(function ($cat) {
        return [
            'id' => (int)$cat['id'],
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'icon' => $cat['icon'] ?? 'category',
            'sort_order' => (int)$cat['sort_order'],
            'show_count' => (int)($cat['show_count'] ?? 12),
            'is_show' => (bool)($cat['is_show'] ?? 1),
            'seo_title' => $cat['seo_title'] ?? '',
            'seo_desc' => $cat['seo_desc'] ?? '',
        ];
    }, $categories);

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => [
            'list' => $data,
            'total' => count($data),
        ],
    ]);
}

/**
 * GET /api/open/search?q=keyword&page=1&limit=20
 * 开放接口：搜索站点
 */
function api_open_search(): void
{
    $keyword = Security::cleanString($_GET['q'] ?? '');
    $page    = max(1, min(100, (int)($_GET['page'] ?? 1)));
    $limit   = max(1, min(100, (int)($_GET['limit'] ?? 20)));

    if (empty($keyword)) {
        Security::jsonOutput([
            'success' => false,
            'code' => 40001,
            'message' => '缺少搜索关键词',
        ], 400);
    }

    $siteModel = new SiteModel();
    $sites = $siteModel->searchPaged($keyword, $page, $limit);
    $total = $siteModel->searchCount($keyword);

    $data = array_map('formatSite', $sites);

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => [
            'keyword' => $keyword,
            'list' => $data,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

/**
 * GET /api/open/stats
 * 开放接口：获取站点统计信息
 */
function api_open_stats(): void
{
    $siteModel = new SiteModel();
    $catModel  = new CategoryModel();

    $tbl = Database::table('sites');
    $catTbl = Database::table('categories');

    // 基础统计
    $stats = Database::queryOne("SELECT
        COUNT(*) as total_sites,
        SUM(status = 'published') as published_sites,
        SUM(status = 'pending') as pending_sites,
        SUM(views) as total_views,
        SUM(clicks) as total_clicks,
        AVG(br_pc) as avg_br_pc,
        AVG(br_mobile) as avg_br_mobile
        FROM {$tbl}");

    // 分类数量
    $catCount = Database::scalar("SELECT COUNT(*) FROM {$catTbl} WHERE is_show = 1");

    // 最近7天新增站点
    $recentSites = Database::scalar(
        "SELECT COUNT(*) FROM {$tbl} WHERE status = 'published' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    Security::jsonOutput([
        'success' => true,
        'code' => 0,
        'message' => 'ok',
        'data' => [
            'total_sites' => (int)($stats['total_sites'] ?? 0),
            'published_sites' => (int)($stats['published_sites'] ?? 0),
            'pending_sites' => (int)($stats['pending_sites'] ?? 0),
            'total_views' => (int)($stats['total_views'] ?? 0),
            'total_clicks' => (int)($stats['total_clicks'] ?? 0),
            'avg_br_pc' => round((float)($stats['avg_br_pc'] ?? 0), 2),
            'avg_br_mobile' => round((float)($stats['avg_br_mobile'] ?? 0), 2),
            'category_count' => (int)$catCount,
            'recent_7days_sites' => (int)$recentSites,
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

// ==================== 开放 API 公共工具 ====================

/**
 * 读取 API 请求体（JSON 优先，兼容表单提交），带缓存避免重复解析
 */
function api_json_input(): array
{
    static $input = null;
    if ($input === null) {
        $raw     = file_get_contents('php://input');
        $decoded = $raw ? json_decode($raw, true) : null;
        $input   = is_array($decoded) ? $decoded : $_POST;
    }
    return $input;
}

/**
 * 标签入参统一转 JSON 数组字符串
 * 兼容：数组 / 逗号分隔字符串 / JSON 数组字符串
 */
function api_tags_to_json($tags): string
{
    $arr = [];
    if (is_array($tags)) {
        $arr = $tags;
    } elseif (is_string($tags) && $tags !== '') {
        $trimmed = trim($tags);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $arr = $decoded;
            }
        } else {
            $arr = explode(',', $trimmed);
        }
    }
    return json_encode(Security::cleanTags($arr), JSON_UNESCAPED_UNICODE);
}

/**
 * 权重字段清洗（0~10）
 */
function api_open_br_fields(array $input): array
{
    $out = [];
    foreach (['br_pc', 'br_mobile', 'br_360', 'br_shenma'] as $f) {
        $out[$f] = max(0, min(10, Security::int($input[$f] ?? 0)));
    }
    return $out;
}

/**
 * 开放接口写入日志（open_api 频道，后台日志设置中可开关）
 */
function api_open_log(string $message): void
{
    Logger::log('open_api', $message . ' IP=' . Security::getClientIP());
}

// ==================== 新增开放 API Handlers ====================

/**
 * GET /api/open/site/check?url=https://example.com
 * 查询一个网址是否已被收录及其审核状态
 */
function api_open_site_check(): void
{
    $url = Security::cleanString($_GET['url'] ?? '');
    if ($url === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少 url 参数'], 400);
    }
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    if (empty($host) || Security::isInternalHost($host)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '不允许的 URL'], 400);
    }

    // 同时匹配 www 与非 www 变体
    $rawHost  = strtolower(preg_replace('/:\d+$/', '', $host));
    $variants = [$rawHost];
    if (preg_match('/^www\./i', $rawHost)) {
        $variants[] = substr($rawHost, 4);
    } else {
        $variants[] = 'www.' . $rawHost;
    }

    $clauses = [];
    $params  = [];
    foreach ($variants as $v) {
        $clauses[] = 'url LIKE ?';
        $params[]  = '%//' . $v . '%';
    }

    $tbl  = Database::table('sites');
    $site = Database::queryOne(
        "SELECT id, name, url, category_id, description, status, views, clicks, created_at
         FROM {$tbl}
         WHERE (" . implode(' OR ', $clauses) . ")
           AND status IN ('published', 'pending')
         ORDER BY (status = 'published') DESC, id DESC
         LIMIT 1",
        $params
    );

    if (!$site) {
        Security::jsonOutput([
            'success' => true,
            'code'    => 0,
            'message' => 'ok',
            'data'    => ['found' => false, 'status' => null, 'status_text' => '未收录'],
        ]);
    }

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'found'       => true,
            'id'          => (int)$site['id'],
            'name'        => $site['name'],
            'url'         => $site['url'],
            'domain'      => getDisplayDomain($site['url']),
            'category_id' => (int)$site['category_id'],
            'description' => $site['description'] ?? '',
            'views'       => (int)$site['views'],
            'clicks'      => (int)$site['clicks'],
            'status'      => $site['status'],
            'status_text' => $site['status'] === 'published' ? '已收录' : '待审核',
            'created_at'  => $site['created_at'],
        ],
    ]);
}

/**
 * GET /api/open/site/related?id=1&limit=6
 * 获取与指定站点同分类的相关站点
 */
function api_open_related(): void
{
    $id    = (int)($_GET['id'] ?? 0);
    $limit = max(1, min(12, (int)($_GET['limit'] ?? 6)));
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少站点ID参数'], 400);
    }

    $siteModel = new SiteModel();
    $site      = $siteModel->getSite($id);
    if (!$site || $site['status'] !== 'published') {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '站点不存在或未发布'], 404);
    }

    $related = $siteModel->getRelatedSites((int)$site['category_id'], $id, $limit);
    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'site_id' => $id,
            'list'    => array_map('formatSite', $related),
            'count'   => count($related),
        ],
    ]);
}

/**
 * GET /api/open/featured?limit=12
 * 获取全局推荐站点
 */
function api_open_featured(): void
{
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 12)));
    $siteModel = new SiteModel();
    $items = $siteModel->getGlobalFeatured($limit);
    $ids   = array_column($items, 'id');
    $siteModel->incrementViewsBatch($ids);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'list'  => array_map('formatSite', $items),
            'count' => count($items),
        ],
    ]);
}

/**
 * GET /api/open/plugins
 * 获取插件列表与启用状态（供客户端判断插件接口是否可用）
 */
function api_open_plugins(): void
{
    $list = [];
    foreach (Plugin::scan() as $name => $info) {
        $list[] = [
            'name'         => $name,
            'title'        => $info['title'] ?? $name,
            'version'      => $info['version'] ?? '',
            'author'       => $info['author'] ?? '',
            'description'  => $info['description'] ?? '',
            'enabled'      => (bool)($info['enabled'] ?? false),
            'builtin'      => (bool)($info['builtin'] ?? true),
            'has_open_api' => is_file(Plugin::getDir($name) . '/api.php'),
        ];
    }

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => ['list' => $list, 'total' => count($list)],
    ]);
}

/**
 * POST /api/open/submit - 发布 / 提交站点（API Key 受信凭证，默认直接发布）
 */
function api_open_submit(): void
{
    $input  = api_json_input();
    $name   = Security::cleanString($input['name'] ?? '', 100);
    $url    = Security::safeUrl($input['url'] ?? '');
    $cat    = Security::int($input['category_id'] ?? 0);
    $tags   = api_tags_to_json($input['tags'] ?? '');
    $desc   = Security::cleanString($input['description'] ?? '', 200);
    $email  = Security::cleanString($input['email'] ?? '', 100);
    $status = Security::enum($input['status'] ?? '', ['published', 'pending'], 'published');
    $brs    = api_open_br_fields($input);

    if (empty($name) || empty($url)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称和网址不能为空'], 400);
    }
    if ($cat <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '请选择分类'], 400);
    }
    $catModel = new CategoryModel();
    if (!$catModel->getById($cat)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '分类不存在'], 400);
    }

    $siteModel = new SiteModel();
    $id = $siteModel->create([
        'name'        => $name,
        'url'         => $url,
        'category_id' => $cat,
        'description' => $desc,
        'tags'        => $tags,
        'status'      => $status,
        'submit_ip'   => Security::getClientIP(),
        'submit_email'=> $email,
        'br_pc'       => $brs['br_pc'],
        'br_mobile'   => $brs['br_mobile'],
        'br_360'      => $brs['br_360'],
        'br_shenma'   => $brs['br_shenma'],
    ]);

    Plugin::hook('site_submitted', [['id' => $id, 'name' => $name, 'url' => $url, 'category_id' => $cat, 'status' => $status, 'ip' => Security::getClientIP(), 'email' => $email]]);
    api_open_log("[开放API-发布] 站点ID={$id} 名称={$name} 状态={$status}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => $status === 'pending' ? '提交成功，等待审核' : '发布成功',
        'id'      => $id,
        'status'  => $status,
    ]);
}

/**
 * POST /api/open/site/update - 编辑站点（部分更新）
 */
function api_open_site_update(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少站点ID参数'], 400);
    }

    $siteModel = new SiteModel();
    $site = $siteModel->getSite($id);
    if (!$site) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '站点不存在'], 404);
    }

    $data = [];
    if (array_key_exists('name', $input)) {
        $name = Security::cleanString($input['name'] ?? '', 100);
        if ($name === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称不能为空'], 400);
        }
        $data['name'] = $name;
    }
    if (array_key_exists('url', $input)) {
        $url = Security::safeUrl($input['url'] ?? '');
        if ($url === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '网址不能为空'], 400);
        }
        $data['url'] = $url;
    }
    if (array_key_exists('category_id', $input)) {
        $cat = Security::int($input['category_id'] ?? 0);
        if ($cat <= 0 || !(new CategoryModel())->getById($cat)) {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '分类不存在'], 400);
        }
        $data['category_id'] = $cat;
    }
    if (array_key_exists('description', $input)) {
        $data['description'] = Security::cleanString($input['description'] ?? '', 200);
    }
    if (array_key_exists('tags', $input)) {
        $data['tags'] = api_tags_to_json($input['tags']);
    }
    if (array_key_exists('status', $input)) {
        $data['status'] = Security::enum($input['status'] ?? '', ['published', 'pending'], '');
        if ($data['status'] === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => 'status 仅支持 published / pending'], 400);
        }
    }
    if (array_key_exists('is_featured', $input)) {
        $data['is_featured'] = (int)$input['is_featured'] === 1 ? 1 : 0;
    }
    if (array_key_exists('sort_order', $input)) {
        $data['sort_order'] = max(0, Security::int($input['sort_order'] ?? 0));
    }
    foreach (['br_pc', 'br_mobile', 'br_360', 'br_shenma'] as $bf) {
        if (array_key_exists($bf, $input)) {
            $data[$bf] = max(0, min(10, Security::int($input[$bf] ?? 0)));
        }
    }

    if (empty($data)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '没有可更新的字段'], 400);
    }

    $siteModel->update($id, $data);
    api_open_log("[开放API-编辑] 站点ID={$id} 更新字段=" . implode(',', array_keys($data)));

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '更新成功',
        'id'      => $id,
        'updated' => array_keys($data),
    ]);
}

/**
 * POST /api/open/site/delete - 删除站点
 */
function api_open_site_delete(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少站点ID参数'], 400);
    }

    $siteModel = new SiteModel();
    if (!$siteModel->getSite($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '站点不存在'], 404);
    }

    $siteModel->delete($id);
    api_open_log("[开放API-删除] 站点ID={$id}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '删除成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/open/category/create - 新增分类
 */
function api_open_category_create(): void
{
    $input = api_json_input();
    $name  = Security::cleanString($input['name'] ?? '', 50);
    $slug  = Security::validateSlug($input['slug'] ?? '');
    if ($name === '' || $slug === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称和 slug 不能为空（slug 仅含小写字母、数字、中划线）'], 400);
    }

    $catModel = new CategoryModel();
    if ($catModel->getBySlug($slug)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => 'slug 已存在，请更换'], 400);
    }

    $icon = Security::cleanString($input['icon'] ?? '', 50);
    if ($icon !== '' && !preg_match('/^[a-z0-9\-]+$/', $icon)) {
        $icon = '';
    }

    $id = $catModel->create([
        'name'        => $name,
        'slug'        => $slug,
        'icon'        => $icon !== '' ? $icon : 'category',
        'sort_order'  => max(0, Security::int($input['sort_order'] ?? 0)),
        'show_count'  => max(1, min(50, Security::int($input['show_count'] ?? 12))),
        'is_show'     => isset($input['is_show']) ? ((int)$input['is_show'] === 1 ? 1 : 0) : 1,
        'seo_title'   => Security::cleanString($input['seo_title'] ?? '', 200),
        'seo_desc'    => Security::cleanString($input['seo_desc'] ?? '', 200),
        'fill_sort'   => Security::enum($input['fill_sort'] ?? '', ['newest', 'views', 'br'], 'newest'),
    ]);
    api_open_log("[开放API-新增分类] 分类ID={$id} 名称={$name} slug={$slug}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '分类创建成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/open/category/update - 编辑分类（部分更新）
 */
function api_open_category_update(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少分类ID参数'], 400);
    }

    $catModel = new CategoryModel();
    if (!$catModel->getById($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '分类不存在'], 404);
    }

    $data = [];
    if (array_key_exists('name', $input)) {
        $name = Security::cleanString($input['name'] ?? '', 50);
        if ($name === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称不能为空'], 400);
        }
        $data['name'] = $name;
    }
    if (array_key_exists('slug', $input)) {
        $slug = Security::validateSlug($input['slug'] ?? '');
        if ($slug === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => 'slug 仅含小写字母、数字、中划线'], 400);
        }
        $existing = $catModel->getBySlug($slug);
        if ($existing && (int)$existing['id'] !== $id) {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => 'slug 已存在，请更换'], 400);
        }
        $data['slug'] = $slug;
    }
    if (array_key_exists('icon', $input)) {
        $icon = Security::cleanString($input['icon'] ?? '', 50);
        if ($icon !== '' && !preg_match('/^[a-z0-9\-]+$/', $icon)) {
            $icon = '';
        }
        $data['icon'] = $icon !== '' ? $icon : 'category';
    }
    if (array_key_exists('sort_order', $input)) {
        $data['sort_order'] = max(0, Security::int($input['sort_order'] ?? 0));
    }
    if (array_key_exists('show_count', $input)) {
        $data['show_count'] = max(1, min(50, Security::int($input['show_count'] ?? 12)));
    }
    if (array_key_exists('is_show', $input)) {
        $data['is_show'] = (int)$input['is_show'] === 1 ? 1 : 0;
    }
    if (array_key_exists('seo_title', $input)) {
        $data['seo_title'] = Security::cleanString($input['seo_title'] ?? '', 200);
    }
    if (array_key_exists('seo_desc', $input)) {
        $data['seo_desc'] = Security::cleanString($input['seo_desc'] ?? '', 200);
    }
    if (array_key_exists('fill_sort', $input)) {
        $data['fill_sort'] = Security::enum($input['fill_sort'] ?? '', ['newest', 'views', 'br'], 'newest');
    }

    if (empty($data)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '没有可更新的字段'], 400);
    }

    $catModel->update($id, $data);
    api_open_log("[开放API-编辑分类] 分类ID={$id} 更新字段=" . implode(',', array_keys($data)));

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '更新成功',
        'id'      => $id,
        'updated' => array_keys($data),
    ]);
}

/**
 * POST /api/open/category/delete - 删除分类（分类下仍有站点时拒绝）
 */
function api_open_category_delete(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少分类ID参数'], 400);
    }

    $catModel = new CategoryModel();
    if (!$catModel->getById($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '分类不存在'], 404);
    }

    $count = (int)Database::scalar(
        "SELECT COUNT(*) FROM " . Database::table('sites') . " WHERE category_id = ?",
        [$id]
    );
    if ($count > 0) {
        Security::jsonOutput([
            'success' => false,
            'code'    => 40901,
            'message' => "分类下仍有 {$count} 个站点，无法删除，请先迁移或删除站点",
        ], 409);
    }

    $catModel->delete($id);
    api_open_log("[开放API-删除分类] 分类ID={$id}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '删除成功',
        'id'      => $id,
    ]);
}
