<?php
/**
 * 伪静态设置插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"伪静态设置"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"伪静态设置"Tab 内容面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 后台设置页钩子：注入 Tab 导航和内容面板 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'rewrite' ? 'active' : '';
    echo '<a href="#tab-rewrite" class="settings-tab ' . $cls . '" onclick="switchTab(\'rewrite\', this)">伪静态设置</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $rewriteConfig = Rewrite::getConfig();
    $urlItems = Rewrite::getUrlItems();
    $defaultFormats = [
        'home'          => '/',
        'category'      => 'category/{%slug%}/',
        'category_page' => 'category/{%slug%}/page-{%page%}/',
        'site'          => 'site/{%id%}/',
        'search'        => 'search/',
        'submit'        => 'submit/',
        'wormhole'      => 'wormhole/',
        'article_list'  => 'articles/',
        'article'       => 'article/{%id%}/',
    ];
    $cls = $activeTab === 'rewrite' ? 'active' : '';
    ?>
<!-- 伪静态设置 Tab（rewrite 插件注入） -->
<div id="tab-rewrite" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">伪静态设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="tab" value="rewrite">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="rewrite">

      <div class="form-group">
        <label class="mb-2937">URL 模式</label>
        <div class="flex-gap-16-mt-8">
          <label class="flex-center-gap-00bf">
            <input type="radio" name="rewrite_mode" value="dynamic" <?= $rewriteConfig['mode'] === 'dynamic' ? 'checked' : '' ?> onchange="toggleRewriteFields(this)">
            <span><strong class="text-dark">动态模式</strong></span>
          </label>
          <label class="flex-center-gap-00bf">
            <input type="radio" name="rewrite_mode" value="rewrite" <?= $rewriteConfig['mode'] !== 'dynamic' ? 'checked' : '' ?> onchange="toggleRewriteFields(this)">
            <span><strong class="text-dark">伪静态模式</strong></span>
          </label>
        </div>
        <div class="form-help mt-8">动态模式使用 URL 参数（/?route=category&amp;slug=xxx），伪静态模式使用友好 URL（/category/xxx/，需服务器支持重写）</div>
      </div>

      <div id="rewrite-url-fields" style="<?= $rewriteConfig['mode'] === 'dynamic' ? 'display:none' : '' ?>">
        <p class="text-mb-ff51"><i class="ti ti-info-circle"></i><strong class="text-primary">URL 格式说明：</strong><span class="text-muted">留空使用默认格式</span></p>
        <?php foreach ($urlItems as $key => $item): ?>
        <div class="form-row mb-12">
          <div class="form-group flex-1">
            <label class="fs-13"><?= Security::e($item['label']) ?></label>
            <input type="text" class="form-input" name="url_format_<?= Security::eAttr($key) ?>"
              value="<?= Security::eAttr($rewriteConfig[$key] ?? '') ?>"
              placeholder="默认：<?= Security::eAttr($defaultFormats[$key] ?? '') ?>"
              class="font-mono">
            <?php if (!empty($item['placeholders'])): ?>
            <div class="form-help text-mt-ab33">可用变量：<?= Security::e($item['placeholders']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 规则代码展示（仅伪静态模式显示） -->
      <?php if ($rewriteConfig['mode'] !== 'dynamic'): ?>
      <div id="rewrite-server-rules" class="mb-20">
        <div class="flex-center-mb-gap-b1b7">
          <h4 class="text-b493"><i class="ti ti-code"></i> 服务器重写规则</h4>
          <span class="text-5728">请根据服务器类型选择对应规则复制到配置文件中</span>
        </div>

        <!-- Apache .htaccess -->
        <div class="mb-16">
          <div class="flex-center-mb-0c7b">
            <span class="text-1a99"><i class="ti ti-brand-apache"></i> Apache (.htaccess)</span>
            <button type="button" class="btn btn-sm text-p-59fb" onclick="copyCode('htaccess-code', this)">
              <i class="ti ti-copy"></i> 复制
            </button>
          </div>
          <pre id="htaccess-code" style="background:#f8f9fa;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;line-height:1.5;color:#495057;font-family:'Consolas','Monaco',monospace"><?= Security::e(Rewrite::generateHtaccess()) ?></pre>
        </div>

        <!-- Nginx -->
        <div>
          <div class="flex-center-mb-0c7b">
            <span class="text-1a99"><i class="ti ti-brand-nginx"></i> Nginx (server 块)</span>
            <button type="button" class="btn btn-sm text-p-59fb" onclick="copyCode('nginx-code', this)">
              <i class="ti ti-copy"></i> 复制
            </button>
          </div>
          <pre id="nginx-code" style="background:#f8f9fa;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;line-height:1.5;color:#495057;font-family:'Consolas','Monaco',monospace"><?= Security::e(Rewrite::generateNginx()) ?></pre>
        </div>
      </div>
      <?php endif; ?>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存伪静态设置</button>
      </div>
    </form>
  </div>
</div>
<?php
});
