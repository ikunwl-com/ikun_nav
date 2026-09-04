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

    <p class="text-mb-db5c">点击主题卡片或「切换使用」按钮立即生效，无需另行保存</p>

    <?php foreach ($themes as $name => $info): $isCurrent = ($name === $currentTheme); ?>
    <form method="POST" class="theme-item<?= $isCurrent ? ' active' : '' ?>">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="action" value="switch_theme">
      <input type="hidden" name="current_theme" value="<?= Security::eAttr($name) ?>">
      <div class="theme-screenshot">
        <?php if (!empty($info['screenshot']) && file_exists(__DIR__ . '/../' . $info['screenshot'])): ?>
        <img src="<?= Security::eAttr('/' . $info['screenshot']) ?>" alt="<?= Security::eAttr($name) ?>">
        <?php else: ?>
        <span>无预览图</span>
        <?php endif; ?>
      </div>
      <div class="theme-info">
        <h3><?= Security::e($info['title'] ?? $name) ?>
          <?php
            // 来源标签：应用中心启用时按安装来源显示 官方/第三方/自定义
            $themeSrc = (function_exists('appcenter_origin') && function_exists('appcenter_display_label'))
                ? appcenter_display_label('theme', $name, false) : '';
            if ($themeSrc !== ''):
              $tColor = $themeSrc === '第三方' ? '#7c3aed' : ($themeSrc === '自定义' ? '#b45309' : '#16a34a');
              $tBg    = $themeSrc === '第三方' ? '#ede9fe' : ($themeSrc === '自定义' ? '#fef3c7' : '#dcfce7');
          ?>
          <span style="font-size:11px;font-weight:normal;color:<?= $tColor ?>;background:<?= $tBg ?>;padding:1px 6px;border-radius:3px;margin-left:4px;"><?= Security::e($themeSrc) ?></span>
          <?php endif; ?>
        </h3>
        <p><?= Security::e($info['description'] ?? '') ?></p>
        <p class="text-8ee9">作者：<?= Security::e($info['author'] ?? '未知') ?> · 版本 <?= Security::e($info['version'] ?? '1.0') ?></p>
      </div>
      <div class="theme-actions">
        <?php if ($isCurrent): ?>
        <button type="button" class="btn btn-success btn-sm" disabled><i class="ti ti-check"></i> 正在使用</button>
        <?php if (Theme::hasSettingsPage($name)): ?>
        <a href="/admin/theme.php?name=<?= Security::eAttr($name) ?>" class="btn btn-secondary btn-sm" title="进入该主题的后台设置页"><i class="ti ti-settings"></i> 设置</a>
        <?php endif; ?>
        <?php else: ?>
        <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-switch-horizontal"></i> 切换使用</button>
        <?php endif; ?>
      </div>
    </form>
    <?php endforeach; ?>

    <?php if (empty($themes)): ?>
    <div class="alert alert-warning"><i class="ti ti-alert-triangle"></i> 未找到任何主题，请检查 templates/ 目录</div>
    <?php endif; ?>
</div>

<script>
// 点击非当前主题卡片的任意位置（按钮/链接除外）直接切换该主题
(function() {
  document.querySelectorAll('form.theme-item:not(.active)').forEach(function(card) {
    card.addEventListener('click', function(e) {
      if (e.target.closest('button, a, input, select, textarea')) return;
      card.submit();
    });
  });
})();
</script>

<?php adminFooter(); ?>
