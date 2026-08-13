<?php
/**
 * 更新操作 API（AJAX 接口）
 * 支持动作：check / download / install / rollback
 */

// 安全：阻止任何 PHP 错误/警告输出到响应体（防止污染 JSON）
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

// 更新操作可能很慢，放宽限制
@set_time_limit(300);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../core/bootstrap.php';

// 致命错误捕获：防止空白响应
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $msg = $error['message'];
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        // 安全：不暴露服务器路径和文件结构
        echo json_encode(['success' => false, 'message' => '服务器内部错误，请查看日志获取详情']);
        error_log('Update API fatal: ' . $msg . ' in ' . ($error['file'] ?? '?') . ':' . ($error['line'] ?? '?'));
        exit;
    }
});

// 必须已登录且为管理员
Security::initSession();
if (empty($_SESSION['admin_id'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}
// 安全：检查会话超时
$sessionTimeout = (int)setting('session_timeout', 3600);
if (Security::isSessionExpired($sessionTimeout)) {
    session_destroy();
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '会话已过期，请重新登录']);
    exit;
}

// CSRF 校验
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'CSRF 校验失败']);
    exit;
}

$action = $_POST['action'] ?? '';

$result = null;

switch ($action) {
    // ===== 检测新版本 =====
    case 'check':
        $result = Updater::check();
        // 同步 Session 缓存：侧边栏红点依赖此值
        $_SESSION['has_new_version'] = !empty($result['has_update']);
        $_SESSION['update_check_time'] = time();
        $_SESSION['checked_version'] = Updater::currentVersion();
        break;

    // ===== 下载更新包 =====
    case 'download':
        // 安全：不接受用户提供的URL，仅从更新服务器check()结果获取下载地址
        $checkResult = Updater::check();
        if (isset($checkResult['error'])) {
            $result = ['success' => false, 'message' => '检查更新失败: ' . $checkResult['error']];
            break;
        }
        $url = $checkResult['download_url'] ?? '';
        if (empty($url)) {
            $result = ['success' => false, 'message' => '更新服务器未提供下载地址'];
            break;
        }
        // 安全：仅允许 http/https
        if (!preg_match('/^https?:\/\//i', $url)) {
            $result = ['success' => false, 'message' => '非法下载地址'];
            break;
        }
        // 安全：验证下载地址来自更新服务器同域
        $serverHost = parse_url(Updater::serverUrl(), PHP_URL_HOST);
        $dlHost = parse_url($url, PHP_URL_HOST);
        if ($serverHost && $dlHost && strcasecmp($serverHost, $dlHost) !== 0) {
            $result = ['success' => false, 'message' => '下载地址与更新服务器不匹配'];
            break;
        }

        $version = $checkResult['version'] ?? 'unknown';
        if ($version === Updater::currentVersion()) {
            $version = 'latest-' . date('Ymd');
        }

        $file = Updater::download($url, $version);
        if ($file === false || (is_array($file) && isset($file['error']))) {
            $err = (is_array($file) && isset($file['error'])) ? $file['error'] : '未知错误';
            $result = ['success' => false, 'message' => '下载更新包失败: ' . $err];
        } else {
            $result = ['success' => true, 'file' => basename($file)];
        }
        break;

    // ===== 安装更新 =====
    case 'install':
        $fileName = $_POST['file'] ?? '';
        $expectedVersion = $_POST['version'] ?? '';
        if (empty($fileName)) {
            $result = ['success' => false, 'message' => '未指定更新包'];
            break;
        }
        // 安全：只允许文件名，禁止路径穿越
        $fileName = basename($fileName);
        // 安全：只允许 update-* 前缀的文件名（由 Updater::download 生成）
        if (!preg_match('/^update-[a-zA-Z0-9._-]+\.(zip|zba)$/', $fileName)) {
            $result = ['success' => false, 'message' => '非法更新包文件名'];
            break;
        }
        $packageFile = Updater::cacheDir() . '/' . $fileName;
        if (!file_exists($packageFile)) {
            $result = ['success' => false, 'message' => '更新包不存在'];
            break;
        }

        // 1. 先备份
        $backupDir = Updater::backup();
        if ($backupDir === false) {
            $result = ['success' => false, 'message' => '备份失败，安装已中止'];
            break;
        }
        Updater::cleanOldBackups(5);

        // 2. 执行安装
        $result = Updater::install($packageFile);

        // 3. 如果前端传了版本号，优先用这个（ikun_nav.php 里的最权威）
        if (!empty($expectedVersion)) {
            $result['new_version'] = $expectedVersion;
            // 自动同步写入 bootstrap.php
            Updater::updateBootstrapVersion($expectedVersion);
        }
        break;

    // ===== 回滚备份 =====
    case 'rollback':
        $backupName = $_POST['backup'] ?? '';
        if (empty($backupName)) {
            $result = ['success' => false, 'message' => '未指定备份'];
            break;
        }
        // 安全：只允许目录名
        $backupName = basename($backupName);
        $backupDir = Updater::backupDir() . '/' . $backupName;
        if (!is_dir($backupDir)) {
            $result = ['success' => false, 'message' => '备份目录不存在'];
            break;
        }

        $result = Updater::rollback($backupDir);
        break;

    // ===== 删除备份 =====
    case 'delete_backup':
        $backupName = $_POST['backup'] ?? '';
        if (empty($backupName)) {
            $result = ['success' => false, 'message' => '未指定备份'];
            break;
        }
        // 安全：只允许目录名
        $backupName = basename($backupName);
        $backupDir = Updater::backupDir() . '/' . $backupName;
        if (!is_dir($backupDir)) {
            $result = ['success' => false, 'message' => '备份目录不存在'];
            break;
        }

        Updater::rmDir($backupDir);
        if (!is_dir($backupDir)) {
            $result = ['success' => true, 'message' => '备份已删除'];
        } else {
            $result = ['success' => false, 'message' => '删除失败，目录仍保留'];
        }
        break;

    default:
        $result = ['success' => false, 'message' => '未知操作'];
}

// 清理可能产生的任何意外输出，确保只返回 JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
