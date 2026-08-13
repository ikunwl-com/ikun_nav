<?php
/**
 * 文章详情页模板
 */
Theme::partial('header');
?>

<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-article"></i>
            </div>
            <div class="page-hero-info">
                <h1><?= Theme::e($article['title']) ?></h1>
                <p class="page-hero-desc">
                    <?php if (!empty($article['author'])): ?>作者：<?= Theme::e($article['author']) ?> · <?php endif; ?>
                    <?= formatDate($article['created_at']) ?> · <?= (int)$article['views'] ?> 阅读
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="breadcrumb-bar">
        <nav class="breadcrumb">
            <a href="/">首页</a>
            <span class="separator">/</span>
            <a href="<?= Theme::eAttr(Rewrite::url('article_list')) ?>">文章专栏</a>
            <span class="separator">/</span>
            <span class="current"><?= Theme::e(mb_substr($article['title'], 0, 20)) ?></span>
        </nav>
    </div>

    <article class="article-detail">
        <!-- 文章头部信息 -->
        <div class="article-detail-header">
            <?php if (!empty($article['category'])): ?>
            <div class="article-detail-category">
                <span class="category-tag"><i class="ti ti-folder"></i> <?= Security::e($article['category']) ?></span>
            </div>
            <?php endif; ?>

            <h1 class="article-detail-title"><?= Theme::e($article['title']) ?></h1>

            <div class="article-detail-meta">
                <?php if (!empty($article['author'])): ?>
                <span><i class="ti ti-user"></i> <?= Theme::e($article['author']) ?></span>
                <?php endif; ?>
                <span><i class="ti ti-calendar"></i> <?= formatDate($article['created_at']) ?></span>
                <span><i class="ti ti-eye"></i> <?= (int)$article['views'] ?> 阅读</span>
                <?php if (!empty($article['tags'])): ?>
                <span><i class="ti ti-tags"></i>
                    <?php foreach (array_slice(explode(',', $article['tags']), 0, 3) as $tag): ?>
                        <span class="tag-mini"><?= Theme::e(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 摘要 -->
        <?php if (!empty($article['excerpt'])): ?>
        <div class="article-excerpt-box">
            <i class="ti ti-quote"></i>
            <p><?= Theme::e($article['excerpt']) ?></p>
        </div>
        <?php endif; ?>

        <!-- 正文内容 -->
        <div class="article-content">
            <?= Security::cleanHtml($article['content']) ?>
        </div>

        <!-- 底部操作 -->
        <div class="article-detail-footer">
            <a href="<?= Theme::eAttr(Rewrite::url('article_list')) ?>" class="btn-back">
                <i class="ti ti-arrow-left"></i> 返回文章列表
            </a>
            <div class="article-share">
                <span>分享：</span>
                <a href="javascript:void(0)" onclick="copyLink()" title="复制链接"><i class="ti ti-link"></i></a>
            </div>
        </div>
    </article>

    <script>
    function copyLink() {
        navigator.clipboard.writeText(window.location.href).then(function() {
            alert('链接已复制到剪贴板');
        });
    }
    </script>
</div>

<?php Theme::partial('footer'); ?>
