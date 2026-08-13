<?php
/**
 * 友链来访自动收录插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"友链收录"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"友链收录"Tab 内容面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// ========== 后台设置页钩子：注入 Tab 导航和内容面板 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'autolink' ? 'active' : '';
    echo '<a href="#tab-autolink" class="settings-tab ' . $cls . '" onclick="switchTab(\'autolink\', this)">友链收录</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $catModel = new CategoryModel();
    $allCategories = $catModel->getAll();
    $autolinkEnabled = setting('autolink_enable', '0') === '1';
    $autolinkNeedReview = setting('autolink_need_review', '1') === '1';
    $autolinkDefaultCat = (int)setting('autolink_default_category', '0');
    $autolinkBannedWords = setting('autolink_banned_words', '');
    $cls = $activeTab === 'autolink' ? 'active' : '';
    ?>
<!-- 友链收录 Tab（auto-link 插件注入） -->
<div id="tab-autolink" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">友链自动收录</span></div>

    <div class="alert" style="background:#f0f7ff;border:1px solid #b3d9ff;padding:12px;border-radius:6px;margin-bottom:16px">
      <h4 style="margin:0 0 8px 0;font-size:14px"><i class="ti ti-info-circle"></i> 友链自动收录说明</h4>
      <ul style="margin:0;padding-left:20px;font-size:13px;color:#495057;line-height:1.8">
        <li>当用户从<strong>挂了本站友链</strong>的外站点击进入时，自动检测来路并收录对方站点</li>
        <li>系统会<strong>抓取对方首页</strong>验证是否确实包含指向本站的链接，未挂友链不收录</li>
        <li>对方 TDK（标题/描述/关键词）中包含<strong>违禁词</strong>时不收录</li>
        <li>已收录（published）或待审核（pending）状态的站点<strong>不会重复收录</strong></li>
        <li>收录后自动获取对方 TDK 和搜索引擎权重</li>
        <li>前台首页通过 JS 异步触发，<strong>不影响页面加载速度</strong></li>
      </ul>
    </div>

    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="autolink">
      <input type="hidden" name="tab" value="autolink">

      <div class="form-group">
        <label>
          <input type="checkbox" name="autolink_enable" value="1" <?= $autolinkEnabled ? 'checked' : '' ?> class="wh-18">
          开启友链自动收录
        </label>
        <div class="form-help">开启后，前台首页会异步检测来路，自动收录挂了本站友链的网站</div>
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" name="autolink_need_review" value="1" <?= $autolinkNeedReview ? 'checked' : '' ?> class="wh-18">
          收录后需要审核
        </label>
        <div class="form-help">开启后自动收录的站点状态为"待审核"，需在后台审核后发布；关闭则直接发布</div>
      </div>

      <div class="form-group">
        <label>默认收录分类</label>
        <select name="autolink_default_category" class="form-input">
          <option value="0">-- 自动匹配（取第一个可见分类）--</option>
          <?php foreach ($allCategories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $autolinkDefaultCat === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= Security::e($cat['name']) ?>（<?= Security::e($cat['slug']) ?>）
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-help">自动收录的站点默认放入此分类，审核时可再调整</div>
      </div>

      <div class="form-group">
        <label>违禁词黑名单</label>
        <textarea name="autolink_banned_words" class="form-input" rows="6" placeholder="每行一个违禁词，或用逗号分隔&#10;例如：&#10;赌博&#10;色情&#10;博彩"><?= Security::e($autolinkBannedWords) ?></textarea>
        <div class="form-help">对方网站的标题/描述/关键词中包含这些词时不收录。每行一个或用逗号分隔</div>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy"></i> 保存设置
      </button>
    </form>
  </div>
</div>
<?php
});
