<?php
/**
 * 懒人导航 - 一键安装向导
 * 步骤：环境检查 → 数据库配置 → 管理员设置 → 建表+导入数据 → 生成配置 → 完成
 */

session_start();

// 检查是否已安装
$configFile = __DIR__ . '/../config.php';
$lockFile = __DIR__ . '/../install.lock';

if (file_exists($lockFile)) {
    die('<div style="text-align:center;padding:80px;font-family:sans-serif;">
        <h2 style="color:#667eea">系统已安装</h2>
        <p style="color:#888">如需重新安装，请删除 <code>根目录/install.lock</code> 文件和 <code>根目录/config.php</code> 文件</p>
        <a href="/" style="color:#667eea">→ 返回首页</a>
    </div>');
}

$step = $_GET['step'] ?? '1';
$step = max(1, min(5, (int)$step));

// CSRF token
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['install_csrf'];

function verifyInstallCsrf(?string $token): bool
{
    return !empty($token) && hash_equals($_SESSION['install_csrf'] ?? '', $token);
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// POST 处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyInstallCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF 校验失败，请刷新页面重试';
    } elseif ($action === 'install') {
        // 执行安装
        require __DIR__ . '/do_install.php';
        exit;
    }
}

// 环境检查
$envCheck = [
    'PHP 版本 >= 7.1' => version_compare(PHP_VERSION, '7.1.0', '>='),
    'PDO 扩展' => extension_loaded('pdo'),
    'PDO MySQL 驱动' => extension_loaded('pdo_mysql'),
    'mbstring 扩展' => extension_loaded('mbstring'),
    'json 扩展' => extension_loaded('json'),
    'cURL 扩展' => extension_loaded('curl'),
    '根目录/ 目录可写' => is_writable(__DIR__ . '/..'),
];

$allPass = !in_array(false, $envCheck, true);

// 获取推荐表前缀
$defaultPrefix = 'nav_';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 - 懒人导航</title>
<link rel="stylesheet" href="/assets/css/tabler-icons.css">
<link rel="stylesheet" href="/install/css/install.css">
</head>
<body>

<div class="install-card">
    <div class="install-header">
        <h1><i class="ti ti-compass"></i> 懒人导航安装向导</h1>
        <p>几步完成安装，快速上线你的导航站</p>
    </div>
    <div class="install-body">
        <div class="steps">
            <div class="step-dot <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'done' : '' ?>"></div>
            <div class="step-dot <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'done' : '' ?>"></div>
            <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-error"><i class="ti ti-alert-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- 第一步：环境检查 -->
        <div class="section-title"><i class="ti ti-checkup-list" style="color:#667eea"></i> 环境检查</div>
        <ul class="env-list">
            <?php foreach ($envCheck as $name => $ok): ?>
            <li>
                <span><?= e($name) ?></span>
                <span class="env-status <?= $ok ? 'ok' : 'fail' ?>">
                    <i class="ti ti-<?= $ok ? 'circle-check' : 'circle-x' ?>"></i>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="divider"></div>
        <?php if ($allPass): ?>
        <div class="alert alert-info"><i class="ti ti-info-circle"></i> 环境检查全部通过，可以继续安装</div>
        <?php else: ?>
        <div class="alert alert-error"><i class="ti ti-alert-triangle"></i> 存在检查项未通过，请修复后刷新页面</div>
        <?php endif; ?>
        <div class="actions">
            <button class="btn btn-primary" <?= !$allPass ? 'disabled' : '' ?> onclick="location.href='?step=2'">
                下一步 <i class="ti ti-arrow-right"></i>
            </button>
        </div>

        <?php elseif ($step == 2): ?>
        <!-- 第二步：数据库配置 + 管理员设置 -->
        <form method="POST" action="?step=2" id="installForm">
            <input type="hidden" name="action" value="install">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="section-title"><i class="ti ti-database" style="color:#667eea"></i> 数据库配置</div>
            <div class="form-row">
                <div class="form-group">
                    <label>数据库主机</label>
                    <input type="text" class="form-input" name="db_host" value="127.0.0.1" required>
                </div>
                <div class="form-group" style="max-width:120px">
                    <label>端口</label>
                    <input type="number" class="form-input" name="db_port" value="3306" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>数据库名</label>
                    <input type="text" class="form-input" name="db_name" placeholder="如：lazy_nav" required>
                </div>
                <div class="form-group">
                    <label>表前缀</label>
                    <input type="text" class="form-input" name="db_prefix" value="<?= e($defaultPrefix) ?>" placeholder="nav_">
                    <div class="hint">建议使用字母+下划线，如 nav_</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>数据库用户名</label>
                    <input type="text" class="form-input" name="db_user" required>
                </div>
                <div class="form-group">
                    <label>数据库密码</label>
                    <input type="password" class="form-input" name="db_pass">
                </div>
            </div>

            <div class="divider"></div>

            <div class="section-title"><i class="ti ti-user-shield" style="color:#667eea"></i> 管理员设置</div>
            <div class="form-group">
                <label>管理员用户名</label>
                <input type="text" class="form-input" name="admin_user" placeholder="3-20位字母数字下划线" pattern="[a-zA-Z0-9_]{3,20}" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>管理员密码</label>
                    <input type="password" class="form-input" name="admin_pass" id="adminPass" required>
                    <div class="password-hint">至少 8 位，需包含字母和数字</div>
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" class="form-input" name="admin_pass_confirm" id="adminPassConfirm" required>
                </div>
            </div>
            <div class="form-group">
                <label>管理员邮箱（可选）</label>
                <input type="email" class="form-input" name="admin_email" placeholder="admin@example.com">
            </div>

            <div class="actions">
                <button type="button" class="btn btn-primary" onclick="location.href='?step=1'" style="background:#f5f5f5;color:#555;box-shadow:none">
                    <i class="ti ti-arrow-left"></i> 上一步
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="ti ti-database-import"></i> 开始安装
                </button>
            </div>
        </form>
        <script>
        document.getElementById('installForm').addEventListener('submit', function(e) {
            var pass = document.getElementById('adminPass').value;
            var passConfirm = document.getElementById('adminPassConfirm').value;
            if (pass !== passConfirm) {
                e.preventDefault();
                alert('两次输入的密码不一致');
                return;
            }
            if (pass.length < 8) {
                e.preventDefault();
                alert('密码至少 8 位');
                return;
            }
            if (!/[a-zA-Z]/.test(pass) || !/[0-9]/.test(pass)) {
                e.preventDefault();
                alert('密码需包含字母和数字');
                return;
            }
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="ti ti-loader ti-spin"></i> 安装中...';
        });
        </script>

        <?php elseif ($step == 3): ?>
        <!-- 第三步：安装完成 -->
        <div style="text-align:center;padding:20px 0">
            <div class="success-icon"><i class="ti ti-check"></i></div>
            <h2 style="font-size:22px;margin-bottom:8px">安装成功！</h2>
            <p style="color:#888;font-size:14px;margin-bottom:24px">懒人导航已成功安装，默认数据已导入</p>
            <div style="background:#f5f7fa;border-radius:12px;padding:20px;margin-bottom:24px;text-align:left">
                <div style="font-size:13px;color:#888;margin-bottom:8px"><i class="ti ti-shield-check" style="color:#10b981"></i> 安全提示：</div>
                <div style="font-size:13px;color:#555;line-height:1.8">
                    &bull; 安装锁定文件已生成：<code>根目录/install.lock</code><br>
                    &bull; 配置文件已生成：<code>根目录/config.php</code>（已被 .htaccess 保护）<br>
                    &bull; 建议删除 <code>install/</code> 目录以确保安全
                </div>
            </div>
            <div class="actions" style="justify-content:center">
                <a href="/" class="btn btn-primary"><i class="ti ti-home"></i> 访问首页</a>
                <a href="/admin/" class="btn btn-primary" style="background:#10b981;box-shadow:0 2px 8px rgba(16,185,129,0.3)"><i class="ti ti-login"></i> 进入后台</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
