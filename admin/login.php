<?php
/**
 * 后台登录页
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 获取站点名称
$settingsModel = new SettingsModel();
$siteName = $settingsModel->get('site_name', '管理后台');

if (!isInstalled()) {
    redirect('/install/');
}

Security::initSession();

// 已登录直接跳后台
if (isset($_SESSION['admin_id'])) {
    redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = Security::getClientIP();
    $redirectErr = '';

    // CSRF校验
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $redirectErr = '表单验证失败，请刷新页面重试';
    } else {
        $username = Security::cleanString($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';
        $captcha = Security::cleanString($_POST['captcha'] ?? '', 10);

        // 验证验证码（根据后台设置决定是否启用）
        $captchaEnabled = setting('enable_captcha', '0') === '1';
        if ($captchaEnabled && (empty($captcha) || strtolower($captcha) !== strtolower($_SESSION['admin_captcha'] ?? ''))) {
            Logger::log('admin_auth', "登录失败 IP={$ip} 原因=验证码不正确");
            $redirectErr = '验证码不正确';
        } elseif (empty($username) || empty($password)) {
            Logger::log('admin_auth', "登录失败 IP={$ip} 原因=用户名或密码为空");
            $redirectErr = '请输入用户名和密码';
        } else {
            // 登录频率限制
            if (!Security::rateLimit('admin_login_' . $ip, 5, 300)) {
                Logger::log('admin_auth', "登录失败 IP={$ip} username=" . Security::cleanString($username) . " 原因=频率限制");
                $redirectErr = '登录尝试过于频繁，请 5 分钟后再试';
            } else {
                $authModel = new AuthModel();
                $admin = $authModel->verify($username, $password);

                if ($admin) {
                    // 登录成功
                    Security::regenerateSession();
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_login_time'] = time();

                    // 记录登录日志
                    $authModel->logLogin((int)$admin['id'], $ip);
                    Logger::log('admin_auth', "登录成功 admin_id=" . (int)$admin['id'] . " username=" . $admin['username'] . " IP={$ip}");

                    // 清除验证码
                    unset($_SESSION['admin_captcha']);

                    // 跳转
                    $redirect = $_SESSION['redirect_after_login'] ?? '/admin/';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect);
                } else {
                    Logger::log('admin_auth', "登录失败 IP={$ip} username={$username} 原因=用户名或密码错误");
                    $redirectErr = '用户名或密码错误';
                }
            }
        }
    }

    // PRG：登录失败也跳转为 GET，防止刷新重复提交
    unset($_SESSION['admin_captcha']);
    redirect('/admin/login.php?err=' . urlencode($redirectErr));
}

// 验证码是否启用
$captchaEnabled = setting('enable_captcha', '0') === '1';

// 仅在启用验证码时生成（captcha.php 负责实际图像生成，session 中存储验证码）
if ($captchaEnabled) {
    $captchaCode = '';
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 4; $i++) {
        $captchaCode .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['admin_captcha'] = $captchaCode;
}

// 从 URL 参数读取 PRG 消息
$error = '';
if (isset($_GET['err']) && $_GET['err'] !== '') {
    $error = Security::cleanString($_GET['err'], 200);
}

$timeoutMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $timeoutMsg = '登录已过期，请重新登录';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>登录 - 管理后台</title>
<link rel="stylesheet" href="/assets/css/tabler-icons.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">

<div class="login-card">
  <div class="login-header">
    <div class="icon"><i class="ti ti-compass"></i></div>
    <h1><?= Security::e($siteName) ?> 管理后台</h1>
    <p>请登录以继续</p>
  </div>
  <div class="login-body">
    <?php if ($timeoutMsg): ?>
    <div class="timeout-msg"><?= Security::e($timeoutMsg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="error-msg"><i class="ti ti-alert-circle"></i> <?= Security::e($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= Security::e(Security::generateCSRFToken()) ?>">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" class="form-input" name="username" required autocomplete="username" autofocus>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" class="form-input" name="password" required autocomplete="current-password">
      </div>
      <?php if ($captchaEnabled): ?>
      <div class="form-group">
        <label>验证码</label>
        <div class="captcha-row">
          <input type="text" class="form-input" name="captcha" required autocomplete="off" maxlength="4" placeholder="输入验证码">
          <img class="captcha-img" src="/admin/captcha.php" onclick="this.src='/admin/captcha.php?t='+Date.now()" title="点击刷新验证码" alt="验证码">
        </div>
      </div>
      <?php endif; ?>
      <button type="submit" class="login-btn"><i class="ti ti-login"></i> 登录</button>
    </form>
    <div class="login-footer"><a href="/">← 返回前台</a></div>
  </div>
</div>

</body>
</html>
