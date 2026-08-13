<?php
/**
 * 懒人导航 V2 数据库结构检测与修复脚本
 *
 * 功能：
 * 1. 每次访问先显示确认页面，让用户选择检测项目
 * 2. 数据库检测：核心表/字段/索引 + 已启用插件的表/字段
 * 3. 本地文件检测：扫描冗余文件、临时文件、垃圾文件、废弃后台文件
 * 4. 逐行展示检测结果，通过打勾，有问题显示详情
 * 5. 最后显示汇总统计
 *
 * 使用方式：浏览器访问 /install/upgrade_v2.php
 * 安全：仅限已登录管理员执行
 */

require_once __DIR__ . '/../core/bootstrap.php';

// 未安装则跳转
if (!isInstalled()) {
    header('Location: /install/');
    exit;
}

// 校验管理员登录状态
$needLogin = !isset($_SESSION['admin_id']) || empty($_SESSION['admin_id']);

// CSRF 防护
Security::initSession();
if (empty($_SESSION['csrf_token'])) {
    Security::generateCSRFToken();
}

// POST 请求需要 CSRF 校验
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        die('CSRF 校验失败，请刷新页面重试');
    }
}

$prefix = DB_PREFIX;

// ========== 定义期望的完整数据库结构 ==========
$expectedSchema = [

    // 1. sites 站点主表
    'sites' => [
        'comment' => '站点主表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'name' => ['type' => 'VARCHAR(100)', 'null' => 'NO', 'default' => "''", 'comment' => '站点名称'],
            'url' => ['type' => 'VARCHAR(500)', 'null' => 'NO', 'default' => "''", 'comment' => '站点网址'],
            'category_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '所属分类ID'],
            'description' => ['type' => 'TEXT', 'null' => 'YES', 'default' => 'NULL', 'comment' => '站点描述'],
            'br_pc' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '百度PC权重'],
            'br_mobile' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '百度移动权重'],
            'br_360' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '360权重'],
            'br_shenma' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '神马权重'],
            'tags' => ['type' => 'JSON', 'null' => 'YES', 'default' => 'NULL', 'comment' => '标签数组'],
            'is_featured' => ['type' => 'TINYINT', 'null' => 'YES', 'default' => '0', 'comment' => '是否全局推荐'],
            'sort_order' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '排序权重'],
            'views' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '浏览量'],
            'clicks' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '点击量(兼容旧统计)'],
            'clicks_in' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '点入统计(排行榜用)'],
            'clicks_out' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '点出统计(排行榜用)'],
            'status' => ['type' => "ENUM('published','pending','rejected','offline')", 'null' => 'YES', 'default' => "'pending'", 'comment' => '状态'],
            'submit_ip' => ['type' => 'VARCHAR(50)', 'null' => 'YES', 'default' => "''", 'comment' => '提交者IP'],
            'wormhole_status' => ['type' => "ENUM('none','manual','auto','pending','broken')", 'null' => 'YES', 'default' => "'none'", 'comment' => '虫洞联盟状态'],
            'wormhole_joined_at' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '加入联盟时间'],
            'wormhole_last_check' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '上次检测时间'],
            'wormhole_check_fail' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '连续检测失败次数'],
            'wormhole_source_domain' => ['type' => 'VARCHAR(200)', 'null' => 'YES', 'default' => "''", 'comment' => '检测来源域名'],
            'wormhole_quality_score' => ['type' => 'DECIMAL(5,2)', 'null' => 'YES', 'default' => '0.00', 'comment' => '虫洞质量评分(0-100)'],
            'wormhole_click_in' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '虫洞点入次数(回流)'],
            'wormhole_click_out' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '虫洞点出次数(送出)'],
            'wormhole_last_content_update' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '站点内容上次更新时间'],
            'wormhole_quality_updated_at' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '质量评分上次更新时间'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [
            ['name' => 'idx_category', 'columns' => ['category_id']],
            ['name' => 'idx_status', 'columns' => ['status']],
            ['name' => 'idx_featured', 'columns' => ['is_featured']],
            ['name' => 'idx_views', 'columns' => ['views']],
            ['name' => 'idx_created', 'columns' => ['created_at']],
            ['name' => 'idx_wormhole_status', 'columns' => ['wormhole_status']],
            ['name' => 'idx_wormhole_quality', 'columns' => ['wormhole_quality_score']],
        ],
    ],

    // 12. api_keys API密钥表
    'api_keys' => [
        'comment' => 'API开放接口密钥表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'api_key' => ['type' => 'VARCHAR(64)', 'null' => 'NO', 'key' => 'UNI', 'default' => "''", 'comment' => 'API Key'],
            'api_secret' => ['type' => 'VARCHAR(128)', 'null' => 'YES', 'default' => "''", 'comment' => 'API Secret(用于签名验证)'],
            'name' => ['type' => 'VARCHAR(100)', 'null' => 'NO', 'default' => "''", 'comment' => '密钥名称/备注'],
            'status' => ['type' => 'TINYINT', 'null' => 'NO', 'default' => '1', 'comment' => '状态(1=启用,0=禁用)'],
            'rate_limit_per_minute' => ['type' => 'INT', 'null' => 'NO', 'default' => '60', 'comment' => '每分钟调用限制'],
            'rate_limit_per_hour' => ['type' => 'INT', 'null' => 'NO', 'default' => '1000', 'comment' => '每小时调用限制'],
            'rate_limit_per_day' => ['type' => 'INT', 'null' => 'NO', 'default' => '10000', 'comment' => '每天调用限制'],
            'call_count' => ['type' => 'BIGINT', 'null' => 'NO', 'default' => '0', 'comment' => '总调用次数'],
            'last_call_at' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '上次调用时间'],
            'expires_at' => ['type' => 'TIMESTAMP', 'null' => 'YES', 'default' => 'NULL', 'comment' => '过期时间(NULL=永不过期)'],
            'created_by' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '创建者管理员ID'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [
            ['name' => 'uk_api_key', 'columns' => ['api_key'], 'unique' => true],
            ['name' => 'idx_status', 'columns' => ['status']],
        ],
    ],

    // 13. api_rate_limit API调用限流表
    'api_rate_limit' => [
        'comment' => 'API调用限流记录表',
        'fields' => [
            'id' => ['type' => 'BIGINT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'api_key' => ['type' => 'VARCHAR(64)', 'null' => 'NO', 'default' => "''", 'comment' => 'API Key(或IP)'],
            'period' => ['type' => "ENUM('minute','hour','day')", 'null' => 'NO', 'comment' => '统计周期'],
            'period_key' => ['type' => 'VARCHAR(20)', 'null' => 'NO', 'default' => "''", 'comment' => '周期键值(如202401011200)'],
            'call_count' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '调用次数'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [
            ['name' => 'uk_key_period', 'columns' => ['api_key', 'period', 'period_key'], 'unique' => true],
            ['name' => 'idx_api_key', 'columns' => ['api_key']],
            ['name' => 'idx_period_key', 'columns' => ['period_key']],
        ],
    ],

    // 2. categories 分类表
    'categories' => [
        'comment' => '分类表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'name' => ['type' => 'VARCHAR(50)', 'null' => 'NO', 'default' => "''", 'comment' => '分类名称'],
            'slug' => ['type' => 'VARCHAR(50)', 'null' => 'NO', 'key' => 'UNI', 'default' => "''", 'comment' => 'URL标识'],
            'icon' => ['type' => 'VARCHAR(50)', 'null' => 'YES', 'default' => "'category'", 'comment' => '图标'],
            'sort_order' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '排序'],
            'show_count' => ['type' => 'INT', 'null' => 'YES', 'default' => '12', 'comment' => '首页展示数量上限'],
            'is_show' => ['type' => 'TINYINT', 'null' => 'YES', 'default' => '1', 'comment' => '是否显示在侧边栏'],
            'seo_title' => ['type' => 'VARCHAR(200)', 'null' => 'YES', 'default' => "''", 'comment' => 'SEO标题'],
            'seo_desc' => ['type' => 'TEXT', 'null' => 'YES', 'default' => 'NULL', 'comment' => '分类描述/SEO描述'],
            'fill_sort' => ['type' => "ENUM('newest','views','br')", 'null' => 'YES', 'default' => "'newest'", 'comment' => '补位排序方式'],
        ],
        'indexes' => [],
    ],

    // 3. site_features 推荐关系表
    'site_features' => [
        'comment' => '推荐关系表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'site_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '站点ID'],
            'category_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '分类ID(0=全局推荐)'],
            'feature_order' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '推荐排序'],
            'is_excluded' => ['type' => 'TINYINT', 'null' => 'YES', 'default' => '0', 'comment' => '是否排除'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
        ],
        'indexes' => [
            ['name' => 'uk_site_cat', 'columns' => ['site_id', 'category_id'], 'unique' => true],
            ['name' => 'idx_category', 'columns' => ['category_id']],
        ],
    ],

    // 4. settings 设置表
    'settings' => [
        'comment' => '系统配置表',
        'fields' => [
            'setting_key' => ['type' => 'VARCHAR(100)', 'null' => 'NO', 'key' => 'PRI', 'default' => "''", 'comment' => '配置键名'],
            'setting_value' => ['type' => 'TEXT', 'null' => 'YES', 'default' => 'NULL', 'comment' => '配置值'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [],
    ],

    // 5. admins 管理员表
    'admins' => [
        'comment' => '管理员账号表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'username' => ['type' => 'VARCHAR(50)', 'null' => 'NO', 'key' => 'UNI', 'default' => "''", 'comment' => '用户名'],
            'password_hash' => ['type' => 'VARCHAR(255)', 'null' => 'NO', 'default' => "''", 'comment' => '密码哈希'],
            'email' => ['type' => 'VARCHAR(100)', 'null' => 'YES', 'default' => "''", 'comment' => '邮箱'],
            'status' => ['type' => 'TINYINT', 'null' => 'YES', 'default' => '1', 'comment' => '状态(1=正常,0=禁用)'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [],
    ],

    // 6. site_ratings 评分表
    'site_ratings' => [
        'comment' => '用户评分记录表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'site_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '站点ID'],
            'rating' => ['type' => 'TINYINT', 'null' => 'NO', 'default' => '0', 'comment' => '评分1-5'],
            'ip' => ['type' => 'VARCHAR(50)', 'null' => 'YES', 'default' => "''", 'comment' => '评分者IP'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '评分时间'],
        ],
        'indexes' => [
            ['name' => 'uk_site_ip', 'columns' => ['site_id', 'ip'], 'unique' => true],
            ['name' => 'idx_site', 'columns' => ['site_id']],
        ],
    ],

    // 7. deleted_ids ID回收队列
    'deleted_ids' => [
        'comment' => '已删除站点ID回收队列',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'comment' => '被删除的站点ID'],
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '删除时间'],
        ],
        'indexes' => [],
    ],

    // 8. site_feedback 站点反馈表
    'site_feedback' => [
        'comment' => '站点问题反馈表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'site_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '站点ID'],
            'type' => ['type' => "ENUM('url_change','broken','error','other')", 'null' => 'YES', 'default' => "'other'", 'comment' => '反馈类型'],
            'content' => ['type' => 'TEXT', 'null' => 'NO', 'comment' => '反馈内容'],
            'email' => ['type' => 'VARCHAR(100)', 'null' => 'YES', 'default' => "''", 'comment' => '提交者邮箱'],
            'ip' => ['type' => 'VARCHAR(50)', 'null' => 'YES', 'default' => "''", 'comment' => '提交者IP'],
            'status' => ['type' => "ENUM('pending','resolved','ignored')", 'null' => 'YES', 'default' => "'pending'", 'comment' => '处理状态'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '提交时间'],
        ],
        'indexes' => [
            ['name' => 'idx_site', 'columns' => ['site_id']],
            ['name' => 'idx_status', 'columns' => ['status']],
        ],
    ],

    // 9. site_daily_stats 站点日统计表
    'site_daily_stats' => [
        'comment' => '站点日统计表(趋势图数据源)',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'site_id' => ['type' => 'INT', 'null' => 'NO', 'default' => '0', 'comment' => '站点ID'],
            'stat_date' => ['type' => 'DATE', 'null' => 'NO', 'default' => "'1970-01-01'", 'comment' => '统计日期'],
            'views' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '当天浏览量'],
            'clicks' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '当天点击量'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update CURRENT_TIMESTAMP', 'comment' => '更新时间'],
        ],
        'indexes' => [
            ['name' => 'uk_site_date', 'columns' => ['site_id', 'stat_date'], 'unique' => true],
            ['name' => 'idx_site_id', 'columns' => ['site_id']],
            ['name' => 'idx_stat_date', 'columns' => ['stat_date']],
        ],
    ],

    // 10. spider_visits 蜘蛛来访记录表（spider插件）
    'spider_visits' => [
        'comment' => '蜘蛛来访记录表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'spider_type' => ['type' => 'VARCHAR(30)', 'null' => 'NO', 'comment' => '蜘蛛类型(baidu/google/bing/sogou/360/yandex/bytespider)'],
            'url' => ['type' => 'VARCHAR(500)', 'null' => 'NO', 'comment' => '访问URL路径'],
            'ip' => ['type' => 'VARCHAR(45)', 'null' => 'YES', 'default' => "''", 'comment' => 'IP地址'],
            'user_agent' => ['type' => 'VARCHAR(500)', 'null' => 'YES', 'default' => "''", 'comment' => 'User-Agent'],
            'visited_at' => ['type' => 'DATETIME', 'null' => 'NO', 'comment' => '访问时间'],
        ],
        'indexes' => [
            ['name' => 'idx_spider_type', 'columns' => ['spider_type']],
            ['name' => 'idx_visited_at', 'columns' => ['visited_at']],
            ['name' => 'idx_type_date', 'columns' => ['spider_type', 'visited_at']],
        ],
    ],

    // 11. friendlinks 友情链接表（friendlink插件）
    'friendlinks' => [
        'comment' => '友情链接表',
        'fields' => [
            'id' => ['type' => 'INT', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment', 'comment' => '主键自增'],
            'name' => ['type' => 'VARCHAR(100)', 'null' => 'NO', 'comment' => '友链名称'],
            'url' => ['type' => 'VARCHAR(500)', 'null' => 'NO', 'comment' => '友链链接'],
            'css_class' => ['type' => 'VARCHAR(200)', 'null' => 'YES', 'default' => "''", 'comment' => '自定义CSS类名(填写则输出,不填则不输出)'],
            'icon' => ['type' => 'VARCHAR(500)', 'null' => 'YES', 'default' => "''", 'comment' => '图标URL或Tabler图标类名(填写则显示,不填则不显示)'],
            'sort_order' => ['type' => 'INT', 'null' => 'YES', 'default' => '0', 'comment' => '排序(越小越靠前)'],
            'status' => ['type' => 'TINYINT', 'null' => 'YES', 'default' => '1', 'comment' => '状态(1=显示,0=隐藏)'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'],
        ],
        'indexes' => [
            ['name' => 'idx_status', 'columns' => ['status']],
            ['name' => 'idx_sort', 'columns' => ['sort_order']],
        ],
    ],
];

// 废弃的表（检测到会删除）
$abandonedTables = ['admin_logs'];

// 废弃的字段（只删除这些已知废弃字段，不删除任何其他字段）
// 修复前版本会删除"不在 expectedSchema 中"的所有字段，这会导致用户自定义插件字段被误删
// 现在改为白名单模式：只有明确声明为废弃的字段才会被删除
$abandonedFields = [];

// 期望的根目录文件
$expectedRootFiles = ['config.php', 'index.php', 'go.php', 'ikun_nav.php', 'install.lock', '.gitignore', 'README.md'];

// 期望的根目录子目录
$expectedRootDirs = ['admin', 'api', 'assets', 'core', 'data', 'install', 'plugins', 'templates', 'wormhole', '.git', '.dumate', 'node_modules'];

// 垃圾文件扩展名
$junkExtensions = ['bak', 'tmp', 'swp', 'old', 'orig', 'log'];

// 垃圾系统文件名
$junkFileNames = ['.DS_Store', 'Thumbs.db', 'desktop.ini', 'ehthumbs.db'];

// 废弃后台文件（功能已被插件接管，建议删除）
$deprecatedAdminFiles = [
    'admin/wormhole.php' => '虫洞联盟管理已迁移到 plugins/wormhole/admin.php',
    'admin/blacklist.php' => '黑名单管理已合并到虫洞联盟插件',
];

// ========== 辅助函数：生成建表 SQL ==========
function buildCreateTableSQL(string $tableName, array $schema): string {
    $lines = [];
    $primaryKey = '';

    foreach ($schema['fields'] as $field => $def) {
        $line = "`{$field}` {$def['type']}";

        if ($def['null'] === 'NO') {
            $line .= ' NOT NULL';
        } else {
            $line .= ' NULL';
        }

        if (isset($def['default'])) {
            $default = $def['default'];
            if ($default === 'NULL' || $default === 'CURRENT_TIMESTAMP' || str_starts_with($default, "'")) {
                $line .= " DEFAULT {$default}";
            } elseif ($default !== '' && $default !== 'auto_increment') {
                $line .= " DEFAULT {$default}";
            }
        }

        if (!empty($def['extra']) && $def['extra'] === 'auto_increment') {
            $line .= ' AUTO_INCREMENT';
        }
        if (!empty($def['extra']) && str_contains($def['extra'], 'on update CURRENT_TIMESTAMP')) {
            $line .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if (!empty($def['comment'])) {
            $line .= " COMMENT '" . addslashes($def['comment']) . "'";
        }

        $lines[] = $line;

        if (!empty($def['key']) && $def['key'] === 'PRI') {
            $primaryKey = $field;
        }
    }

    if ($primaryKey) {
        $lines[] = "PRIMARY KEY (`{$primaryKey}`)";
    }

    if (!empty($schema['indexes'])) {
        foreach ($schema['indexes'] as $idx) {
            $unique = !empty($idx['unique']) ? 'UNIQUE' : '';
            $cols = implode(',', array_map(function($c) { return "`{$c}`"; }, $idx['columns']));
            $lines[] = "{$unique} KEY `{$idx['name']}` ({$cols})";
        }
    }

    $comment = isset($schema['comment']) ? " COMMENT='" . addslashes($schema['comment']) . "'" : '';

    return "CREATE TABLE IF NOT EXISTS `{$tableName}` (
        " . implode(",\n        ", $lines) . "
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci{$comment};";
}

// ========== 辅助函数：生成添加字段 SQL ==========
function buildAddColumnSQL(string $tableName, string $field, array $def, string $afterField): string {
    $line = "ALTER TABLE `{$tableName}` ADD COLUMN `{$field}` {$def['type']}";

    if ($def['null'] === 'NO') {
        $line .= ' NOT NULL';
    } else {
        $line .= ' NULL';
    }

    if (isset($def['default'])) {
        $default = $def['default'];
        if ($default === 'NULL' || $default === 'CURRENT_TIMESTAMP' || str_starts_with($default, "'")) {
            $line .= " DEFAULT {$default}";
        } elseif ($default !== '' && $default !== 'auto_increment') {
            $line .= " DEFAULT {$default}";
        }
    }

    if (!empty($def['extra']) && $def['extra'] === 'auto_increment') {
        $line .= ' AUTO_INCREMENT';
    }
    if (!empty($def['extra']) && str_contains($def['extra'], 'on update CURRENT_TIMESTAMP')) {
        $line .= ' ON UPDATE CURRENT_TIMESTAMP';
    }

    if (!empty($def['comment'])) {
        $line .= " COMMENT '" . addslashes($def['comment']) . "'";
    }

    if ($afterField) {
        $line .= " AFTER `{$afterField}`";
    } else {
        $line .= " FIRST";
    }

    return $line;
}

// ========== 检测函数：环境检查 ==========
function checkEnvironment(): array {
    $items = [];
    $projectRoot = dirname(__DIR__);

    // 1. config.php
    $configExists = file_exists($projectRoot . '/config.php');
    $items[] = [
        'category' => '环境检查',
        'name' => 'config.php 配置文件',
        'status' => $configExists ? 'ok' : 'error',
        'detail' => $configExists ? '' : '配置文件不存在，系统可能未安装',
    ];

    // 2. install.lock
    $lockExists = file_exists($projectRoot . '/install.lock');
    $items[] = [
        'category' => '环境检查',
        'name' => 'install.lock 安装锁',
        'status' => $lockExists ? 'ok' : 'warning',
        'detail' => $lockExists ? '' : '安装锁不存在（不影响运行，但建议创建以防止重复安装）',
    ];

    // 3. 数据库连接
    try {
        $pdo = Database::getInstance();
        $pdo->query("SELECT 1");
        $items[] = [
            'category' => '环境检查',
            'name' => '数据库连接',
            'status' => 'ok',
            'detail' => '',
        ];
    } catch (Throwable $e) {
        $items[] = [
            'category' => '环境检查',
            'name' => '数据库连接',
            'status' => 'error',
            'detail' => $e->getMessage(),
        ];
    }

    // 4. PHP 版本
    $phpVersion = PHP_VERSION;
    $minVersion = '7.4.0';
    $phpOk = version_compare($phpVersion, $minVersion, '>=');
    $items[] = [
        'category' => '环境检查',
        'name' => 'PHP 版本',
        'status' => $phpOk ? 'ok' : 'error',
        'detail' => $phpOk ? "当前版本 {$phpVersion}" : "当前版本 {$phpVersion}，要求 >= {$minVersion}",
    ];

    return $items;
}

// ========== 检测函数：废弃表清理 ==========
function checkAbandonedTables(array $abandonedTables, string $prefix): array {
    $items = [];

    foreach ($abandonedTables as $table) {
        $fullName = $prefix . $table;
        try {
            $exists = (bool) Database::queryOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$fullName]
            );
            if ($exists) {
                Database::execute("DROP TABLE IF EXISTS `{$fullName}`");
                $items[] = [
                    'category' => '废弃表清理',
                    'name' => $fullName,
                    'status' => 'fixed',
                    'detail' => '已删除废弃表',
                ];
            } else {
                $items[] = [
                    'category' => '废弃表清理',
                    'name' => $fullName,
                    'status' => 'ok',
                    'detail' => '表不存在，无需清理',
                ];
            }
        } catch (Throwable $e) {
            $items[] = [
                'category' => '废弃表清理',
                'name' => $fullName,
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    return $items;
}

// ========== 检测函数：核心表结构检测 ==========
function checkCoreTables(array $expectedSchema, string $prefix, array $abandonedFields): array {
    $items = [];

    foreach ($expectedSchema as $table => $schema) {
        $fullName = $prefix . $table;
        $addedFields = [];
        $droppedFields = [];
        $fixedIndexes = [];
        $errors = [];

        try {
            // 检查表是否存在
            $tableExists = (bool) Database::queryOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$fullName]
            );

            if (!$tableExists) {
                // 创建新表
                $sql = buildCreateTableSQL($fullName, $schema);
                Database::execute($sql);
                $items[] = [
                    'category' => '核心表检测',
                    'name' => $fullName . ' (' . ($schema['comment'] ?? $table) . ')',
                    'status' => 'fixed',
                    'detail' => '表不存在，已自动创建',
                ];
                continue;
            }

            // 表存在，获取当前字段
            $currentFields = [];
            $rows = Database::query(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?
                 ORDER BY ORDINAL_POSITION",
                [$fullName]
            );
            foreach ($rows as $row) {
                $currentFields[$row['COLUMN_NAME']] = [
                    'type' => strtoupper($row['DATA_TYPE']),
                    'null' => $row['IS_NULLABLE'],
                    'default' => $row['COLUMN_DEFAULT'] ?? 'NULL',
                    'extra' => strtoupper($row['EXTRA'] ?? ''),
                    'comment' => $row['COLUMN_COMMENT'] ?? '',
                ];
            }

            $expectedFields = $schema['fields'];

            // 添加缺失字段
            $prevField = '';
            foreach ($expectedFields as $field => $def) {
                if (!isset($currentFields[$field])) {
                    try {
                        $sql = buildAddColumnSQL($fullName, $field, $def, $prevField);
                        Database::execute($sql);
                        $addedFields[] = $field;
                    } catch (Throwable $e) {
                        $errors[] = "添加字段 {$field} 失败: " . $e->getMessage();
                    }
                }
                $prevField = $field;
            }

            // 删除废弃字段（白名单模式：只删除明确声明为废弃的字段，不删除任何不在预期列表中的其他字段）
            foreach ($currentFields as $field => $def) {
                if (isset($expectedFields[$field])) {
                    continue; // 核心字段，保留
                }
                if (isset($abandonedFields[$table]) && in_array($field, $abandonedFields[$table])) {
                    try {
                        Database::execute("ALTER TABLE `{$fullName}` DROP COLUMN `{$field}`");
                        $droppedFields[] = $field;
                    } catch (Throwable $e) {
                        $errors[] = "删除废弃字段 {$field} 失败: " . $e->getMessage();
                    }
                }
                // 其他字段不删除（包括用户自定义插件字段、第三方插件字段）
            }

            // 修复索引
            if (!empty($schema['indexes'])) {
                $currentIndexes = [];
                $idxRows = Database::query(
                    "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
                     FROM information_schema.statistics
                     WHERE table_schema = DATABASE() AND table_name = ?
                     AND INDEX_NAME != 'PRIMARY'",
                    [$fullName]
                );
                foreach ($idxRows as $row) {
                    $currentIndexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
                    $currentIndexes[$row['INDEX_NAME']]['unique'] = ($row['NON_UNIQUE'] == 0);
                }

                foreach ($schema['indexes'] as $idx) {
                    $idxName = $idx['name'];
                    $expectedCols = implode(',', $idx['columns']);
                    $currentCols = isset($currentIndexes[$idxName]) ? implode(',', $currentIndexes[$idxName]['columns']) : '';

                    if (!isset($currentIndexes[$idxName]) || $expectedCols !== $currentCols) {
                        if (isset($currentIndexes[$idxName])) {
                            try {
                                Database::execute("ALTER TABLE `{$fullName}` DROP INDEX `{$idxName}`");
                            } catch (Throwable $e) {
                                // ignore
                            }
                        }
                        $unique = !empty($idx['unique']) ? 'UNIQUE' : '';
                        $cols = implode(',', array_map(function($c) { return "`{$c}`"; }, $idx['columns']));
                        try {
                            Database::execute("ALTER TABLE `{$fullName}` ADD {$unique} INDEX `{$idxName}` ({$cols})");
                            $fixedIndexes[] = $idxName;
                        } catch (Throwable $e) {
                            $errors[] = "修复索引 {$idxName} 失败: " . $e->getMessage();
                        }
                    }
                }
            }

            // 综合结果
            if (!empty($errors)) {
                $detail = implode('；', $errors);
                if (!empty($addedFields)) $detail .= '；添加字段: ' . implode(', ', $addedFields);
                if (!empty($droppedFields)) $detail .= '；删除字段: ' . implode(', ', $droppedFields);
                if (!empty($fixedIndexes)) $detail .= '；修复索引: ' . implode(', ', $fixedIndexes);
                $items[] = [
                    'category' => '核心表检测',
                    'name' => $fullName . ' (' . ($schema['comment'] ?? $table) . ')',
                    'status' => 'error',
                    'detail' => $detail,
                ];
            } elseif (!empty($addedFields) || !empty($droppedFields) || !empty($fixedIndexes)) {
                $details = [];
                if (!empty($addedFields)) $details[] = '添加字段: ' . implode(', ', $addedFields);
                if (!empty($droppedFields)) $details[] = '删除字段: ' . implode(', ', $droppedFields);
                if (!empty($fixedIndexes)) $details[] = '修复索引: ' . implode(', ', $fixedIndexes);
                $items[] = [
                    'category' => '核心表检测',
                    'name' => $fullName . ' (' . ($schema['comment'] ?? $table) . ')',
                    'status' => 'fixed',
                    'detail' => implode('；', $details),
                ];
            } else {
                $items[] = [
                    'category' => '核心表检测',
                    'name' => $fullName . ' (' . ($schema['comment'] ?? $table) . ')',
                    'status' => 'ok',
                    'detail' => '',
                ];
            }

        } catch (Throwable $e) {
            $items[] = [
                'category' => '核心表检测',
                'name' => $fullName . ' (' . ($schema['comment'] ?? $table) . ')',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    return $items;
}

// ========== 检测函数：已启用插件表检测 ==========
function checkPluginTables(): array {
    $items = [];

    if (!class_exists('Plugin')) {
        $items[] = [
            'category' => '插件表检测',
            'name' => '插件系统',
            'status' => 'error',
            'detail' => 'Plugin 类不存在',
        ];
        return $items;
    }

    $enabledPlugins = Plugin::getEnabledPlugins();

    if (empty($enabledPlugins)) {
        $items[] = [
            'category' => '插件表检测',
            'name' => '已启用插件',
            'status' => 'ok',
            'detail' => '当前没有已启用的插件',
        ];
        return $items;
    }

    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'nav_';

    foreach ($enabledPlugins as $name => $info) {
        $schema = Plugin::loadSchema($name);
        $issues = [];
        $hasTable = false;
        $hasColumns = false;

        // 检查插件独立表
        foreach ($schema['tables'] as $tableName => $rawSql) {
            $hasTable = true;
            $fullTable = $prefix . $tableName;
            try {
                $exists = Database::queryOne(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                    [$fullTable]
                );
                if (!$exists) {
                    $sql = str_replace('{prefix}', $prefix, $rawSql);
                    Database::execute($sql);
                    $issues[] = "创建表 {$tableName}";
                }
            } catch (Throwable $e) {
                $issues[] = "表 {$tableName} 检查/创建失败: " . $e->getMessage();
            }
        }

        // 检查插件添加到已有表的字段
        foreach ($schema['columns'] as $targetTable => $columns) {
            $hasColumns = true;
            $fullTable = $prefix . $targetTable;
            foreach ($columns as $columnName => $columnDef) {
                try {
                    $exists = Database::queryOne(
                        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
                        [$fullTable, $columnName]
                    );
                    if (!$exists) {
                        Database::execute("ALTER TABLE `{$fullTable}` ADD COLUMN `{$columnName}` {$columnDef}");
                        $issues[] = "添加字段 {$targetTable}.{$columnName}";
                    }
                } catch (Throwable $e) {
                    $issues[] = "字段 {$targetTable}.{$columnName} 检查/添加失败: " . $e->getMessage();
                }
            }
        }

        // 检查插件配置项
        $settings = new SettingsModel();
        $missingConfig = [];
        foreach ($schema['config'] as $key => $value) {
            $existing = $settings->get($key, null);
            if ($existing === null) {
                $settings->set($key, (string)$value);
                $missingConfig[] = $key;
            }
        }
        if (!empty($missingConfig)) {
            $issues[] = '写入配置: ' . implode(', ', array_slice($missingConfig, 0, 3)) . (count($missingConfig) > 3 ? '等' . count($missingConfig) . '项' : '');
        }

        $title = $info['title'] ?? $name;
        if (!empty($issues)) {
            // 判断是否有错误
            $hasError = false;
            foreach ($issues as $issue) {
                if (str_contains($issue, '失败')) {
                    $hasError = true;
                    break;
                }
            }
            $items[] = [
                'category' => '插件表检测',
                'name' => $name . ' (' . $title . ')',
                'status' => $hasError ? 'error' : 'fixed',
                'detail' => implode('；', $issues),
            ];
        } else {
            $items[] = [
                'category' => '插件表检测',
                'name' => $name . ' (' . $title . ')',
                'status' => 'ok',
                'detail' => $hasTable || $hasColumns ? '表和字段完整' : '配置项完整',
            ];
        }
    }

    return $items;
}

// ========== 检测函数：本地文件检测（含废弃后台文件） ==========
function checkLocalFiles(array $expectedRootFiles, array $expectedRootDirs, array $junkExtensions, array $junkFileNames, array $deprecatedAdminFiles): array {
    $items = [];
    $projectRoot = dirname(__DIR__);
    $redundantFiles = [];

    // 1. 检查根目录多余文件
    $rootFiles = [];
    $rootDirs = [];
    if (is_dir($projectRoot)) {
        foreach (new DirectoryIterator($projectRoot) as $item) {
            if ($item->isDot()) continue;
            $name = $item->getBasename();
            if ($item->isDir()) {
                $rootDirs[] = $name;
            } else {
                $rootFiles[] = $name;
            }
        }
    }

    // 根目录多余的文件
    $extraRootFiles = [];
    foreach ($rootFiles as $file) {
        if (!in_array($file, $expectedRootFiles)) {
            $extraRootFiles[] = $file;
        }
    }
    if (empty($extraRootFiles)) {
        $items[] = [
            'category' => '本地文件检测',
            'name' => '根目录文件检查',
            'status' => 'ok',
            'detail' => '无多余文件',
        ];
    } else {
        $items[] = [
            'category' => '本地文件检测',
            'name' => '根目录文件检查',
            'status' => 'warning',
            'detail' => '发现多余文件: ' . implode(', ', $extraRootFiles),
        ];
    }

    // 2. 递归扫描垃圾文件
    $junkFiles = [];
    $skipDirs = ['.git', 'node_modules', '.dumate'];
    scanJunkFiles($projectRoot, $junkExtensions, $junkFileNames, $skipDirs, $junkFiles, $projectRoot);

    if (empty($junkFiles)) {
        $items[] = [
            'category' => '本地文件检测',
            'name' => '临时/垃圾文件扫描',
            'status' => 'ok',
            'detail' => '未发现临时文件和垃圾文件',
        ];
    } else {
        $displayFiles = array_slice($junkFiles, 0, 5);
        $detail = implode(', ', $displayFiles);
        if (count($junkFiles) > 5) {
            $detail .= ' 等共 ' . count($junkFiles) . ' 个文件';
        }
        $items[] = [
            'category' => '本地文件检测',
            'name' => '临时/垃圾文件扫描',
            'status' => 'warning',
            'detail' => $detail,
        ];
    }

    // 3. 检查调试/测试文件
    $debugFiles = [];
    scanDebugFiles($projectRoot, $skipDirs, $debugFiles, $projectRoot);

    if (empty($debugFiles)) {
        $items[] = [
            'category' => '本地文件检测',
            'name' => '调试/测试文件检查',
            'status' => 'ok',
            'detail' => '未发现调试或测试文件',
        ];
    } else {
        $items[] = [
            'category' => '本地文件检测',
            'name' => '调试/测试文件检查',
            'status' => 'warning',
            'detail' => implode(', ', array_slice($debugFiles, 0, 5)) . (count($debugFiles) > 5 ? ' 等共 ' . count($debugFiles) . ' 个' : ''),
        ];
    }

    // 4. 废弃后台文件检测（功能已被插件接管）
    foreach ($deprecatedAdminFiles as $file => $reason) {
        $fullPath = $projectRoot . '/' . $file;
        if (file_exists($fullPath)) {
            $items[] = [
                'category' => '本地文件检测',
                'name' => '废弃文件 ' . $file,
                'status' => 'warning',
                'detail' => $reason . '，建议删除',
            ];
        } else {
            $items[] = [
                'category' => '本地文件检测',
                'name' => '废弃文件 ' . $file,
                'status' => 'ok',
                'detail' => $reason,
            ];
        }
    }

    return $items;
}

// ========== 检测函数：文件权限检测（独立区域） ==========
function checkPermissions(string $projectRoot): array {
    $items = [];

    // 当前 PHP 进程用户（只计算一次）
    $processUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'uid:' . posix_geteuid())
        : (getenv('APACHE_RUN_USER') ?: getenv('USER') ?: getenv('USERNAME') ?: '未知');

    // 需要检测的路径列表（目录 + 文件）
    $checkList = [
        // 目录
        ['path' => 'data',          'type' => 'dir',  'desc' => '数据目录'],
        ['path' => 'data/updates',  'type' => 'dir',  'desc' => '更新缓存'],
        ['path' => 'data/backups',  'type' => 'dir',  'desc' => '系统备份'],
        ['path' => 'data/sitemaps', 'type' => 'dir',  'desc' => '站点地图'],
        ['path' => 'admin',         'type' => 'dir',  'desc' => '后台目录'],
        ['path' => 'api',           'type' => 'dir',  'desc' => 'API接口'],
        ['path' => 'assets',        'type' => 'dir',  'desc' => '静态资源'],
        ['path' => 'core',          'type' => 'dir',  'desc' => '核心代码'],
        ['path' => 'install',       'type' => 'dir',  'desc' => '安装目录'],
        ['path' => 'plugins',       'type' => 'dir',  'desc' => '插件目录'],
        ['path' => 'templates',     'type' => 'dir',  'desc' => '模板目录'],
        ['path' => 'wormhole',      'type' => 'dir',  'desc' => '虫洞联盟'],
        // 文件
        ['path' => 'config.php',    'type' => 'file', 'desc' => '配置文件'],
        ['path' => 'install.lock',  'type' => 'file', 'desc' => '安装锁定'],
        ['path' => 'go.php',        'type' => 'file', 'desc' => '跳转文件'],
        ['path' => 'index.php',     'type' => 'file', 'desc' => '首页入口'],
    ];

    foreach ($checkList as $itemDef) {
        $relPath = $itemDef['path'];
        $type    = $itemDef['type'];
        $desc    = $itemDef['desc'];
        $fullPath = $projectRoot . '/' . $relPath;

        // 权限数字
        $perms = '未创建';
        if (file_exists($fullPath)) {
            $perms = substr(decoct(fileperms($fullPath)), -4);
        }

        // 文件属主
        $owner = '未知';
        if (function_exists('posix_getpwuid') && file_exists($fullPath)) {
            $info = posix_getpwuid(fileowner($fullPath));
            $owner = $info['name'] ?? 'uid:' . fileowner($fullPath);
        }

        // 是否可写
        if ($type === 'dir') {
            $exists   = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);
        } else {
            if (file_exists($fullPath)) {
                $exists   = true;
                $writable = is_writable($fullPath);
            } else {
                $exists   = false;
                $parent   = dirname($fullPath);
                $writable = is_dir($parent) && is_writable($parent);
            }
        }

        $detail = $desc . ' · 权限' . $perms . ' · 属主' . $owner . ' · 进程' . $processUser;
        if (!$exists && $type === 'file') {
            $detail .= ' · 文件不存在（父目录可写则自动创建）';
        }

        if ($writable) {
            $items[] = [
                'category' => '文件权限检测',
                'name' => $relPath,
                'status' => 'ok',
                'detail' => $detail . ' · 可写',
            ];
        } else {
            $items[] = [
                'category' => '文件权限检测',
                'name' => $relPath,
                'status' => 'warning',
                'detail' => $detail . ' · 不可写 · 建议 chown ' . $processUser . ':' . $processUser . ' ' . $relPath . ' 或 chmod 755（目录）/ 644（文件）',
            ];
        }
    }

    return $items;
}

// 递归扫描垃圾文件
function scanJunkFiles(string $dir, array $junkExtensions, array $junkFileNames, array $skipDirs, array &$result, string $rootPath): void {
    if (!is_dir($dir)) return;

    try {
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot()) continue;
            $name = $item->getBasename();

            if ($item->isDir()) {
                if (in_array($name, $skipDirs)) continue;
                scanJunkFiles($item->getPathname(), $junkExtensions, $junkFileNames, $skipDirs, $result, $rootPath);
            } else {
                // 检查垃圾文件名
                if (in_array($name, $junkFileNames)) {
                    $relPath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $item->getPathname());
                    $result[] = $relPath;
                    continue;
                }
                // 检查垃圾扩展名
                $ext = strtolower($item->getExtension());
                if (in_array($ext, $junkExtensions)) {
                    $relPath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $item->getPathname());
                    $result[] = $relPath;
                }
            }
        }
    } catch (Throwable $e) {
        // 忽略权限错误
    }
}

// 递归扫描调试/测试文件
function scanDebugFiles(string $dir, array $skipDirs, array &$result, string $rootPath): void {
    if (!is_dir($dir)) return;

    $debugPatterns = [
        '/^test_.*\.php$/i',
        '/^debug_.*\.php$/i',
        '/^__.*\.php$/i',
        '/^temp_.*\.php$/i',
        '/^_.*\.php$/i',
        '/^phpinfo\.php$/i',
        '/^info\.php$/i',
    ];

    try {
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot()) continue;
            $name = $item->getBasename();

            if ($item->isDir()) {
                if (in_array($name, $skipDirs)) continue;
                scanDebugFiles($item->getPathname(), $skipDirs, $result, $rootPath);
            } else {
                foreach ($debugPatterns as $pattern) {
                    if (preg_match($pattern, $name)) {
                        $relPath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $item->getPathname());
                        $result[] = $relPath;
                        break;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // 忽略权限错误
    }
}

// ========== 主逻辑 ==========

// 未登录：显示登录提示
if ($needLogin):
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统检测 - 懒人导航</title>
    <link rel="stylesheet" href="/assets/css/tabler-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 420px; text-align: center; }
        .card .icon { font-size: 48px; color: #6366f1; margin-bottom: 16px; }
        .card h1 { font-size: 18px; color: #1f2937; margin-bottom: 8px; }
        .card p { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.6; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; border: none; cursor: pointer; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #5558e0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="ti ti-lock"></i></div>
        <h1>需要管理员登录</h1>
        <p>系统检测工具需要管理员权限，请先登录后台管理账号。</p>
        <a href="/admin/login.php" class="btn btn-primary">
            <i class="ti ti-login"></i> 前往登录
        </a>
    </div>
</body>
</html>
<?php
    exit;
endif;

// GET 请求：显示确认页面
if ($_SERVER['REQUEST_METHOD'] === 'GET'):
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统检测 - 懒人导航</title>
    <link rel="stylesheet" href="/assets/css/tabler-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 560px; width: 100%; }
        .header { text-align: center; margin-bottom: 32px; }
        .header .icon { font-size: 56px; color: #6366f1; margin-bottom: 12px; }
        .header h1 { font-size: 22px; color: #1f2937; margin-bottom: 8px; }
        .header p { font-size: 14px; color: #6b7280; line-height: 1.6; }

        .check-group { margin-bottom: 20px; }
        .check-item { display: flex; align-items: flex-start; gap: 12px; padding: 16px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all .2s; }
        .check-item:hover { border-color: #c7d2fe; background: #f5f3ff; }
        .check-item.active { border-color: #6366f1; background: #eef2ff; }
        .check-item .checkbox { width: 20px; height: 20px; border: 2px solid #d1d5db; border-radius: 6px; flex-shrink: 0; margin-top: 1px; display: flex; align-items: center; justify-content: center; transition: all .2s; }
        .check-item.active .checkbox { background: #6366f1; border-color: #6366f1; }
        .check-item.active .checkbox::after { content: ''; width: 6px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
        .check-item .info { flex: 1; }
        .check-item .info .title { font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .check-item .info .desc { font-size: 13px; color: #6b7280; line-height: 1.5; }

        .actions { display: flex; gap: 12px; justify-content: center; margin-top: 32px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 15px; font-weight: 500; transition: all .2s; border: none; cursor: pointer; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #5558e0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-primary:disabled { background: #c7d2fe; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-ghost { background: #f3f4f6; color: #6b7280; }
        .btn-ghost:hover { background: #e5e7eb; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="icon"><i class="ti ti-health-recognition"></i></div>
            <h1>系统检测工具</h1>
            <p>本工具将检测数据库结构是否完整、本地文件是否有冗余。<br>检测到的问题会自动修复，并在下方逐行显示结果。</p>
        </div>

        <form method="POST" id="checkForm">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
            <div class="check-group">
                <label class="check-item active" data-target="check_db">
                    <div class="checkbox"></div>
                    <div class="info">
                        <div class="title"><i class="ti ti-database"></i> 数据库结构检测</div>
                        <div class="desc">检查核心表、字段、索引是否完整，缺少的自动添加，多余的自动删除，已启用插件的表和字段也会检测</div>
                    </div>
                    <input type="checkbox" name="check_db" id="check_db" value="1" checked hidden>
                </label>
            </div>

            <div class="check-group">
                <label class="check-item active" data-target="check_files">
                    <div class="checkbox"></div>
                    <div class="info">
                        <div class="title"><i class="ti ti-folder-search"></i> 本地文件检测</div>
                        <div class="desc">扫描项目目录中的冗余文件、临时文件（.bak/.tmp/.swp 等）、系统垃圾文件和调试测试文件、废弃后台文件</div>
                    </div>
                    <input type="checkbox" name="check_files" id="check_files" value="1" checked hidden>
                </label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary" id="startBtn">
                    <i class="ti ti-player-play"></i> 开始检测
                </button>
                <a href="/admin/" class="btn btn-ghost">
                    <i class="ti ti-arrow-left"></i> 返回后台
                </a>
            </div>
        </form>
    </div>

    <script>
        document.querySelectorAll('.check-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.toggle('active');
                var input = document.getElementById(this.dataset.target);
                input.checked = this.classList.contains('active');
            });
        });

        document.getElementById('checkForm').addEventListener('submit', function(e) {
            var btn = document.getElementById('startBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader ti-spin"></i> 正在检测...';
        });
    </script>
</body>
</html>
<?php
    exit;
endif;

// ========== POST 请求：执行检测 ==========

$checkDb = isset($_POST['check_db']);
$checkFiles = isset($_POST['check_files']);

// 如果都没选，默认全检测
if (!$checkDb && !$checkFiles) {
    $checkDb = true;
    $checkFiles = true;
}

// 收集所有检测结果
$checkItems = [];

// 阶段 1: 环境检查（始终执行）
$checkItems = array_merge($checkItems, checkEnvironment());

// 阶段 2-4: 数据库检测
if ($checkDb) {
    // 废弃表清理
    $checkItems = array_merge($checkItems, checkAbandonedTables($abandonedTables, $prefix));

    // 核心表检测
    $checkItems = array_merge($checkItems, checkCoreTables($expectedSchema, $prefix, $abandonedFields));

    // 插件表检测
    $checkItems = array_merge($checkItems, checkPluginTables());
}

    // 阶段 5: 本地文件检测（含废弃后台文件）
    if ($checkFiles) {
        $checkItems = array_merge($checkItems, checkLocalFiles($expectedRootFiles, $expectedRootDirs, $junkExtensions, $junkFileNames, $deprecatedAdminFiles));

        // 阶段 6: 文件权限检测（独立区域）
        $projectRoot = dirname(__DIR__);
        $checkItems = array_merge($checkItems, checkPermissions($projectRoot));
    }

// 统计
$passCount = 0;
$fixedCount = 0;
$warningCount = 0;
$errorCount = 0;
foreach ($checkItems as $item) {
    switch ($item['status']) {
        case 'ok': $passCount++; break;
        case 'fixed': $fixedCount++; break;
        case 'warning': $warningCount++; break;
        case 'error': $errorCount++; break;
    }
}
$totalCount = count($checkItems);

// 按分类分组
$categories = [];
foreach ($checkItems as $item) {
    $cat = $item['category'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $item;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统检测结果 - 懒人导航</title>
    <link rel="stylesheet" href="/assets/css/tabler-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Consolas', monospace; background: #0f172a; min-height: 100vh; padding: 20px; color: #e2e8f0; }
        .container { max-width: 860px; margin: 0 auto; }

        /* 顶部状态栏 */
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .topbar .title { font-size: 18px; font-weight: 600; color: #f1f5f9; display: flex; align-items: center; gap: 8px; }
        .topbar .title .icon { color: #6366f1; }
        .topbar .actions { display: flex; gap: 8px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: all .2s; border: none; cursor: pointer; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #5558e0; }
        .btn-ghost { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
        .btn-ghost:hover { background: #334155; }

        /* 进度条 */
        .progress-wrap { margin-bottom: 20px; }
        .progress-bar { height: 6px; background: #1e293b; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #6366f1, #818cf8); border-radius: 3px; transition: width .3s ease; width: 0%; }
        .progress-text { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-top: 6px; }

        /* 检测终端 */
        .terminal { background: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
        .terminal-header { background: #334155; padding: 10px 16px; display: flex; align-items: center; gap: 8px; }
        .terminal-header .dot { width: 12px; height: 12px; border-radius: 50%; }
        .dot-red { background: #ef4444; }
        .dot-yellow { background: #f59e0b; }
        .dot-green { background: #22c55e; }
        .terminal-header .label { font-size: 13px; color: #94a3b8; margin-left: 8px; }
        .terminal-body { padding: 16px; max-height: 600px; overflow-y: auto; }
        .terminal-body::-webkit-scrollbar { width: 6px; }
        .terminal-body::-webkit-scrollbar-track { background: transparent; }
        .terminal-body::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }

        /* 检测项 */
        .check-category { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 0 6px; border-top: 1px solid #334155; margin-top: 12px; }
        .check-category:first-child { border-top: none; margin-top: 0; padding-top: 0; }
        .check-row { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0; font-size: 13px; line-height: 1.6; opacity: 0; transform: translateX(-8px); transition: all .3s ease; }
        .check-row.visible { opacity: 1; transform: translateX(0); }
        .check-row .status-icon { flex-shrink: 0; width: 20px; text-align: center; font-size: 14px; margin-top: 1px; }
        .check-row .status-icon.checking { color: #64748b; }
        .check-row .status-icon.ok { color: #22c55e; }
        .check-row .status-icon.fixed { color: #f59e0b; }
        .check-row .status-icon.warning { color: #f59e0b; }
        .check-row .status-icon.error { color: #ef4444; }
        .check-row .name { color: #cbd5e1; }
        .check-row .detail { color: #64748b; font-size: 12px; }
        .check-row .detail.error-text { color: #fca5a5; }
        .check-row .detail.fixed-text { color: #fcd34d; }
        .check-row .detail.warning-text { color: #fcd34d; }

        /* 检查中动画 */
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .ti-spin { animation: spin 0.8s linear infinite; display: inline-block; }

        /* 汇总卡片 */
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 20px; opacity: 0; transition: opacity .4s; }
        .summary.visible { opacity: 1; }
        .summary-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 16px; text-align: center; }
        .summary-card .num { font-size: 28px; font-weight: 700; }
        .summary-card .label { font-size: 12px; color: #64748b; margin-top: 4px; }
        .summary-card.ok .num { color: #22c55e; }
        .summary-card.fixed .num { color: #f59e0b; }
        .summary-card.warning .num { color: #f59e0b; }
        .summary-card.error .num { color: #ef4444; }

        .bottom-actions { display: flex; gap: 12px; justify-content: center; margin-top: 24px; opacity: 0; transition: opacity .4s; }
        .bottom-actions.visible { opacity: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div class="title">
                <i class="ti ti-health-recognition icon"></i>
                系统检测结果
            </div>
            <div class="actions">
                <a href="/install/upgrade_v2.php" class="btn btn-primary">
                    <i class="ti ti-refresh"></i> 重新检测
                </a>
                <a href="/admin/" class="btn btn-ghost">
                    <i class="ti ti-dashboard"></i> 返回后台
                </a>
            </div>
        </div>

        <div class="progress-wrap">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-text">
                <span id="progressLabel">准备检测...</span>
                <span id="progressCount">0 / <?= $totalCount ?></span>
            </div>
        </div>

        <div class="terminal">
            <div class="terminal-header">
                <span class="dot dot-red"></span>
                <span class="dot dot-yellow"></span>
                <span class="dot dot-green"></span>
                <span class="label">检测日志</span>
            </div>
            <div class="terminal-body" id="terminalBody">
                <?php foreach ($categories as $catName => $catItems): ?>
                <div class="check-category"><?= htmlspecialchars($catName) ?></div>
                <?php foreach ($catItems as $item): ?>
                <div class="check-row" data-status="<?= $item['status'] ?>" data-detail="<?= htmlspecialchars($item['detail']) ?>">
                    <span class="status-icon checking"><i class="ti ti-loader ti-spin"></i></span>
                    <span class="name"><?= htmlspecialchars($item['name']) ?></span>
                    <?php if (!empty($item['detail'])): ?>
                    <span class="detail detail-text <?= $item['status'] === 'error' ? 'error-text' : ($item['status'] === 'fixed' ? 'fixed-text' : ($item['status'] === 'warning' ? 'warning-text' : '')) ?>"><?= htmlspecialchars($item['detail']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="summary" id="summary">
            <div class="summary-card ok">
                <div class="num"><?= $passCount ?></div>
                <div class="label">通过</div>
            </div>
            <div class="summary-card fixed">
                <div class="num"><?= $fixedCount ?></div>
                <div class="label">已修复</div>
            </div>
            <div class="summary-card warning">
                <div class="num"><?= $warningCount ?></div>
                <div class="label">警告</div>
            </div>
            <div class="summary-card error">
                <div class="num"><?= $errorCount ?></div>
                <div class="label">错误</div>
            </div>
        </div>

        <div class="bottom-actions" id="bottomActions">
            <a href="/install/upgrade_v2.php" class="btn btn-primary">
                <i class="ti ti-refresh"></i> 重新检测
            </a>
            <a href="/admin/" class="btn btn-ghost">
                <i class="ti ti-dashboard"></i> 返回后台
            </a>
        </div>
    </div>

    <script>
        (function() {
            var rows = document.querySelectorAll('.check-row');
            var total = rows.length;
            var index = 0;
            var progressFill = document.getElementById('progressFill');
            var progressLabel = document.getElementById('progressLabel');
            var progressCount = document.getElementById('progressCount');
            var summary = document.getElementById('summary');
            var bottomActions = document.getElementById('bottomActions');

            var statusIcons = {
                'ok': '<i class="ti ti-check" style="font-size:16px;"></i>',
                'fixed': '<i class="ti ti-tools" style="font-size:14px;"></i>',
                'warning': '<i class="ti ti-alert-triangle" style="font-size:14px;"></i>',
                'error': '<i class="ti ti-x" style="font-size:16px;"></i>',
            };

            var statusLabels = {
                'ok': '通过',
                'fixed': '已修复',
                'warning': '警告',
                'error': '错误',
            };

            function revealNext() {
                if (index >= total) {
                    // 全部完成
                    progressFill.style.width = '100%';
                    progressLabel.textContent = '检测完成';
                    progressCount.textContent = total + ' / ' + total;
                    summary.classList.add('visible');
                    setTimeout(function() {
                        bottomActions.classList.add('visible');
                    }, 200);
                    return;
                }

                var row = rows[index];
                var status = row.dataset.status;

                // 先显示"正在检测..."
                row.classList.add('visible');
                progressLabel.textContent = '正在检测: ' + row.querySelector('.name').textContent;
                progressCount.textContent = (index + 1) + ' / ' + total;
                progressFill.style.width = ((index + 1) / total * 100) + '%';

                // 短暂延迟后显示结果
                setTimeout(function() {
                    var iconEl = row.querySelector('.status-icon');
                    iconEl.className = 'status-icon ' + status;
                    iconEl.innerHTML = statusIcons[status] || statusIcons['ok'];

                    index++;
                    // 下一项
                    setTimeout(revealNext, 80 + Math.random() * 120);
                }, 150 + Math.random() * 200);
            }

            // 启动
            setTimeout(revealNext, 300);
        })();
    </script>
</body>
</html>
<?php
// 如果还有未关闭的输出缓冲，确保刷新
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>