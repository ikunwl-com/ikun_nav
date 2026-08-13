<?php
/**
 * 文章列表页模板
 * 支持按分类筛选
 */
Theme::partial('header');

// 获取插件配置的文章分类ID列表
$articleCatIdsStr = Plugin::config('article', 'category_ids', '');
$articleCatIds = [];
if ($articleCatIdsStr) {
    foreach (explode(',', $articleCatIdsStr) as $cid) {
        $cid = (int)trim($cid);
        if ($cid > 0) $articleCatIds[] = $cid;
    }
}

// 获取文章分类列表（只统计已配置的文章分类）
$catModel = new CategoryModel();
$allCategories = $catModel->getAll();
$categories = [];
$articleModel = new ArticleModel();
$allArticles = $articleModel->getList(1, 1000, 'published');
foreach ($allArticles as $art) {
    $cat = trim($art['category'] ?? '');
    if ($cat && !isset($categories[$cat])) {
        $categories[$cat] = 0;
    }
    if ($cat) {
        $categories[$cat]++;
    }
}
$currentCategory = $_GET['cat'] ?? '';

// 如果指定了分类，只显示该分类文章
$displayArticles = [];
if ($currentCategory) {
    foreach ($articles as $art) {
        if (trim($art['category'] ?? '') === $currentCategory) {
            $displayArticles[] = $art;
        }
    }
} else {
    $displayArticles = $articles;
}
?>

<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-article"></i>
            </div>
            <div class="page-hero-info">
                <h1>文章专栏</h1>
                <p class="page-hero-desc">分享优质内容与行业见解，共 <?= $total ?> 篇文章</p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="breadcrumb-bar">
        <nav class="breadcrumb">
            <a href="/">首页</a>
            <span class="separator">/</span>
            <span class="current">文章专栏</span>
            <?php if ($currentCategory): ?>
            <span class="separator">/</span>
            <span class="current"><?= Security::e($currentCategory) ?></span>
            <?php endif; ?>
        </nav>
    </div>

    <!-- 分类筛选 -->
    <?php
    // 如果配置了文章分类，只保留配置中的分类
    if (!empty($articleCatIds)) {
        $filteredCategories = [];
        foreach ($categories as $catName => $catCount) {
            // 通过名称找ID进行匹配
            foreach ($allCategories as $navCat) {
                if ($navCat['name'] === $catName && in_array((int)$navCat['id'], $articleCatIds)) {
                    $filteredCategories[$catName] = $catCount;
                    break;
                }
            }
        }
        $categories = $filteredCategories;
    }
    ?>
    <?php if (!empty($categories)): ?>
    <div class="article-category-filter">
        <a href="<?= Theme::eAttr(Rewrite::url('article_list')) ?>" class="filter-tag <?= empty($currentCategory) ? 'active' : '' ?>">
            <i class="ti ti-apps"></i> 全部
        </a>
        <?php foreach ($categories as $catName => $catCount): ?>
        <a href="<?= Theme::eAttr(Rewrite::url('article_list') . (strpos(Rewrite::url('article_list'), '?') !== false ? '&' : '?') . 'cat=' . urlencode($catName)) ?>"
           class="filter-tag <?= $currentCategory === $catName ? 'active' : '' ?>">
            <i class="ti ti-folder"></i> <?= Security::e($catName) ?> <span class="filter-count"><?= $catCount ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 文章列表 -->
    <div class="article-list">
        <?php if (!empty($displayArticles)): ?>
            <?php foreach ($displayArticles as $article): ?>
            <div class="article-card">
                <?php if (!empty($article['category'])): ?>
                <div class="article-card-category">
                    <span class="category-tag"><i class="ti ti-folder"></i> <?= Security::e($article['category']) ?></span>
                </div>
                <?php endif; ?>
                <h2 class="article-title">
                    <a href="<?= Theme::eAttr(Rewrite::url('article', ['id' => (int)$article['id']])) ?>"><?= Theme::e($article['title']) ?></a>
                </h2>
                <?php if (!empty($article['excerpt'])): ?>
                <p class="article-excerpt"><?= Theme::e($article['excerpt']) ?></p>
                <?php endif; ?>
                <?php if (!empty($article['tags'])): ?>
                <div class="article-card-tags">
                    <?php foreach (array_slice(explode(',', $article['tags']), 0, 5) as $tag): ?>
                        <?php $tag = trim($tag); if ($tag): ?>
                        <span class="tag"><?= Security::e($tag) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="article-meta">
                    <span><i class="ti ti-calendar"></i> <?= formatDate($article['created_at']) ?></span>
                    <span><i class="ti ti-eye"></i> <?= (int)$article['views'] ?> 阅读</span>
                    <?php if (!empty($article['author'])): ?>
                    <span><i class="ti ti-user"></i> <?= Theme::e($article['author']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="ti ti-article"></i>
                <p class="empty-title">暂无文章</p>
                <?php if ($currentCategory): ?>
                <p style="color:#999;font-size:14px;margin-top:8px;">该分类下暂无文章</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if (!$currentCategory && $totalPages > 1): ?>
    <div class="pagination">
        <?php
        $baseUrl = Rewrite::url('article_list');
        renderPagination($page, $totalPages, $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=%d');
        ?>
    </div>
    <?php endif; ?>
</div>

<?php Theme::partial('footer'); ?>
