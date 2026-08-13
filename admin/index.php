<?php
/**
 * 后台仪表盘
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'dashboard';

$siteModel = new SiteModel();
$catModel = new CategoryModel();
$authModel = new AuthModel();

// 统计数据
$stats = $siteModel->getStats();
$totalSites = $stats['total'] ?? 0;
$publishedSites = $stats['published'] ?? 0;
$pendingSites = $stats['pending'] ?? 0;
$totalCategories = $catModel->count();
$totalViews = $stats['total_views'] ?? 0;
$totalClicks = $stats['total_clicks'] ?? 0;

// 最近7天提交趋势
$trend = Database::query(
    "SELECT DATE(created_at) as d, COUNT(*) as c FROM " . table('sites') .
    " WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY d");

// 最近提交的站点
$recentSites = Database::query(
    "SELECT * FROM " . table('sites') . " ORDER BY created_at DESC LIMIT 8");

// 最近登录日志
      $recentLogs = [];  // 不再从数据库读取，已迁移到文件日志


adminHeader('仪表盘');
?>

<!-- 统计卡片 -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon icon-bg-blue" ><i class="ti ti-world"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($totalSites) ?></div>
      <div class="stat-label">站点总数</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-a994" ><i class="ti ti-eye-check"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($publishedSites) ?></div>
      <div class="stat-label">已发布</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-f96a" ><i class="ti ti-clock-pause"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($pendingSites) ?></div>
      <div class="stat-label">待审核</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-262f" ><i class="ti ti-chart-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($totalViews) ?></div>
      <div class="stat-label">总浏览量</div>
    </div>
  </div>
</div>

<!-- 第二行统计 -->
<div class="stat-grid style-823d" >
  <div class="stat-card">
    <div class="stat-icon icon-bg-pink" ><i class="ti ti-hand-click"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($totalClicks) ?></div>
      <div class="stat-label">总点击量</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-ad9d" ><i class="ti ti-category"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($totalCategories) ?></div>
      <div class="stat-label">分类数</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon icon-1603" ><i class="ti ti-star"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= formatNumber($stats['featured'] ?? 0) ?></div>
      <div class="stat-label">推荐站点</div>
    </div>
  </div>
</div>

<!-- 最近提交趋势 + 快捷操作 -->
<div class="dual-grid-2-1">
  <!-- 趋势 -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">最近 7 天提交趋势</span>
    </div>
    <div class="trend-chart">
      <?php
      $trendMap = [];
      foreach ($trend as $t) { $trendMap[$t['d']] = $t['c']; }
      $maxCount = max(1, max(array_values($trendMap) + [1]));
      for ($i = 6; $i >= 0; $i--):
        $date = date('Y-m-d', strtotime("-$i day"));
        $count = $trendMap[$date] ?? 0;
        $height = $count > 0 ? max(8, ($count / $maxCount) * 140) : 4;
        $label = date('m/d', strtotime($date));
      ?>
      <div class="trend-bar-col">
        <span class="trend-bar-count"><?= $count > 0 ? (int)$count : '' ?></span>
        <div class="trend-bar" style="height:<?= (int)$height ?>px"></div>
        <span class="trend-bar-label"><?= Security::e($label) ?></span>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- 快捷操作 -->
  <div class="card">
    <div class="card-header"><span class="card-title">快捷操作</span></div>
    <div class="flex-col gap-10">
      <a href="/admin/sites.php?action=add" class="btn btn-primary quick-action"><i class="ti ti-plus"></i> 添加站点</a>
      <a href="/admin/categories.php?action=add" class="btn btn-secondary quick-action"><i class="ti ti-category-plus"></i> 添加分类</a>
      <a href="/admin/review.php" class="btn btn-warning quick-action">
        <i class="ti ti-clipboard-check"></i> 待审核
        <?php if ($pendingSites > 0): ?><span class="badge badge-danger"><?= $pendingSites ?></span><?php endif; ?>
      </a>
      <a href="/admin/settings.php" class="btn btn-secondary quick-action"><i class="ti ti-settings"></i> 基础设置</a>
    </div>
  </div>
</div>

<!-- 最近提交站点 -->
<div class="card">
  <div class="card-header">
    <span class="card-title">最近提交的站点</span>
    <a href="/admin/sites.php" class="btn btn-sm btn-secondary">查看全部</a>
  </div>
  <?php if (empty($recentSites)): ?>
  <div class="empty-state"><div class="icon"><i class="ti ti-world-off"></i></div><p>暂无站点数据</p></div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>站点名称</th><th>分类</th><th>权重</th><th>状态</th><th>提交时间</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recentSites as $s): ?>
      <tr>
        <td><?= Security::e($s['name']) ?></td>
        <td><?= Security::e($catModel->getNameById((int)$s['category_id']) ?: '-') ?></td>
        <td><span class="badge badge-info">BR <?= (int)$s['br_pc'] ?>/<?= (int)$s['br_mobile'] ?></span></td>
        <td>
          <?php if ($s['status'] === 'published'): ?>
          <span class="badge badge-success">已发布</span>
          <?php elseif ($s['status'] === 'pending'): ?>
          <span class="badge badge-warning">待审核</span>
          <?php else: ?>
          <span class="badge badge-secondary"><?= Security::e($s['status']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= Security::e(formatDate($s['created_at'])) ?></td>
        <td class="actions">
          <a href="/admin/sites.php?action=edit&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-secondary"><i class="ti ti-edit"></i></a>
          <?php if ($s['status'] === 'pending'): ?>
          <form method="POST" action="/admin/review.php" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken ?? '') ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn btn-sm btn-success" title="通过"><i class="ti ti-check"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- 最近登录日志 -->
<!-- 登录日志提示 -->
<div class="card">
  <div class="card-header"><span class="card-title">登录日志</span></div>
  <div class="info" style="margin:0;border-radius:0;">
    <i class="ti ti-file-text"></i>
    登录记录已迁移到本地文件日志，请自行查看 <code>data/logs/YYYYMMDD/admin_auth.log</code><br>
    <a href="https://site.ikunwl.com/data/docs/#ch6" target="_blank" rel="noopener" style="color:#667eea;text-decoration:underline;">
      <i class="ti ti-external-link"></i> 查看日志配置与说明文档
    </a>
  </div>
</div>

<!-- 系统信息 -->
<div class="card mt-20" >
  <div class="card-header"><span class="card-title">系统信息</span></div>
  <table class="data-table">
    <tr><td width="200">PHP版本</td><td><code><?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></code></td></tr>
    <tr><td>PDO驱动</td><td><code><?= implode(', ', PDO::getAvailableDrivers()) ?></code></td></tr>
    <tr><td>程序版本</td><td><code>懒人导航 v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?></code></td></tr>
    <tr><td>安装时间</td><td><code><?= Security::e(date('Y-m-d H:i:s', @filemtime(__DIR__ . '/../install.lock') ?: @filemtime(__DIR__ . '/../config/install.lock') ?: 0)) ?></code></td></tr>
  </table>
</div>

<?php adminFooter(); ?>