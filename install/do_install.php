<?php
/**
 * 执行安装：建表 + 导入默认数据 + 生成配置文件
 */

// 安全：仅在安装上下文中执行
if (!defined('INSTALL_CONTEXT')) {
    define('INSTALL_CONTEXT', true);
}

session_start();

// 安装锁检查：已安装则拒绝执行
$lockFile = __DIR__ . '/../install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('系统已安装，拒绝重复安装');
}

// CSRF 验证
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['install_csrf'] ?? '', $csrfToken)) {
    die('CSRF 校验失败');
}

// 获取并清洗输入
$dbHost   = trim($_POST['db_host'] ?? '127.0.0.1');
$dbPort   = (int)($_POST['db_port'] ?? 3306);
$dbName   = trim($_POST['db_name'] ?? '');
$dbUser   = trim($_POST['db_user'] ?? '');
$dbPass   = $_POST['db_pass'] ?? '';
$dbPrefix = trim($_POST['db_prefix'] ?? 'nav_');

$adminUser  = trim($_POST['admin_user'] ?? '');
$adminPass  = $_POST['admin_pass'] ?? '';
$adminEmail = trim($_POST['admin_email'] ?? '');

// 输入验证
$errors = [];

if (empty($dbName)) $errors[] = '数据库名不能为空';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) $errors[] = '数据库名只能包含字母、数字和下划线';
if (empty($dbUser)) $errors[] = '数据库用户名不能为空';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbPrefix)) $errors[] = '表前缀只能包含字母、数字和下划线';
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $adminUser)) $errors[] = '管理员用户名需 3-20 位字母数字下划线';
if (strlen($adminPass) < 8) $errors[] = '管理员密码至少 8 位';
if (!preg_match('/[a-zA-Z]/', $adminPass) || !preg_match('/[0-9]/', $adminPass)) $errors[] = '管理员密码需包含字母和数字';
if ($adminEmail && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = '邮箱格式不正确';

if (!empty($errors)) {
    $msg = implode('; ', $errors);
    echo "<div style='text-align:center;padding:60px;font-family:sans-serif;'>";
    echo "<h2 style='color:#ef4444'>安装失败</h2>";
    echo "<p style='color:#888'>$msg</p>";
    echo "<a href='?step=2' style='color:#667eea'>&#8592; 返回修正</a>";
    echo "</div>";
    exit;
}

// 1. 测试数据库连接
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 尝试创建数据库
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
} catch (PDOException $e) {
    $errMsg = $e->getMessage();
    echo "<div style='text-align:center;padding:60px;font-family:sans-serif;'>";
    echo "<h2 style='color:#ef4444'>数据库连接失败</h2>";
    echo "<p style='color:#888'>" . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='?step=2' style='color:#667eea'>&#8592; 返回修正</a>";
    echo "</div>";
    exit;
}

// 2. 建表
$tables = getTableSQL($dbPrefix);

try {
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    echo "<div style='text-align:center;padding:60px;font-family:sans-serif;'>";
    echo "<h2 style='color:#ef4444'>建表失败</h2>";
    echo "<p style='color:#888'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</div>";
    exit;
}

// 3. 导入默认分类数据
$defaultCategories = [
    ['ai', 'AI工具', 'robot', 1],
    ['video', '视频站', 'device-tv', 2],
    ['music', '音乐站', 'music', 3],
    ['novel', '小说站', 'book', 4],
    ['comic', '漫画站', 'books', 5],
    ['anime', '动漫站', 'device-tv-old', 6],
    ['game', '游戏站', 'device-gamepad-2', 7],
    ['wallpaper', '壁纸站', 'photo', 8],
    ['tool', '工具站', 'tool', 9],
    ['cloud', '云服务', 'cloud', 10],
    ['blog', '博客站', 'rss', 11],
    ['forum', '论坛站', 'message-circle', 12],
];

$catTbl = $dbPrefix . 'categories';
$catStmt = $pdo->prepare("INSERT INTO `{$catTbl}` (slug, name, icon, sort_order, show_count, is_show, fill_sort) VALUES (?, ?, ?, ?, 12, 1, 'newest')");
foreach ($defaultCategories as $cat) {
    $catStmt->execute($cat);
}

// 4. 导入默认设置（仅核心设置，插件配置在各插件启用时写入）
$settingsTbl = $dbPrefix . 'settings';
$defaultSettings = [
    'site_name'           => '',
    'site_slogan'         => '',
    'site_logo'           => '',
    'site_footer'         => '',
    'home_featured_count' => '6',
    'home_category_count' => '11',
    'home_per_category'   => '12',
    'seo_title'           => '',
    'seo_keywords'        => '',
    'seo_description'     => '',
    'enable_submit'       => '1',
    'need_review'         => '1',
    'show_weight'         => '1',
    'admin_email'         => $adminEmail,
    'api_key_5118'        => '',
    'site_url'            => '',
    'debug_mode'          => '0',
    'default_per_page'    => '12',
    // 主题默认配置（伪静态 URL 格式由 Rewrite 核心类提供默认值，rewrite 插件启用时写入数据库）
    'current_theme'       => 'default',
    // 安全设置
    'session_timeout'          => '3600',
    'enable_captcha'           => '0',
    // 所有插件默认关闭，启用时由 Plugin::ensureSchema 自动安装表、字段和配置
    'plugin_ad_enabled'        => '0',
    'plugin_article_enabled'   => '0',
    'plugin_lightbox_enabled'  => '0',
    'plugin_auto-alt_enabled'  => '0',
    'plugin_auto-link_enabled' => '0',
    'plugin_wormhole_enabled'  => '0',
    'plugin_submit_enabled'    => '0',
    'plugin_sitemap_enabled'   => '0',
    'plugin_rewrite_enabled'   => '0',
    'plugin_spider_enabled'    => '0',
    'plugin_friendlink_enabled' => '0',
];

$settingStmt = $pdo->prepare("INSERT INTO `{$settingsTbl}` (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaultSettings as $k => $v) {
    $settingStmt->execute([$k, $v]);
}

// 5. 创建管理员账号
$adminTbl = $dbPrefix . 'admins';
$passHash = password_hash($adminPass, PASSWORD_BCRYPT);

$adminStmt = $pdo->prepare("INSERT INTO `{$adminTbl}` (username, password_hash, email, status) VALUES (?, ?, ?, 1)");
$adminStmt->execute([$adminUser, $passHash, $adminEmail]);

// 6. 生成配置文件（根目录）
// 计算网站URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$parsedHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$siteUrl = $protocol . '://' . $parsedHost;

$configContent = generateConfigFile($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbPrefix, $siteUrl);

$configPath = __DIR__ . '/../config.php';
file_put_contents($configPath, $configContent);

// 7. 生成 .htaccess 保护配置文件（兼容旧config目录）
$configDir = __DIR__ . '/../config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}
$htaccessPath = $configDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "Order deny,allow\nDeny from all\n<Files ~ \"\\.php$\">\nOrder deny,allow\nDeny from all\n</Files>");
}

// 8. 生成安装锁（默认放在根目录）
file_put_contents($lockFile, date('Y-m-d H:i:s'));

// 9. 清除安装 session
unset($_SESSION['install_csrf']);

// 跳转到成功页面
header('Location: ?step=3');
exit;

/**
 * 生成配置文件内容
 */
function generateConfigFile(string $host, int $port, string $name, string $user, string $pass, string $prefix, string $siteUrl): string
{
    // 转义密码中的特殊字符（用于 PHP 单引号字符串）
    $pass = str_replace('\\', '\\\\', $pass);
    $pass = str_replace("'", "\\'", $pass);
    $time = date('Y-m-d H:i:s');

    return <<<PHP
<?php
/**
 * 懒人导航 - 主配置文件
 * 安装后请检查各项配置是否正确
 */

// 网站URL（末尾不带斜杠）
define('SITE_URL', '{$siteUrl}');

// 数据库配置
define('DB_HOST', '{$host}');
define('DB_PORT', {$port});
define('DB_NAME', '{$name}');
define('DB_USER', '{$user}');
define('DB_PASS', '{$pass}');
define('DB_PREFIX', '{$prefix}');

// 调试模式：config.php默认为false，后台可控制。
// 如需强制开启调试（后台也进不去时），手动取消下面注释：
// define('APP_DEBUG', true);

// 其他配置
define('TRUST_PROXY', false); // 是否信任代理头（CDN/负载均衡后设为 true）

// 程序更新
define('UPDATE_SERVER', 'https://site.ikunwl.com/ikun_nav.php');
PHP;
}

/**
 * 获取建表 SQL
 */
function getTableSQL(string $prefix): array
{
    return [
        // 站点表
        "CREATE TABLE IF NOT EXISTS `{$prefix}sites` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL COMMENT '站点名称',
            url VARCHAR(500) NOT NULL COMMENT '站点网址',
            category_id INT NOT NULL COMMENT '所属分类ID',
            description TEXT COMMENT '站点描述',
            br_pc INT DEFAULT 0 COMMENT '百度PC权重',
            br_mobile INT DEFAULT 0 COMMENT '百度移动权重',
            br_360 INT DEFAULT 0 COMMENT '360权重',
            br_shenma INT DEFAULT 0 COMMENT '神马权重',
            tags JSON COMMENT '标签数组',
            is_featured TINYINT DEFAULT 0 COMMENT '是否推荐',
            sort_order INT DEFAULT 0 COMMENT '排序权重',
            views INT DEFAULT 0 COMMENT '浏览量',
            clicks INT DEFAULT 0 COMMENT '点击量（兼容旧统计）',
            clicks_in INT DEFAULT 0 COMMENT '点入统计（排行榜用）',
            clicks_out INT DEFAULT 0 COMMENT '点出统计（排行榜用）',
            status ENUM('published','pending','rejected','offline') DEFAULT 'pending' COMMENT '状态',
            submit_ip VARCHAR(50) DEFAULT '' COMMENT '提交者IP',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category_id),
            INDEX idx_status (status),
            INDEX idx_featured (is_featured),
            INDEX idx_views (views),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 分类表
        "CREATE TABLE IF NOT EXISTS `{$prefix}categories` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL COMMENT '分类名称',
            slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'URL标识',
            icon VARCHAR(50) DEFAULT 'category' COMMENT '图标',
            sort_order INT DEFAULT 0 COMMENT '排序',
            show_count INT DEFAULT 12 COMMENT '首页展示数量上限',
            is_show TINYINT DEFAULT 1 COMMENT '是否显示在侧边栏',
            seo_title VARCHAR(200) DEFAULT '' COMMENT 'SEO标题',
            seo_desc TEXT COMMENT '分类描述',
            fill_sort ENUM('newest','views','br') DEFAULT 'newest' COMMENT '补位排序方式'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 推荐关系表
        "CREATE TABLE IF NOT EXISTS `{$prefix}site_features` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_id INT NOT NULL COMMENT '站点ID',
            category_id INT NOT NULL COMMENT '分类ID',
            feature_order INT DEFAULT 0 COMMENT '推荐排序',
            is_excluded TINYINT DEFAULT 0 COMMENT '是否排除',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_cat (site_id, category_id),
            INDEX idx_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 设置表
        "CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 管理员表
        "CREATE TABLE IF NOT EXISTS `{$prefix}admins` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
            password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
            email VARCHAR(100) DEFAULT '' COMMENT '邮箱',
            status TINYINT DEFAULT 1 COMMENT '状态(1=正常,0=禁用)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 站点评分表
        "CREATE TABLE IF NOT EXISTS `{$prefix}site_ratings` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_id INT NOT NULL COMMENT '站点ID',
            rating TINYINT NOT NULL COMMENT '评分1-5',
            ip VARCHAR(50) DEFAULT '' COMMENT '评分者IP',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_ip (site_id, ip),
            INDEX idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 已删除站点ID回收队列（用于创建时优先复用空缺ID）
        "CREATE TABLE IF NOT EXISTS `{$prefix}deleted_ids` (
            id INT PRIMARY KEY,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 站点反馈表
        "CREATE TABLE IF NOT EXISTS `{$prefix}site_feedback` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_id INT NOT NULL COMMENT '站点ID',
            type ENUM('url_change','broken','error','other') DEFAULT 'other' COMMENT '反馈类型',
            content TEXT NOT NULL COMMENT '反馈内容',
            email VARCHAR(100) DEFAULT '' COMMENT '提交者邮箱',
            ip VARCHAR(50) DEFAULT '' COMMENT '提交者IP',
            status ENUM('pending','resolved','ignored') DEFAULT 'pending' COMMENT '处理状态',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site (site_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 黑名单表（由 wormhole / auto-link 插件启用时创建，不在核心安装时创建）

        // 站点日统计表（趋势图数据源）
        "CREATE TABLE IF NOT EXISTS `{$prefix}site_daily_stats` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_id INT NOT NULL COMMENT '站点ID',
            stat_date DATE NOT NULL COMMENT '统计日期',
            views INT DEFAULT 0 COMMENT '当天浏览量',
            clicks INT DEFAULT 0 COMMENT '当天点击量',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_date (site_id, stat_date),
            INDEX idx_site_id (site_id),
            INDEX idx_stat_date (stat_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点日统计表';",

        // 文章表（由 article 插件启用时创建，不在核心安装时创建）
    ];
}
