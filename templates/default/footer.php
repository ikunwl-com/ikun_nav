<?php
// footer partial 被多页面复用，确保关键变量有默认值
if (!isset($showWeight)) $showWeight = 1;
if (!isset($settings)) $settings = [];
// 获取原始 URL 格式（含占位符），用于 JS 端动态替换
$urlCfg = Rewrite::getConfig();
// chart.js 和 site.js（由页面模板注入）
if (!isset($chartJs)) {
    $chartJs = '<script src="/templates/default/js/chart.min.js"></script>';
}
if (!isset($siteJs)) {
    $siteJs = '<script src="/templates/default/js/site.js"></script>';
}
// 额外 head 内容（安全过滤）
if (!isset($extraHead)) {
    $extraHead = '';
}
// 安全：对 extraHead 做 HTML 清洗，防止 XSS
echo Security::cleanHtml($extraHead);
?><script>
(function(){
  // PHP 预生成完整 URL 模板（带站点根路径），JS 只替换占位符
  var categoryUrlTemplate = '<?= Theme::url('category', ['slug' => 'SLUG_PLACEHOLDER']) ?>';
  var siteUrlTemplate     = '<?= Theme::url('site', ['id' => 'SITE_ID_PLACEHOLDER', 'slug' => 'SLUG_PLACEHOLDER']) ?>';
  var navConfig = {
    showWeight: <?= (int)$showWeight ?>,
    searchUrl:  '<?= Theme::url('search') ?>',
    homeUrl:    '<?= Theme::url('home') ?>',
    submitUrl:  '<?= Theme::url('submit') ?>',
    categoryUrlTemplate: categoryUrlTemplate,
    siteUrlTemplate:     siteUrlTemplate
  };

  // 全局搜索跳转
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.matches('#searchInput')) {
      var q = e.target.value.trim();
      if (q) {
        var sep = navConfig.searchUrl.indexOf('?') !== -1 ? '&' : '?';
        location.href = navConfig.searchUrl + sep + 'q=' + encodeURIComponent(q);
      }
    }
  });

  window.searchTag = function(tag) {
    var input = document.getElementById('searchInput');
    if (input) input.value = tag;
    var sep = navConfig.searchUrl.indexOf('?') !== -1 ? '&' : '?';
    location.href = navConfig.searchUrl + sep + 'q=' + encodeURIComponent(tag);
  };

  window.triggerSearch = function() {
    var input = document.getElementById('searchInput');
    var q = input ? input.value.trim() : '';
    if (!q) return;
    var sep = navConfig.searchUrl.indexOf('?') !== -1 ? '&' : '?';
    location.href = navConfig.searchUrl + sep + 'q=' + encodeURIComponent(q);
  };

  /** 分类就地切换 */
  window.switchCategory = function(slug, el) {
    document.querySelectorAll('.sidebar-item[data-slug]').forEach(function(item) {
      item.classList.remove('active');
    });
    if (el) el.classList.add('active');

    var grid = document.getElementById('site-grid');
    if (grid) grid.style.opacity = '0.5';

    fetch('/api/?endpoint=sites&category=' + encodeURIComponent(slug) + '&limit=12&sort=br')
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (grid) grid.style.opacity = '1';
        if (!res.success) {
          if (grid) grid.innerHTML = '<div class="empty-state-grid"><p>加载失败</p></div>';
          return;
        }

        var catName = document.getElementById('cat-name');
        var catDesc = document.getElementById('cat-desc');
        var catInfo = res.category || {};
        if (catName) catName.textContent = catInfo.name || slug;
        if (catDesc) catDesc.textContent = catInfo.seo_desc || '';

        var viewMoreLink = document.getElementById('view-more-link');
        if (viewMoreLink) {
          viewMoreLink.href = navConfig.categoryUrlTemplate.replace('SLUG_PLACEHOLDER', encodeURIComponent(slug));
        }

        var sites = res.data || [];
        if (grid) {
          if (sites.length === 0) {
            grid.innerHTML = '<div class="empty-state-grid"><i class="ti ti-inbox"></i><p>暂无站点</p></div>';
          } else {
            grid.innerHTML = sites.map(function(s) {
              var maxBr = Math.max(
                (s.br_pc || 0), (s.br_mobile || 0), (s['br_360'] || 0), (s.br_shenma || 0)
              );
              var tags = (s.tags || []).slice(0, 2);
              var tagsHtml = '';
              if (tags.length > 0) {
                tagsHtml = '<div class="card-tags">' + tags.map(function(t) {
                  return '<span class="tag">' + escapeHtml(t) + '</span>';
                }).join('') + '</div>';
              }
              var weightHtml = '';
              if (navConfig.showWeight && maxBr > 0) {
                weightHtml = '<span class="weight-badge ' + getWeightClass(maxBr) + '">BR ' + maxBr + '</span>';
              }
              var color = getSiteColor(s.name);
              var iconHtml = '<div class="site-icon" style="width:36px;height:36px;background:' + color + '15;color:' + color + '">' + escapeHtml(s.name.charAt(0)) + '</div>';
              var siteUrl = navConfig.siteUrlTemplate.replace('SITE_ID_PLACEHOLDER', s.id).replace('SLUG_PLACEHOLDER', encodeURIComponent(s.category_slug || ''));
              var views = s.views || 0;
              var viewsStr = views >= 1000000 ? (views / 1000000).toFixed(1) + 'M' : (views >= 1000 ? (views / 1000).toFixed(1) + 'k' : String(views));
              return '<a href="' + escapeHtml(siteUrl) + '" class="card">' +
                '<div class="card-header">' + iconHtml + '<div class="card-title-wrap"><span class="card-title">' + escapeHtml(s.name) + '</span></div>' + weightHtml + '</div>' +
                '<div class="card-url">' + escapeHtml(s.domain || s.url) + '</div>' +
                '<div class="card-desc">' + escapeHtml(s.description || '') + '</div>' +
                '<div class="card-footer">' + tagsHtml + '<span class="card-views"><i class="ti ti-eye"></i>' + viewsStr + '</span></div></a>';
            }).join('');
          }
        }

        if (window.history && window.history.replaceState) {
          window.history.replaceState({category: slug}, '', '/');
        }
      })
      .catch(function(err) {
        if (grid) grid.style.opacity = '1';
        console.error('分类切换失败:', err);
        if (grid) grid.innerHTML = '<div class="empty-state-grid"><p>加载失败，请重试</p></div>';
      });
  };

  function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function formatNum(n) {
    n = parseInt(n) || 0;
    if (n >= 10000) return (n / 10000).toFixed(1) + 'w';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(n);
  }

  function getWeightClass(br) {
    if (br >= 7) return 'weight-9';
    if (br >= 6) return 'weight-7';
    if (br >= 5) return 'weight-5';
    if (br >= 4) return 'weight-4';
    if (br >= 3) return 'weight-3';
    if (br >= 2) return 'weight-2';
    if (br >= 1) return 'weight-1';
    return 'weight-0';
  }

  function getSiteColor(name) {
    var colors = ['#667eea','#764ba2','#009468','#D95E00','#F15795','#0AA3A3','#0099E6','#8F5CFF','#FA423C','#C2732F','#0284C7','#059669','#d97706','#7c3aed','#ef4444'];
    var hash = 0;
    for (var i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  // 排行榜切换
  document.querySelectorAll('.ranking-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      var targetTab = this.dataset.tab;
      document.querySelectorAll('.ranking-tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      document.querySelectorAll('.ranking-panel').forEach(p => p.classList.remove('active'));
      document.querySelector('.ranking-panel[data-panel="' + targetTab + '"]').classList.add('active');
    });
  });
})();
</script>

<?php Plugin::hook('before_footer'); ?>
<footer class="site-footer">
  <?php $footerContent = setting('site_footer'); ?>
  <?php if (!empty($footerContent)): ?>
    <!-- 用户自定义底部（支持 HTML） -->
    <div class="custom-footer">
      <?= Security::cleanHtml($footerContent) ?>
    </div>
  <?php else: ?>
    <!-- 默认底部 -->
    <nav class="footer-nav">
      <a href="<?= Theme::url('home') ?>" class="primary"><?= Theme::e($settings['site_name'] ?? '首页') ?></a>
      <span class="sep">|</span>
      <a href="<?= Theme::url('submit') ?>" class="secondary">提交站点</a>
      <span class="sep">|</span>
      <a href="/wormhole/" target="_blank" class="primary" title="探索全联盟站点">🌀 虫洞联盟</a>
    </nav>
    <p class="footer-copy"><?= Theme::e($settings['site_name'] ?? '') ?> · <?= Theme::e($settings['site_slogan'] ?? '') ?></p>
  <?php endif; ?>
</footer>
<?php echo $chartJs; ?>
<?php echo $siteJs; ?>

<?php Plugin::hook('after_footer'); ?>
</body>
</html>