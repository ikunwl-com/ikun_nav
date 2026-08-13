<?php
/**
 * 广告管理插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"广告管理"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"广告管理"Tab 内容面板
 */

// 安全检查：阻止直接访问
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// ========== 后台设置页钩子：注入广告管理 Tab ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'ad' ? 'active' : '';
    echo '<a href="#tab-ad" class="settings-tab ' . $cls . '" onclick="switchTab(\'ad\', this)">广告管理</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $adPositions = [
        'site_list_before'  => '首页列表前',
        'site_list_after'   => '首页列表后',
        'sidebar_top'       => '侧边栏顶部',
        'sidebar_bottom'    => '侧边栏底部',
        'before_content'    => '详情内容前',
        'after_content'     => '详情内容后',
    ];
    $cls = $activeTab === 'ad' ? 'active' : '';
    ?>
<!-- 广告管理 Tab（ad 插件注入） -->
<div id="tab-ad" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">广告位管理</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="ad">
      <input type="hidden" name="tab" value="ad">

      <p class="form-help" style="margin-bottom:16px;">每个广告位填写 HTML 代码，留空则不显示。支持 HTML 和内联样式。</p>

      <?php foreach ($adPositions as $key => $label): ?>
      <div class="form-group">
        <label><?= Security::e($label) ?> <code style="font-size:11px;color:#999;"><?= Security::e($key) ?></code></label>
        <textarea name="plugin_ad_<?= Security::eAttr($key) ?>" class="form-textarea font-mono-sm" rows="3" placeholder="在此填写 HTML 广告代码"><?= Security::e(Plugin::config('ad', $key, '')) ?></textarea>
      </div>
      <?php endforeach; ?>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存广告设置</button>
      </div>
    </form>
  </div>
</div>
<?php
});
