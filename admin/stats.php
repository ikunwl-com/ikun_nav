<?php
/**
 * 后台数据统计
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'stats';

$siteModel = new SiteModel();
$catModel = new CategoryModel();

// 总体统计
$stats = $siteModel->getStats();

// 分类分布
$catDistribution = Database::query(
    "SELECT c.name, c.icon, c.slug, COUNT(s.id) as count, SUM(s.views) as views, SUM(s.clicks) as clicks
     FROM " . table('categories') . " c
     LEFT JOIN " . table('sites') . " s ON c.id = s.category_id AND s.status='published'
     WHERE c.is_show=1
     GROUP BY c.id, c.name, c.icon, c.slug
     ORDER BY count DESC");

// 权重分布
$weightDistribution = Database::queryOne(
    "SELECT
       SUM(CASE WHEN br_pc >= 8 THEN 1 ELSE 0 END) as pc_high,
       SUM(CASE WHEN br_pc >= 4 AND br_pc < 8 THEN 1 ELSE 0 END) as pc_mid,
       SUM(CASE WHEN br_pc > 0 AND br_pc < 4 THEN 1 ELSE 0 END) as pc_low,
       SUM(CASE WHEN br_pc = 0 THEN 1 ELSE 0 END) as pc_zero
     FROM " . table('sites') . " WHERE status='published'"
);

// 热门站点 Top 10（按点击）
$topClicked = $siteModel->getTopClicked(10);

// 热门站点 Top 10（按浏览）
$topViewed = Database::query(
    "SELECT * FROM " . table('sites') . " WHERE status='published' ORDER BY views DESC LIMIT 10");

// 最近30天趋势
$monthlyTrend = Database::query(
    "SELECT DATE(created_at) as d, COUNT(*) as count
     FROM " . table('sites') . "
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at) ORDER BY d");

// 最近30天浏览/点击趋势
$trafficTrend = Database::query(
    "SELECT DATE(updated_at) as d, SUM(views) as views, SUM(clicks) as clicks
     FROM " . table('sites') . "
     WHERE updated_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(updated_at) ORDER BY d");

adminHeader('数据统计');
?>

<!-- 总体统计 -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon icon-bg-blue" ><i class="ti ti-world"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($stats['total'] ?? 0) ?></div>
      <div class="stat-label">站点总数</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-a994" ><i class="ti ti-eye"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($stats['total_views'] ?? 0) ?></div>
      <div class="stat-label">总浏览量</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-bg-pink" ><i class="ti ti-hand-click"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($stats['total_clicks'] ?? 0) ?></div>
      <div class="stat-label">总点击量</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-262f" ><i class="ti ti-chart-dots"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['total'] > 0 ? round(($stats['total_clicks'] ?? 0) / max(1, $stats['total_views'] ?? 1) * 100, 1) . '%' : '0%' ?></div>
      <div class="stat-label">点击转化率</div>
    </div>
  </div>
</div>

<!-- 分类分布 -->
<div class="card">
  <div class="card-header"><span class="card-title">分类分布</span></div>
  <?php if (empty($catDistribution)): ?>
  <div class="empty-state"><div class="icon"><i class="ti ti-category"></i></div><p>暂无数据</p></div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>分类</th><th>站点数</th><th>占比</th><th>浏览量</th><th>点击量</th><th>平均点击率</th></tr>
    </thead>
    <tbody>
      <?php
      $totalSites = $stats['published'] ?? 0;
      foreach ($catDistribution as $c):
        $pct = $totalSites > 0 ? round($c['count'] / $totalSites * 100, 1) : 0;
        $ctr = ($c['views'] ?? 0) > 0 ? round(($c['clicks'] ?? 0) / $c['views'] * 100, 1) : 0;
      ?>
      <tr>
        <td><i class="ti ti-<?= Security::eAttr($c['icon']) ?> text-6df9" ></i><?= Security::e($c['name']) ?></td>
        <td><span class="badge badge-info"><?= (int)$c['count'] ?></span></td>
        <td>
          <div class="d-flex items-center gap-8">
            <div class="bg-w-d0db">
              <div style="height:100%;width:<?= (float)$pct ?>%;background:linear-gradient(90deg,#667eea,#764ba2)"></div>
            </div>
            <span class="text-sm-dim-2"><?= (float)$pct ?>%</span>
          </div>
        </td>
        <td><?= formatNumber((int)($c['views'] ?? 0)) ?></td>
        <td><?= formatNumber((int)($c['clicks'] ?? 0)) ?></td>
        <td><?= (float)$ctr ?>%</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- 两列布局 -->
<div class="grid-2">
  <!-- 热门站点（点击） -->
  <div class="card">
    <div class="card-header"><span class="card-title">热门站点 Top 10（点击）</span></div>
    <?php if (empty($topClicked)): ?>
    <div class="empty-state"><div class="icon"><i class="ti ti-world-off"></i></div><p>暂无数据</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th width="40">#</th><th>站点</th><th>点击量</th><th>浏览量</th></tr></thead>
      <tbody>
        <?php foreach ($topClicked as $i => $s): ?>
        <tr>
          <td><span class="badge <?= $i < 3 ? 'badge-warning' : 'badge-secondary' ?>"><?= $i + 1 ?></span></td>
          <td><a href="/site/<?= (int)$s['id'] ?>" class="text-01d1"><?= Security::e($s['name']) ?></a></td>
          <td><span class="badge badge-info"><?= formatNumber((int)$s['clicks']) ?></span></td>
          <td class="text-muted-2"><?= formatNumber((int)$s['views']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- 热门站点（浏览） -->
  <div class="card">
    <div class="card-header"><span class="card-title">热门站点 Top 10（浏览）</span></div>
    <?php if (empty($topViewed)): ?>
    <div class="empty-state"><div class="icon"><i class="ti ti-world-off"></i></div><p>暂无数据</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th width="40">#</th><th>站点</th><th>浏览量</th><th>点击量</th></tr></thead>
      <tbody>
        <?php foreach ($topViewed as $i => $s): ?>
        <tr>
          <td><span class="badge <?= $i < 3 ? 'badge-warning' : 'badge-secondary' ?>"><?= $i + 1 ?></span></td>
          <td><a href="/site/<?= (int)$s['id'] ?>" class="text-01d1"><?= Security::e($s['name']) ?></a></td>
          <td><span class="badge badge-info"><?= formatNumber((int)$s['views']) ?></span></td>
          <td class="text-muted-2"><?= formatNumber((int)$s['clicks']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- 30天趋势 -->
<div class="card">
  <div class="card-header"><span class="card-title">最近 30 天提交趋势</span></div>
  <?php if (empty($monthlyTrend)): ?>
  <div class="empty-state"><div class="icon"><i class="ti ti-chart-line"></i></div><p>暂无数据</p></div>
  <?php else: ?>
  <div class="flex-end-p-gap-d6ca">
    <?php
    $trendMap = [];
    foreach ($monthlyTrend as $t) { $trendMap[$t['d']] = $t['count']; }
    $maxCount = max(1, max(array_values($trendMap) + [1]));
    for ($i = 29; $i >= 0; $i--):
      $date = date('Y-m-d', strtotime("-$i day"));
      $count = $trendMap[$date] ?? 0;
      $height = $count > 0 ? max(8, ($count / $maxCount) * 160) : 2;
    ?>
    <div class="flex-center-gap-w-dacb" title="<?= Security::eAttr($date) ?>: <?= (int)$count ?>个">
      <div style="width:80%;height:<?= (int)$height ?>px;background:linear-gradient(180deg,#667eea,#764ba2);border-radius:3px 3px 0 0;min-height:2px"></div>
      <?php if ($i % 5 === 0): ?>
      <span class="text-9c02"><?= Security::e(date('m/d', strtotime($date))) ?></span>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php adminFooter(); ?>
