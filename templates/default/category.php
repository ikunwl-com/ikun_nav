<?php
/**
 * 主题模板 - 分类页
 * 变量由 Route.php 注入
 */
Theme::partial('header');
?>

<!-- 分类页大标题头 -->
<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-<?= Theme::eAttr($category['icon'] ?: 'folder') ?>"></i>
            </div>
            <div class="page-hero-info">
                <h1><?= Theme::e($category['name']) ?></h1>
                <p class="page-hero-desc"><?= Theme::e($category['seo_desc'] ?: '共收录 ' . (int)$total . ' 个站点') ?></p>
            </div>
        </div>
        <div class="page-hero-right">
            <div class="page-hero-stats">
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= (int)$total ?></div>
                    <div class="hero-stat-label">收录站点</div>
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
            <span class="current"><?= Theme::e($category['name']) ?></span>
        </nav>
    </div>
</div>

<div class="container">
  <!-- Sort Bar -->
  <div class="sort-bar">
    <span class="sort-label">排序：</span>
    <a href="<?= Theme::url('category', ['slug' => $slug, 'sort' => 'newest']) ?>" class="sort-btn <?= $sort === 'newest' ? 'active' : '' ?>">最新收录</a>
    <a href="<?= Theme::url('category', ['slug' => $slug, 'sort' => 'br']) ?>" class="sort-btn <?= $sort === 'br' ? 'active' : '' ?>">按权重</a>
    <a href="<?= Theme::url('category', ['slug' => $slug, 'sort' => 'views']) ?>" class="sort-btn <?= $sort === 'views' ? 'active' : '' ?>">按浏览</a>
    <a href="<?= Theme::url('category', ['slug' => $slug, 'sort' => 'clicks']) ?>" class="sort-btn <?= $sort === 'clicks' ? 'active' : '' ?>">按点击</a>
  </div>

  <!-- Content -->
  <div class="card-grid">
    <?php if (!empty($sites)): ?>
    <?php foreach ($sites as $site):
      $siteTags = parseTags($site['tags'] ?? '[]');
    ?>
    <a href="<?= Theme::url('site', ['id' => (int)$site['id'], 'slug' => $site['category_slug'] ?? '']) ?>" class="card">
      <div class="card-header">
        <?= renderSiteIcon($site['name'], 36) ?>
        <div class="card-title-wrap">
          <span class="card-title"><?= Theme::e($site['name']) ?></span>
        </div>
        <?php $maxBr = getMaxBr($site); if (!empty($showWeight) && $maxBr > 0): ?>
        <span class="weight-badge <?= getWeightBadgeClass($maxBr) ?>">BR <?= $maxBr ?></span>
        <?php endif; ?>
      </div>
      <div class="card-url"><?= Theme::e(getDisplayDomain($site['url'])) ?></div>
      <div class="card-desc"><?= Theme::e($site['description'] ?: '') ?></div>
      <div class="card-footer">
        <div class="card-tags">
          <?php foreach (array_slice($siteTags, 0, 2) as $tag): ?>
          <span class="tag"><?= Theme::e($tag) ?></span>
          <?php endforeach; ?>
        </div>
        <span class="card-views"><i class="ti ti-eye"></i><?= formatNumber((int)($site['views'] ?? 0)) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state empty-state-grid">
      <i class="ti ti-inbox"></i>
      <p class="empty-title">该分类暂无站点</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <?php
  $pgTemplate = Theme::url('category_page', ['slug' => $slug, 'page' => '%d', 'sort' => $sort]);
  echo renderPagination($page, $totalPages, $pgTemplate);
  ?>
  <?php endif; ?>
</div>

<?php Theme::partial('footer'); ?>
