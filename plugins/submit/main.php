<?php
/**
 * 提交网站收录插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"提交收录"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"提交收录"Tab 内容面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 后台设置页钩子：注入 Tab 导航和内容面板 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'submit' ? 'active' : '';
    echo '<a href="#tab-submit" class="settings-tab ' . $cls . '" onclick="switchTab(\'submit\', this)">提交收录</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $rules = Plugin::config('submit', 'rules', '');
    $needReview = Plugin::config('submit', 'need_review', '1') === '1';
    $enableSubmit = Plugin::config('submit', 'enable_submit', '1') === '1';
    $showWeight = Plugin::config('submit', 'show_weight', '1') === '1';
    $rateLimit = (int) Plugin::config('submit', 'rate_limit', 5);
    $tdkRateLimit = (int) Plugin::config('submit', 'tdk_rate_limit', 10);
    $defaultCategory = (int) Plugin::config('submit', 'default_category', 0);
    $requireCategory = Plugin::config('submit', 'require_category', '1') === '1';
    $cls = $activeTab === 'submit' ? 'active' : '';

    // 获取当前设置的收录分类ID列表
    $categoryIdsStr = Plugin::config('submit', 'category_ids', '');
    $categoryIds = [];
    if ($categoryIdsStr) {
        foreach (explode(',', $categoryIdsStr) as $cid) {
            $cid = (int)trim($cid);
            if ($cid > 0) $categoryIds[] = $cid;
        }
    }

    // 获取导航分类列表
    $catModel = new CategoryModel();
    $navCategories = $catModel->getAll();
    ?>
<!-- 提交收录 Tab（submit 插件注入） -->
<div id="tab-submit" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">提交收录设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="submit">
      <input type="hidden" name="tab" value="submit">

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="plugin_submit_enable" value="1" <?= $enableSubmit ? 'checked' : '' ?> class="wh-18">
          <span>允许用户提交站点</span>
        </label>
      </div>

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="plugin_submit_need_review" value="1" <?= $needReview ? 'checked' : '' ?> class="wh-18">
          <span>提交后需要审核</span>
        </label>
        <div class="form-help">开启后用户提交的站点需要管理员审核通过才会显示；关闭则直接发布</div>
      </div>

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="plugin_submit_show_weight" value="1" <?= $showWeight ? 'checked' : '' ?> class="wh-18">
          <span>前台显示站点权重</span>
        </label>
      </div>

      <!-- 收录分类设置 -->
      <div class="form-group">
        <label>收录分类</label>
        <div class="form-help" style="margin-bottom:12px;">
          勾选哪些导航分类用于收录站点。前台提交表单、后台管理将只显示这些分类。
        </div>
        <div class="category-checkbox-group" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:10px;margin-top:8px;">
          <?php foreach ($navCategories as $cat): ?>
          <label class="category-checkbox-item" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;cursor:pointer;transition:all .2s;">
            <input type="checkbox" name="plugin_submit_category_ids[]" value="<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $categoryIds) ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer;">
            <span style="flex:1;"><?= Security::e($cat['name']) ?></span>
            <span style="color:#999;font-size:12px;">(<?= (int)$cat['site_count'] ?>)</span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label>默认提交分类</label>
        <select class="form-input" name="plugin_submit_default_category">
          <option value="0" <?= $defaultCategory === 0 ? 'selected' : '' ?>>不指定（用户自由选择）</option>
          <?php foreach ($navCategories as $cat): ?>
          <?php if (empty($categoryIds) || in_array((int)$cat['id'], $categoryIds)): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= $defaultCategory === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= Security::e($cat['name']) ?>（<?= (int)$cat['site_count'] ?> 站）
          </option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <div class="form-help">在已勾选的收录分类中设置默认分类，前台提交表单自动选中</div>
      </div>

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="plugin_submit_require_category" value="1" <?= $requireCategory ? 'checked' : '' ?> class="wh-18">
          <span>强制要求选择分类</span>
        </label>
        <div class="form-help">开启后用户提交站点必须选择分类，关闭则分类为可选</div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>提交站点频率限制（次/小时）</label>
          <input type="number" class="form-input" name="plugin_submit_rate_limit" value="<?= $rateLimit ?>" min="0" max="100">
          <div class="form-help">每个IP每小时最多提交次数，0=不限制</div>
        </div>
        <div class="form-group">
          <label>TDK获取频率限制（次/分钟）</label>
          <input type="number" class="form-input" name="plugin_submit_tdk_rate_limit" value="<?= $tdkRateLimit ?>" min="0" max="200">
          <div class="form-help">每个IP每分钟最多获取TDK次数，0=不限制</div>
        </div>
      </div>

      <div class="form-group">
        <label>收录规则（支持 HTML）</label>
        <textarea name="plugin_submit_rules" class="form-textarea font-mono-sm" rows="10" placeholder="在此填写提交页的收录规则，支持 HTML 标签"><?= Security::e($rules) ?></textarea>
        <div class="form-help">显示在提交页表单上方。可使用 &lt;ol&gt;、&lt;ul&gt;、&lt;a&gt; 等 HTML 标签，留空则显示默认规则</div>
      </div>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
      </div>
    </form>
  </div>
</div>
<?php
});
