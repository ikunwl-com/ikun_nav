<?php
/**
 * 应用引导文件
 */

// 程序版本（用于在线更新检测）
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// 编码
mb_internal_encoding('UTF-8');

// 启动 Session（必须在任何输出之前）
// 安全：在 session_start 之前设置 cookie 安全参数（secure/httponly/samesite）和自定义 session_name
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name('NAVSESSID');
    session_start();
}

// ========== 首次安装检测（必须在加载 config.php 之前）==========
// config.php 不存在说明尚未安装，直接跳转安装页面
$configFile = __DIR__ . '/../config.php';
$script = $_SERVER['SCRIPT_NAME'] ?? '';

if (!file_exists($configFile)) {
    // 如果已经在安装页面，不重复跳转
    if (strpos($script, '/install/') === false) {
        header('Location: /install/');
        exit;
    }
    // 在安装页面时，不加载后续依赖 config.php 的逻辑
    // 但仍注册自动加载器，以便安装脚本使用核心类
    spl_autoload_register(function (string $class) {
        $file = __DIR__ . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    if (file_exists(__DIR__ . '/helpers.php')) {
        require_once __DIR__ . '/helpers.php';
    }
    // 安装模式下默认关闭错误屏蔽，方便排查
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '1');
    return; // 提前返回，跳过后续需要 config.php 的逻辑
}

// ========== 正常启动流程 ==========

// 加载主配置文件
require_once $configFile;

// 辅助函数
require_once __DIR__ . '/helpers.php';

// 核心类自动加载
spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 统一日志工具（必须放在自动加载之后）
require_once __DIR__ . '/Logger.php';

// 初始化插件系统（扫描并加载已启用的插件）
Plugin::init();

// 调试模式：config.php 未定义时，从数据库读取或默认关闭
if (!defined('APP_DEBUG')) {
    // 尝试从数据库读取调试模式设置
    try {
        require_once __DIR__ . '/Database.php';
        $tbl = Database::table('settings');
        $row = Database::queryOne("SELECT setting_value FROM {$tbl} WHERE setting_key = 'debug_mode'");
        define('APP_DEBUG', $row && ($row['setting_value'] ?? '') === '1');
    } catch (Throwable $e) {
        define('APP_DEBUG', false);
    }
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// 已加载 config.php 但数据库可能未配置，二次校验
if (!isInstalled()) {
    if (strpos($script, '/install/') === false) {
        header('Location: /install/');
        exit;
    }
}
