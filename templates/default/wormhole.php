<?php
/**
 * 主题模板 - 虫洞联盟页
 * 变量由 Route.php 注入
 */
Theme::partial('header');
?>

<!-- 虫洞联盟 Hero -->
<div class="hero">
    <div class="container hero-inner">
        <div class="hero-brand">
            <h1><i class="ti ti-world"></i>虫洞联盟</h1>
            <p>探索联盟站点，发现更多优质资源</p>
        </div>
        <div class="hero-stats">
            <div class="stat">
                <div class="stat-num"><?= (int)($wormholeStats['total_count'] ?? 0) ?></div>
                <div class="stat-label">联盟站点</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
                <div class="stat-num"><?= (int)($wormholeStats['manual_count'] ?? 0) ?></div>
                <div class="stat-label">手动加入</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
                <div class="stat-num"><?= (int)($wormholeStats['auto_count'] ?? 0) ?></div>
                <div class="stat-label">自动加入</div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- 面包屑 -->
    <nav class="breadcrumb-nav">
        <a href="/">首页</a>
        <span class="sep">/</span>
        <span class="current">虫洞联盟</span>
    </nav>

    <div class="main-body">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-title">全部分类</div>
            <a href="/" class="cat-item"><i class="ti ti-home"></i><span class="cat-name">全部站点</span></a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= Theme::url('category', ['slug' => $cat['slug']]) ?>" class="cat-item" data-slug="<?= Theme::eAttr($cat['slug']) ?>">
                <i class="ti ti-<?= Theme::eAttr($cat['icon']) ?>"></i>
                <span class="cat-name"><?= Theme::e($cat['name']) ?></span>
                <span class="cat-count"><?= (int)$cat['site_count'] ?></span>
            </a>
            <?php endforeach; ?>
        </aside>

        <!-- Content -->
        <div class="content">
            <!-- 联盟成员列表 -->
            <div class="cat-header">
                <h2><i class="ti ti-users-group"></i>联盟成员</h2>
                <span class="count">共 <?= (int)($wormholeStats['total_count'] ?? 0) ?> 个站点</span>
            </div>
            <?php if (!empty($members)): ?>
            <div class="card-grid">
                <?php foreach ($members as $m):
                    $mUrl = $m['url'] ?? '';
                    if ($mUrl && !preg_match('/^https?:\/\//i', $mUrl)) {
                        $mUrl = 'https://' . ltrim($mUrl, '/');
                    }
                ?>
                <a href="<?= Theme::eAttr($mUrl) ?>" target="_blank" rel="nofollow" class="card">
                    <div class="card-top">
                        <div class="site-icon" style="width:48px;height:48px;background:<?= getCategoryColor($m['name'] ?? '') ?>;color:white;font-size:20px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">
                            <?= mb_substr($m['name'] ?? '', 0, 1) ?>
                        </div>
                        <div class="card-info">
                            <div class="card-name"><?= Theme::e($m['name'] ?? '') ?></div>
                            <div class="card-url"><?= Theme::e(getDisplayDomain($mUrl)) ?></div>
                        </div>
                    </div>
                    <div class="card-bottom">
                        <span class="tag">联盟</span>
                        <?php if (($m['wormhole_status'] ?? '') === 'manual'): ?>
                        <span class="tag" style="background:#dbeafe;color:#1d4ed8">手动</span>
                        <?php elseif (($m['wormhole_status'] ?? '') === 'auto'): ?>
                        <span class="tag" style="background:#dcfce7;color:#15803d">自动</span>
                        <?php elseif (($m['wormhole_status'] ?? '') === 'pending'): ?>
                        <span class="tag" style="background:#fef3c7;color:#b45309">待审</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="ti ti-world-off"></i>
                <div style="font-size:16px;font-weight:500;margin:16px 0 8px">暂无联盟成员</div>
                <div style="font-size:14px;color:#888">加入虫洞联盟，与其他站点互相引流</div>
            </div>
            <?php endif; ?>

            <!-- 虫洞联盟说明 -->
            <div class="card" style="margin-top: 20px;">
                <div style="padding: 20px;">
                    <h3 style="font-size:18px;font-weight:600;margin-bottom:12px;"><i class="ti ti-info-circle" style="color:var(--primary);margin-right:6px;"></i>什么是虫洞联盟？</h3>
                    <p style="font-size:14px;color:#666;line-height:1.6;margin-bottom:8px;">虫洞联盟是一个站点互访网络，加入后你的站点会出现在其他联盟成员的站点上，同时你也会展示其他联盟成员。通过随机传送功能，用户可以发现在联盟中随机出现的优质站点。</p>
                    <h4 style="font-size:15px;font-weight:600;margin:16px 0 8px;">加入方式：</h4>
                    <ul style="padding-left:20px;margin:8px 0;">
                        <li style="font-size:14px;color:#666;line-height:1.8;list-style:disc;">在后台「站点管理」中，将站点状态设为「虫洞联盟 - 手动加入」</li>
                        <li style="font-size:14px;color:#666;line-height:1.8;list-style:disc;">或在外站挂上本站友链，系统会自动检测并收录</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php Theme::partial('footer'); ?>
