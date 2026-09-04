<?php
/**
 * 站点详情页模板
 * 变量由 Route.php 通过 Theme::render() 的 extract() 展开后直接可用
 */
$chartJs = '<script src="/templates/default/js/chart.min.js"></script>';
?>
<?php include __DIR__ . '/header.php'; ?>

    <div class="page-hero-header">
        <div class="container">
            <div class="site-header-left">
                <div class="site-icon-large">
                    <?= htmlspecialchars(mb_substr($site['name'] ?? '', 0, 1)) ?>
                </div>
                <div class="site-header-info">
                    <h1><?= htmlspecialchars($site['name'] ?? '') ?></h1>
                    <div class="site-url">
                        <a href="<?= htmlspecialchars(normalizeSiteUrl($site['url'] ?? '')) ?>" target="_blank" rel="noopener">
                            <?= htmlspecialchars(parseDomain($site['url'] ?? '')) ?>
                        </a>
                    </div>
                    <div class="site-actions">
                        <a href="/go.php?url=<?= urlencode($site['url'] ?? '') ?>&id=<?= (int)$site['id'] ?>" target="_blank" class="btn-visit" data-site-id="<?= (int)$site['id'] ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            访问站点
                        </a>
                        <button type="button" class="btn-refresh" id="btnUpdateMeta" data-site-id="<?= (int)$site['id'] ?>" data-url="<?= htmlspecialchars($site['url'] ?? '') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            更新数据
                        </button>
                    </div>
                </div>
            </div>
            <div class="site-header-right">
                <div class="header-rating" id="headerRating">
                    <div class="rating-stars" id="ratingStars" data-site-id="<?= (int)$site['id'] ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= ($ratingStats['avg'] ?? 0) ? 'active' : '' ?>" data-rating="<?= $i ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-text">
                        <strong id="ratingAvg"><?= number_format($ratingStats['avg'] ?? 0, 1) ?></strong>
                        <span id="ratingCount">分 · <?= (int)($ratingStats['count'] ?? 0) ?> 人评分</span>
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
                <a href="<?= htmlspecialchars(getCategoryUrl($category['slug'] ?? '')) ?>"><?= htmlspecialchars($category['name'] ?? '') ?></a>
                <span class="separator">/</span>
                <span class="current"><?= htmlspecialchars($site['name'] ?? '') ?></span>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-value"><?= (int)($site['br_pc'] ?? 0) ?: '-' ?></div>
                <div class="info-card-label">百度PC</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= (int)($site['br_mobile'] ?? 0) ?: '-' ?></div>
                <div class="info-card-label">百度移动</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= (int)($site['br_360'] ?? 0) ?: '-' ?></div>
                <div class="info-card-label">360</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= (int)($site['br_shenma'] ?? 0) ?: '-' ?></div>
                <div class="info-card-label">神马</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= htmlspecialchars($category['name'] ?? '-') ?></div>
                <div class="info-card-label">所属分类</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= htmlspecialchars(date('m-d', strtotime($site['created_at'] ?? 'now'))) ?></div>
                <div class="info-card-label">收录时间</div>
            </div>
            <div class="info-card">
                <div class="info-card-value"><?= (int)($site['views'] ?? 0) ?></div>
                <div class="info-card-label">浏览</div>
            </div>
            <a href="#" class="info-card info-card-link" id="btnFeedback" data-site-id="<?= (int)$site['id'] ?>">
                <div class="info-card-value">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <div class="info-card-label">问题反馈</div>
            </a>
        </div>

        <div class="site-details">
            <?php Plugin::hook('before_content', [$site ?? []]); ?>
            <div class="detail-row">
                <div class="detail-label">描述：</div>
                <div class="detail-value"><?= htmlspecialchars($site['description'] ?? '') ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">关键词：</div>
                <div class="detail-value">
                    <div class="tags">
                        <?php foreach (parseTags($site['tags'] ?? '[]') as $tag): ?>
                            <span class="tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="detail-row detail-row-chart">
                <div class="detail-label">趋势：</div>
                <div class="detail-value detail-value-chart">
                    <canvas id="trendChart" data-trend='<?= json_encode($trendData ?? [], JSON_UNESCAPED_UNICODE) ?>'></canvas>
                </div>
            </div>
        </div>

        <?php Plugin::hook('after_content', [$site ?? []]); ?>

        <?php if (!empty($related)): ?>
        <div class="related-section">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                </svg>
                同类推荐
            </h2>
            <div class="related-grid">
                <?php foreach ($related as $r): ?>
                <a href="<?= Theme::eAttr(Theme::url('site', ['id' => (int)$r['id'], 'slug' => $r['category_slug'] ?? ''])) ?>" class="related-card">
                    <div class="related-card-header">
                        <div class="related-card-icon"><?= htmlspecialchars(mb_substr($r['name'] ?? '', 0, 1)) ?></div>
                        <div>
                            <div class="related-card-title"><?= htmlspecialchars($r['name'] ?? '') ?></div>
                            <div class="related-card-url"><?= htmlspecialchars(parseDomain($r['url'] ?? '')) ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 问题反馈弹窗 -->
    <div class="modal hidden" id="feedbackModal">
        <div class="modal-backdrop" onclick="closeFeedback()"></div>
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ti ti-flag"></i> 问题反馈</h3>
                <button type="button" class="modal-close" onclick="closeFeedback()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="fb-site-info" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f8f9fa;border-radius:6px;margin-bottom:16px;">
                    <div style="width:36px;height:36px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:bold;">
                        <?= htmlspecialchars(mb_substr($site['name'] ?? '', 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($site['name'] ?? '') ?></div>
                        <div style="font-size:12px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(parseDomain($site['url'] ?? '')) ?></div>
                    </div>
                </div>

                <form id="feedbackForm" onsubmit="return false;">
                    <div class="form-group">
                        <label>反馈类型 <span style="color:#ef4444;">*</span></label>
                        <div class="fb-type-group" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                            <label class="fb-type-item active" data-value="url_change">
                                <input type="radio" name="fbType" value="url_change" checked hidden>
                                <span>网址变更</span>
                            </label>
                            <label class="fb-type-item" data-value="broken">
                                <input type="radio" name="fbType" value="broken" hidden>
                                <span>链接失效</span>
                            </label>
                            <label class="fb-type-item" data-value="error">
                                <input type="radio" name="fbType" value="error" hidden>
                                <span>信息错误</span>
                            </label>
                            <label class="fb-type-item" data-value="other">
                                <input type="radio" name="fbType" value="other" hidden>
                                <span>其他问题</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label>反馈内容 <span style="color:#ef4444;">*</span></label>
                        <textarea id="fbContent" rows="4" maxlength="500" placeholder="请描述具体问题，如：网址已变更为 https://..." style="width:100%;resize:vertical;"></textarea>
                        <div style="font-size:12px;color:#888;margin-top:4px;">最多 500 字</div>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label>联系邮箱（选填）</label>
                        <input type="email" id="fbEmail" maxlength="100" placeholder="方便我们回复处理结果" style="width:100%;">
                    </div>

                    <div id="fbMsg" style="margin-top:10px;min-height:20px;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFeedback()">取消</button>
                <button type="button" class="btn btn-primary" id="fbSubmitBtn" onclick="submitFeedback()">
                    <i class="ti ti-send"></i> 提交反馈
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>