<?php
/**
 * 后台主题管理（独立页面）
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'themes';

$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/themes.php?err=' . urlencode('CSRF验证失败，请重试'));
    }

    $adminId = $_SESSION['admin_id'] ?? '未知';
    $ip = Security::getClientIP();
    $action = $_POST['action'] ?? '';

    if ($action === 'switch_theme') {
        $themeName = Security::cleanString($_POST['current_theme'] ?? '', 50);
        if (Theme::set($themeName)) {
            $msg = '主题已切换';
            Logger::log('admin_setting', "切换主题 admin_id={$adminId} IP={$ip} theme={$themeName}");
        } else {
            $msg = '主题切换失败：主题不存在';
            $msgType = 'error';
            Logger::log('admin_setting', "切换主题失败 admin_id={$adminId} IP={$ip} theme={$themeName} 原因=主题不存在");
        }
    }

    if ($msgType === 'success') {
        redirect('/admin/themes.php?ok=' . urlencode($msg));
    } else {
        redirect('/admin/themes.php?err=' . urlencode($msg));
    }
}

// GET 消息
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
    $msgType = 'success';
} elseif (isset($_GET['err'])) {
    $msg = Security::cleanString($_GET['err']);
    $msgType = 'error';
}

// 获取主题列表
$themes = Theme::scan();
$currentTheme = Theme::current();

adminHeader('主题管理');
if ($msg) { adminAlert($msg, $msgType); }
?>

<div class="card">
    <div class="card-header"><span class="card-title">主题设置</span></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="action" value="switch_theme">

      <p class="text-mb-db5c">点击选择要使用的主题，保存后立即生效</p>

      <?php foreach ($themes as $name => $info): ?>
      <label class="theme-item <?= $name === $currentTheme ? 'active' : '' ?>">
        <input type="radio" name="current_theme" value="<?= Security::eAttr($name) ?>" <?= $name === $currentTheme ? 'checked' : '' ?>>
        <div class="theme-screenshot">
          <?php if (!empty($info['screenshot']) && file_exists(__DIR__ . '/../' . $info['screenshot'])): ?>
          <img src="<?= Security::eAttr('/' . $info['screenshot']) ?>" alt="<?= Security::eAttr($name) ?>">
          <?php else: ?>
          <span>无预览图</span>
          <?php endif; ?>
        </div>
        <div class="theme-info">
          <h3><?= Security::e($info['title'] ?? $name) ?> <?= $name === $currentTheme ? '<span class="text-primary">(当前使用)</span>' : '' ?></h3>
          <p><?= Security::e($info['description'] ?? '') ?></p>
          <p class="text-8ee9">作者：<?= Security::e($info['author'] ?? '未知') ?> · 版本 <?= Security::e($info['version'] ?? '1.0') ?></p>
        </div>
      </label>
      <?php endforeach; ?>

      <?php if (empty($themes)): ?>
      <div class="alert alert-warning"><i class="ti ti-alert-triangle"></i> 未找到任何主题，请检查 templates/ 目录</div>
      <?php endif; ?>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 切换主题</button>
      </div>
    </form>
</div>

<?php adminFooter(); ?>
