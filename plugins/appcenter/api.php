<?php
/**
 * 应用中心插件 - AJAX 接口
 * 支持动作：list / refresh / install / save_config
 *
 * 安全模型（与 admin/update_api.php 一致）：
 *   - JSON-only 输出，屏蔽所有 PHP 错误/警告，致命错误兜底返回 JSON
 *   - 必须已登录且会话未过期
 *   - CSRF 校验（滑动窗口：当前或上一个 Token，避免多步 AJAX 共用 Token 失败）
 *   - 应用 id 仅接受白名单字符；安装包地址始终来自服务器目录缓存，不接受用户输入 URL
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

// 安装/下载可能较慢，放宽限制
@set_time_limit(300);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../core/bootstrap.php';

// 致命错误捕获：防止空白响应
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        // 安全：不暴露服务器路径和文件结构
        echo json_encode(['success' => false, 'message' => '服务器内部错误，请查看日志获取详情']);
        error_log('AppCenter API fatal: ' . $error['message'] . ' in ' . ($error['file'] ?? '?') . ':' . ($error['line'] ?? '?'));
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
// 会话超时检查
$sessionTimeout = (int)setting('session_timeout', 3600);
if (Security::isSessionExpired($sessionTimeout)) {
    session_destroy();
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '会话已过期，请重新登录']);
    exit;
}

// CSRF 校验（校验但不轮换 Token，滑动窗口模式）
$csrfToken = (string)($_POST['csrf_token'] ?? '');
$csrfValid = false;
if ($csrfToken !== '') {
    $current  = (string)($_SESSION['csrf_token'] ?? '');
    $previous = (string)($_SESSION['csrf_token_previous'] ?? '');
    $csrfValid = ($current !== '' && hash_equals($current, $csrfToken))
              || ($previous !== '' && hash_equals($previous, $csrfToken));
}
if (!$csrfValid) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'CSRF 校验失败，请刷新页面后重试']);
    exit;
}

require_once __DIR__ . '/lib.php';

// 插件未启用时拒绝服务（防止绕过插件管理开关直接调用接口）
if (!Plugin::isEnabled('appcenter')) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '应用中心插件未启用']);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$result = ['success' => false, 'message' => '未知操作'];

switch ($action) {
    // ===== 读取目录（页面加载用，读本地缓存） =====
    case 'list':
        $rows = appcenter_rows();
        $result = array_merge([
            'success'      => true,
            'message'      => '',
            'rows'         => $rows['rows'],
            'fetched_at'   => $rows['fetched_at'],
            'source'       => $rows['source'],
            'auto_enable'  => appcenter_auto_enable(),
            'app_version'  => (defined('APP_VERSION') ? APP_VERSION : '1.0.0'),
        ], appcenter_server_info());
        break;

    // ===== 刷新目录（强制拉取服务器并更新缓存） =====
    case 'refresh':
        $fetch = appcenter_fetch_catalog();
        $rows  = appcenter_rows();
        $result = array_merge([
            'success'      => $fetch['success'],
            'message'      => $fetch['message'],
            'rows'         => $rows['rows'],
            'fetched_at'   => $rows['fetched_at'],
            'source'       => $rows['source'],
            'auto_enable'  => appcenter_auto_enable(),
            'app_version'  => (defined('APP_VERSION') ? APP_VERSION : '1.0.0'),
        ], appcenter_server_info());
        break;

    // ===== 安装 / 升级（id 必须存在于已拉取的目录缓存） =====
    case 'install':
        $itemId = strtolower(Security::cleanString((string)($_POST['item_id'] ?? ''), 50));
        if (!appcenter_id_ok($itemId)) {
            $result = ['success' => false, 'message' => '应用标识非法'];
            break;
        }
        $result = appcenter_install($itemId);
        break;

    // ===== 保存设置（预设服务器 + 自定义，自定义非空则优先） =====
    case 'save_config':
        $preset     = Security::cleanString((string)($_POST['preset'] ?? ''), 20);
        $customUrl  = Security::cleanString((string)($_POST['custom_url'] ?? ''), 500);
        $dlHosts    = Security::cleanString((string)($_POST['download_hosts'] ?? ''), 1000);
        $autoEnable = ($_POST['auto_enable'] ?? '') === '1';
        $tlsLoose   = ($_POST['tls_loose'] ?? '') === '1';

        // 预设 key 白名单
        if (!in_array($preset, ['official', 'third'], true)) {
            $result = ['success' => false, 'message' => '预设服务器参数非法'];
            break;
        }
        // 自定义地址（非空才校验；留空 = 使用勾选的预设）
        if ($customUrl !== '') {
            [$ok, $reason] = appcenter_check_server($customUrl);
            if (!$ok) {
                $result = ['success' => false, 'message' => '自定义地址不合法：' . $reason];
                break;
            }
            $customUrl = $reason; // check_server 成功时返回规整后的地址
        }
        // 校验白名单域名（逐个，逗号/空白分隔）
        $badHosts = [];
        foreach (preg_split('/[\s,]+/', $dlHosts) ?: [] as $h) {
            $h = strtolower(trim($h));
            if ($h === '') {
                continue;
            }
            if (Security::isInternalHost($h) || !preg_match('/^[a-z0-9.\-]+$/i', $h)) {
                $badHosts[] = $h;
            }
        }
        if (!empty($badHosts)) {
            $result = ['success' => false, 'message' => '下载白名单包含非法或内网域名：' . implode(', ', $badHosts)];
            break;
        }

        // 规范化白名单（与上方校验同一切分规则）
        $hostList = [];
        foreach (preg_split('/[\s,]+/', $dlHosts) ?: [] as $h) {
            $h = strtolower(trim($h));
            if ($h !== '') {
                $hostList[] = $h;
            }
        }
        $hostList = array_unique($hostList);

        Plugin::setConfig('appcenter', 'preset', $preset);
        Plugin::setConfig('appcenter', 'custom_url', $customUrl);
        Plugin::setConfig('appcenter', 'server_url', '');   // 清空旧版单地址字段（已迁移到 custom_url）
        Plugin::setConfig('appcenter', 'download_hosts', implode(',', $hostList));
        Plugin::setConfig('appcenter', 'auto_enable', $autoEnable ? '1' : '0');
        Plugin::setConfig('appcenter', 'tls_loose', $tlsLoose ? '1' : '0');

        // 服务器变更后强制丢弃旧目录缓存
        $cacheFile = appcenter_cache_file();
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }

        $admin = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : '?';
        appcenter_log('保存应用中心设置 admin=' . $admin
            . ' preset=' . $preset
            . ' custom=' . ($customUrl !== '' ? $customUrl : '-')
            . ' auto_enable=' . ($autoEnable ? '1' : '0')
            . ' tls_loose=' . ($tlsLoose ? '1' : '0'));

        $result = array_merge([
            'success' => true,
            'message' => '设置已保存：当前生效 ' . appcenter_server_label() . '，正在拉取目录…',
            'auto_enable' => $autoEnable,
        ], appcenter_server_info());
        break;

    default:
        $result = ['success' => false, 'message' => '未知操作'];
}

// 清理可能产生的任何意外输出，确保只返回 JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
