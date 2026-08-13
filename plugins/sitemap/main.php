<?php
/**
 * 网站地图插件 - 设置面板
 * 仅在插件启用时由 Plugin::init() 加载
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"网站地图"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"网站地图"Tab 内容面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 后台设置页钩子：注入 Tab 导航和内容面板 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'sitemap' ? 'active' : '';
    echo '<a href="#tab-sitemap" class="settings-tab ' . $cls . '" onclick="switchTab(\'sitemap\', this)">网站地图</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $sitemapModel = new SitemapModel();
    $sitemapStatus = $sitemapModel->getStatus();
    $sitemapUrl = $sitemapModel->getSitemapUrl();
    $robotsUrl = str_replace('sitemap.xml', 'robots.txt', $sitemapUrl);
    $cls = $activeTab === 'sitemap' ? 'active' : '';
    ?>
<!-- 网站地图 Tab（sitemap 插件注入） -->
<div id="tab-sitemap" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">网站地图 (Sitemap)</span></div>

    <!-- 状态展示 -->
    <div class="form-group">
      <label>当前状态</label>
      <table class="table" style="width:100%;border-collapse:collapse">
        <tbody>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef;width:180px"><strong>Sitemap 地址</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef">
              <a href="<?= Security::eAttr($sitemapUrl) ?>" target="_blank"><?= Security::e($sitemapUrl) ?></a>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>robots.txt 地址</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef">
              <a href="<?= Security::eAttr($robotsUrl) ?>" target="_blank"><?= Security::e($robotsUrl) ?></a>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>是否已生成</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef">
              <?php if ($sitemapStatus['exists']): ?>
                <span class="text-success"><i class="ti ti-circle-check"></i> 已生成</span>
              <?php else: ?>
                <span class="text-warning"><i class="ti ti-alert-triangle"></i> 未生成</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($sitemapStatus['exists']): ?>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>URL 数量</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><?= $sitemapStatus['url_count'] ?> 个</td>
          </tr>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>上次生成时间</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><?= Security::e($sitemapStatus['last_generated']) ?></td>
          </tr>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>文件大小</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><?= round($sitemapStatus['file_size'] / 1024, 2) ?> KB</td>
          </tr>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>缓存有效期</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><?= $sitemapStatus['cache_ttl'] / 3600 ?> 小时（过期后自动重新生成）</td>
          </tr>
          <?php if ($sitemapStatus['is_index']): ?>
          <tr>
            <td style="padding:8px 12px;border:1px solid #e9ecef"><strong>模式</strong></td>
            <td style="padding:8px 12px;border:1px solid #e9ecef">分片模式（URL 数量超过 50000，已自动拆分）</td>
          </tr>
          <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- 手动生成 -->
    <div class="form-group">
      <form method="POST" action="/admin/settings.php">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="section" value="sitemap">
        <input type="hidden" name="tab" value="sitemap">
        <input type="hidden" name="sitemap_action" value="generate">
        <button type="submit" class="btn btn-primary" onclick="return confirm('确定要立即重新生成 Sitemap 吗？')">
          <i class="ti ti-refresh"></i> 立即重新生成
        </button>
      </form>
    </div>

    <div class="alert" style="background:#f8f9fa;border:1px solid #e9ecef;padding:12px;border-radius:6px">
      <h4 style="margin:0 0 8px 0;font-size:14px"><i class="ti ti-info-circle"></i> Sitemap 说明</h4>
      <ul style="margin:0;padding-left:20px;font-size:13px;color:#495057;line-height:1.8">
        <li>Sitemap 会自动收录所有<strong>已发布</strong>的站点详情页、分类页（含分页）、首页等 URL</li>
        <li>缓存有效期 6 小时，过期后访问 <code>/sitemap.xml</code> 会自动重新生成</li>
        <li>支持伪静态模式：URL 格式会根据当前伪静态设置自动适配</li>
        <li><code>robots.txt</code> 会自动包含 Sitemap 声明，引导搜索引擎抓取</li>
        <li>建议将 Sitemap 地址提交到<a href="https://ziyuan.baidu.com" target="_blank">百度搜索资源平台</a>和<a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>
        <li>当 URL 数量超过 50000 时，会自动启用分片模式（生成 sitemap-index.xml + 多个分片文件）</li>
      </ul>
    </div>
  </div>
</div>
<?php
});
