<?php
/**
 * 主题模板 - 搜索页
 * 变量由 Route.php 注入
 */

/**
 * 搜索结果高亮辅助函数
 * 先转义再高亮，防止 XSS
 */
function highlightSearch(string $text, string $keyword): string {
    if (empty($keyword)) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $kw = preg_quote(htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'), '/');
    return preg_replace('/(' . $kw . ')/iu', '<mark>$1</mark>', $escaped);
}

Theme::partial('header');
?>

<!-- Hero Header -->
<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-search"></i>
            </div>
            <div class="page-hero-info">
                <h1><?= $keyword ? '搜索结果' : '搜索站点' ?></h1>
                <?php if ($keyword): ?>
                <p class="page-hero-desc">找到 <?= (int)$total ?> 个相关结果</p>
                <?php else: ?>
                <p class="page-hero-desc">输入关键词搜索网站</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="page-hero-right">
            <div class="page-hero-stats">
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= $keyword ? (int)$total : (int)$siteStats['published'] ?></div>
                    <div class="hero-stat-label"><?= $keyword ? '搜索结果' : '收录站点' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 面包屑导航 -->
<div class="breadcrumb-bar">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">首页</a>
            <span class="separator">/</span>
            <span class="current"><?= $keyword ? '搜索: ' . Theme::e($keyword) : '搜索站点' ?></span>
        </nav>
    </div>
</div>

<div class="container search-page">
  <div class="main-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">全部分类</div>
      <a href="<?= Theme::url('home') ?>" class="sidebar-item">
        <i class="ti ti-home"></i><span>全部站点</span>
      </a>
      <?php foreach ($categories as $cat): ?>
      <a href="<?= Theme::url('category', ['slug' => $cat['slug']]) ?>" class="sidebar-item">
        <i class="ti ti-<?= Theme::eAttr($cat['icon']) ?>"></i>
        <span><?= Theme::e($cat['name']) ?></span>
        <span class="count"><?= (int)$cat['site_count'] ?></span>
      </a>
      <?php endforeach; ?>
    </aside>

    <!-- Content -->
    <div class="main-content">
      <!-- 搜索框 -->
      <form action="<?= Theme::url('search') ?>" method="GET" class="search-bar search-page-form">
        <div class="search-wrap">
          <i class="ti ti-search"></i>
          <input type="search" name="q" value="<?= Theme::eAttr($keyword) ?>" placeholder="搜索网站名称、标签..." autocomplete="off" id="searchInput">
        </div>
        <button type="submit" class="submit-btn"><i class="ti ti-search"></i> 搜索</button>
      </form>

      <?php if (!empty($sites)): ?>
  <div class="card-grid">
    <?php foreach ($sites as $site):
      $maxBr = getMaxBr($site);
      $siteTags = parseTags($site['tags'] ?? '[]');
    ?>
    <a href="<?= Theme::url('site', ['id' => (int)$site['id'], 'slug' => $site['category_slug'] ?? '']) ?>" class="card">
      <div class="card-header">
        <?= renderSiteIcon($site['name'], 36) ?>
        <div class="card-title-wrap">
          <span class="card-title"><?= highlightSearch($site['name'], $keyword) ?></span>
        </div>
        <?php if (!empty($showWeight) && $maxBr > 0): ?>
        <span class="weight-badge <?= getWeightBadgeClass($maxBr) ?>">BR <?= $maxBr ?></span>
        <?php endif; ?>
          </div>
          <div class="card-url"><?= Theme::e(getDisplayDomain($site['url'])) ?></div>
          <div class="card-desc"><?= highlightSearch($site['description'] ?: '', $keyword) ?></div>
          <div class="card-footer">
            <div class="card-tags">
              <?php foreach (array_slice($siteTags, 0, 2) as $tag): ?>
              <span class="tag"><?= highlightSearch($tag, $keyword) ?></span>
              <?php endforeach; ?>
            </div>
            <span class="card-views"><i class="ti ti-eye"></i><?= formatNumber((int)($site['views'] ?? 0)) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <?= renderPagination($page, $totalPages, Theme::url('search') . '?q=' . urlencode($keyword) . '&page=%d') ?>
      <?php endif; ?>
      <?php elseif ($keyword): ?>
      <div class="empty-state">
        <i class="ti ti-mood-sad"></i>
        <p>没有找到 "<strong><?= Theme::e($keyword) ?></strong>" 相关网站</p>
        <p class="hint">试试其他关键词，或者浏览全部分类</p>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <i class="ti ti-search"></i>
        <p>请输入搜索关键词</p>
        <p class="hint">支持搜索网站名称、标签、描述</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php Theme::partial('footer'); ?>
