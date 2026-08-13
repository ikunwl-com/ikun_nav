<?php
/**
 * 虫洞联盟插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"虫洞联盟"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"虫洞联盟"Tab 内容面板
 */

if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 后台设置页钩子：注入 Tab 导航和内容面板 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'wormhole' ? 'active' : '';
    echo '<a href="#tab-wormhole" class="settings-tab ' . $cls . '" onclick="switchTab(\'wormhole\', this)">虫洞联盟</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $rateLimit = (int) Plugin::config('wormhole', 'rate_limit', 1);
    $cls = $activeTab === 'wormhole' ? 'active' : '';
    ?>
<!-- 虫洞联盟 Tab（wormhole 插件注入） -->
<div id="tab-wormhole" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">虫洞联盟设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="wormhole">
      <input type="hidden" name="tab" value="wormhole">

      <div class="form-group">
        <label>虫洞联盟自动加入频率限制（次/小时）</label>
        <input type="number" class="form-input" name="plugin_wormhole_rate_limit" value="<?= $rateLimit ?>" min="0" max="100">
        <div class="form-help">同一域名每小时最多自动加入次数，0=不限制</div>
      </div>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
      </div>
    </form>
  </div>
</div>
<?php
});
