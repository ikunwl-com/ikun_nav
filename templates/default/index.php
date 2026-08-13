<?php
/**
 * 主题模板 - 首页
 * 变量由 Route.php 注入
 */
Theme::partial('header');
?>

<!-- 首页大标题头 -->
<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-compass"></i>
            </div>
            <div class="page-hero-info">
                <h1><?= Theme::e($settings['site_name'] ?? '懒人导航') ?></h1>
                <p class="page-hero-desc"><?= Theme::e($settings['site_slogan'] ?? '精选优质站点，一个页面搞定日常上网需求') ?></p>
            </div>
        </div>
        <div class="page-hero-right">
            <div class="page-hero-stats">
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= (int)$siteStats['published'] ?></div>
                    <div class="hero-stat-label">收录站点</div>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= count($categories) ?></div>
                    <div class="hero-stat-label">分类</div>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= formatNumber((int)$siteStats['total_views']) ?></div>
                    <div class="hero-stat-label">总浏览</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
  <!-- Search -->
  <div class="search-bar">
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input type="search" placeholder="搜索站点名称、标签或网址..." id="searchInput">
    </div>
    <button class="search-btn" onclick="triggerSearch()">
      <i class="ti ti-search"></i> 搜索
    </button>
    <?php Plugin::hook('search_bar_after'); ?>
  </div>

  <!-- 排行榜切换 -->
  <div class="ranking-section">
    <div class="ranking-tabs">
      <button class="ranking-tab active" data-tab="newest">最新站点</button>
      <button class="ranking-tab" data-tab="hottest">最热站点</button>
      <button class="ranking-tab" data-tab="mostClicksOut">最多点出</button>
      <button class="ranking-tab" data-tab="mostClicksIn">最多点入</button>
    </div>
    <div class="ranking-content">
      <?php
      $tabs = [
        'newest' => '最新站点',
        'hottest' => '最热站点',
        'mostClicksOut' => '最多点出',
        'mostClicksIn' => '最多点入'
      ];
      foreach ($tabs as $tabKey => $tabName):
        $sites = $ranking[$tabKey] ?? [];
      ?>
      <div class="ranking-panel <?= $tabKey === 'newest' ? 'active' : '' ?>" data-panel="<?= $tabKey ?>">
        <?php if (!empty($sites)): ?>
        <div class="ranking-grid">
          <?php foreach ($sites as $index => $s):
            $maxBr = getMaxBr($s);
          ?>
          <a href="<?= Theme::url('site', ['id' => (int)$s['id'], 'slug' => $s['category_slug'] ?? '']) ?>" class="ranking-card">
            <span class="ranking-num <?= $index < 3 ? 'top' : '' ?>"><?= $index + 1 ?></span>
            <?= renderSiteIcon($s['name'], 36) ?>
            <div class="ranking-info">
              <div class="ranking-name"><?= Theme::e($s['name']) ?></div>
              <div class="ranking-url"><?= Theme::e(getDisplayDomain($s['url'])) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="ranking-empty">暂无数据</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php Plugin::hook('site_list_before'); ?>
  <!-- Main Layout -->
  <div class="main-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">全部分类</div>
      <?php Plugin::hook('sidebar_top'); ?>
      <?php foreach ($categories as $cat): ?>
      <a href="javascript:void(0)" onclick="switchCategory('<?= Theme::eAttr($cat['slug']) ?>', this)"
         class="sidebar-item <?= ($currentCat && $currentCat['id'] == $cat['id']) ? 'active' : '' ?>"
         data-slug="<?= Theme::eAttr($cat['slug']) ?>">
        <i class="ti ti-<?= Theme::eAttr($cat['icon']) ?>"></i>
        <span><?= Theme::e($cat['name']) ?></span>
        <span class="count"><?= (int)$cat['site_count'] ?></span>
      </a>
      <?php endforeach; ?>
      <?php Plugin::hook('sidebar_bottom'); ?>
    </aside>

    <!-- Content -->
    <main class="main-content">
      <div id="category-header">
        <div class="page-header">
          <h2 id="cat-name"><?= Theme::e($currentCat['name'] ?? '') ?></h2>
          <span class="result-count" id="cat-desc"><?= Theme::e($currentCat['seo_desc'] ?? '') ?></span>
        </div>
      </div>

      <div class="card-grid" id="site-grid">
        <?= renderSiteCards($currentSites ?? [], $showWeight) ?>
      </div>

      <div id="view-more" class="view-more-area">
        <a href="<?= Theme::url('category', ['slug' => $currentCat['slug'] ?? 'ai']) ?>" id="view-more-link" class="view-more-btn">
          查看更多 <i class="ti ti-chevron-right"></i>
        </a>
      </div>
    </main>
  </div>
<?php Plugin::hook('site_list_after'); ?>
</div>

<?php Theme::partial('footer'); ?>
