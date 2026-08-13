<?php
/**
 * 退出登录 - POST方式
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 先启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// POST方式退出（CSRF保护），GET请求直接跳转
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/');
        exit; // 安全：确保重定向后终止执行
    }
} else {
    // GET 请求不允许执行登出操作，直接跳转
    redirect('/admin/');
    exit;
}

$adminId = $_SESSION['admin_id'] ?? 0;
$adminUsername = $_SESSION['admin_username'] ?? '未知';
$ip = Security::getClientIP();
Logger::log('admin_auth', "登出 admin_id={$adminId} username={$adminUsername} IP={$ip}");

// 清除会话数据
$_SESSION = [];

// 删除会话 cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

session_destroy();
redirect('/admin/login.php');
