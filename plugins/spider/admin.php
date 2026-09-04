<?php
/**
 * 蜘蛛来访统计插件 - 后台管理页面
 *
 * 功能模块：
 *   1. 概览卡片：今日 / 昨日 / 近7日 / 近30日 总来访数
 *   2. 各引擎明细表：每个引擎的今日/昨日/7日/30日数据 + 独立IP + 最近来访
 *   3. 30天趋势图：Canvas 折线图，支持切换引擎
 *   4. 来访记录列表：分页浏览，支持按引擎筛选
 *   5. 操作：清理过期数据 / 清空全部数据 / 删除单条记录
 *
 * 由 admin/plugin.php?p=spider 分发进入此文件
 */

// 安全检查
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

$spiderModel = new SpiderModel();
$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugin.php?p=spider&msg=csrf');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'purge_old':
            $count = $spiderModel->purgeOldRecords();
            $msg = "已清理 {$count} 条过期记录";
            break;

        case 'clear_all':
            $count = $spiderModel->clearAll();
            $msg = "已清空全部 {$count} 条记录";
            break;

        case 'clear_engine':
            $engine = $_POST['engine'] ?? '';
            if (isset(SpiderModel::$engines[$engine])) {
                $count = $spiderModel->clearByEngine($engine);
                $engineName = SpiderModel::$engines[$engine]['name'];
                $msg = "已清空 {$engineName} 的 {$count} 条记录";
            } else {
                $msg = '无效的引擎';
                $msgType = 'error';
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $tbl = Database::table('spider_visits');
            $ok = Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]) > 0;
            $msg = $ok ? '记录已删除' : '删除失败';
            $msgType = $ok ? 'success' : 'error';
            break;
    }

    redirect('/admin/plugin.php?p=spider&ok=' . urlencode($msg));
}

// ========== GET 消息显示 ==========
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'csrf') {
    $msg = 'CSRF验证失败';
    $msgType = 'error';
}

// ========== 设置页面分发 ==========
$viewAction = $_GET['action'] ?? '';
if ($viewAction === 'settings') {
    $settingsFile = __DIR__ . '/settings.php';
    if (file_exists($settingsFile)) {
        require_once $settingsFile;
        return;
    }
}

// ========== 获取统计数据 ==========
$todayStats     = $spiderModel->getTodayStats();
$yesterdayStats  = $spiderModel->getYesterdayStats();
$recent7Stats    = $spiderModel->getRecent7Stats();
$recent30Stats   = $spiderModel->getRecent30Stats();
$lastVisitTimes  = $spiderModel->getLastVisitTimes();
$uniqueIps       = $spiderModel->getUniqueIpCount();
$trend30Days     = $spiderModel->getTrend30Days();

$todayTotal    = array_sum($todayStats);
$yesterdayTotal = array_sum($yesterdayStats);
$recent7Total   = array_sum($recent7Stats);
$recent30Total  = array_sum($recent30Stats);

// 获取启用的引擎
$enabledEngines = SpiderModel::getEnabledEngines();

// ========== 来访记录列表（分页） ==========
$filterEngine = $_GET['type'] ?? '';
if (!empty($filterEngine) && !isset(SpiderModel::$engines[$filterEngine])) {
    $filterEngine = '';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$total = $spiderModel->count($filterEngine);
$totalPages = (int)ceil($total / max(1, $perPage));
$visits = $spiderModel->getVisitList($page, $perPage, $filterEngine);

if ($msg) { adminAlert($msg, $msgType); }
?>

<!-- ========== 概览卡片 ========== -->
<div class="spider-overview-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:16px;margin-bottom:20px;">
    <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(102,126,234,0.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;opacity:0.9;">今日来访</div>
                <div style="font-size:32px;font-weight:700;margin-top:4px;"><?= $todayTotal ?></div>
            </div>
            <i class="ti ti-calendar-day" style="font-size:36px;opacity:0.7;"></i>
        </div>
    </div>
    <div style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(245,87,108,0.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;opacity:0.9;">昨日来访</div>
                <div style="font-size:32px;font-weight:700;margin-top:4px;"><?= $yesterdayTotal ?></div>
            </div>
            <i class="ti ti-calendar-minus" style="font-size:36px;opacity:0.7;"></i>
        </div>
    </div>
    <div style="background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(79,172,254,0.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;opacity:0.9;">近7日来访</div>
                <div style="font-size:32px;font-weight:700;margin-top:4px;"><?= $recent7Total ?></div>
            </div>
            <i class="ti ti-calendar-week" style="font-size:36px;opacity:0.7;"></i>
        </div>
    </div>
    <div style="background:linear-gradient(135deg,#43e97b,#38f9d7);color:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(67,233,123,0.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;opacity:0.9;">近30日来访</div>
                <div style="font-size:32px;font-weight:700;margin-top:4px;"><?= $recent30Total ?></div>
            </div>
            <i class="ti ti-calendar-month" style="font-size:36px;opacity:0.7;"></i>
        </div>
    </div>
</div>

<!-- ========== 各引擎明细表 ========== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header flex-between-center">
        <span class="card-title"><i class="ti ti-list-details"></i> 各引擎统计明细</span>
        <div class="flex-center-gap-8">
            <a href="/admin/plugin.php?p=spider&action=settings" class="btn btn-secondary"><i class="ti ti-settings"></i> 设置</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('确定清理过期数据？将删除超过保留期限的记录。')">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="purge_old">
                <button type="submit" class="btn btn-secondary btn-sm"><i class="ti ti-trash-x"></i> 清理过期</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('警告：将清空全部蜘蛛来访记录，此操作不可恢复！确定继续？')">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger btn-sm"><i class="ti ti-trash"></i> 清空全部</button>
            </form>
        </div>
    </div>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">引擎</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">今日</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">昨日</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">近7日</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">近30日</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">独立IP</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">最近来访</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($enabledEngines)): ?>
            <tr><td colspan="8" style="padding:24px;text-align:center;border:1px solid #e9ecef;color:#999;">
                <i class="ti ti-info-circle" style="font-size:24px;"></i>
                <p style="margin:8px 0 0;">尚未启用任何引擎，请到<a href="/admin/plugin.php?p=spider&action=settings">设置页</a>勾选要统计的搜索引擎</p>
            </td></tr>
            <?php else: foreach ($enabledEngines as $engine): 
                $info = SpiderModel::$engines[$engine];
                $tCount = $todayStats[$engine] ?? 0;
                $yCount = $yesterdayStats[$engine] ?? 0;
                $r7Count = $recent7Stats[$engine] ?? 0;
                $r30Count = $recent30Stats[$engine] ?? 0;
                $ipCount = $uniqueIps[$engine] ?? 0;
                $lastVisit = $lastVisitTimes[$engine] ?? null;
            ?>
            <tr>
                <td style="padding:10px 12px;border:1px solid #e9ecef;">
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <i class="ti <?= $info['icon'] ?>" style="color:<?= $info['color'] ?>;font-size:18px;"></i>
                        <span style="font-weight:600;"><?= Security::e($info['name']) ?></span>
                    </span>
                </td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">
                    <span style="font-size:16px;font-weight:700;color:<?= $tCount > 0 ? $info['color'] : '#ccc' ?>;"><?= $tCount ?></span>
                </td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $yCount ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $r7Count ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $r30Count ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $ipCount ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;font-size:13px;color:#666;"><?= $lastVisit ? formatDate($lastVisit) : '<span style="color:#ccc;">无</span>' ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;white-space:nowrap;">
                    <a href="/admin/plugin.php?p=spider&type=<?= Security::eAttr($engine) ?>" class="btn btn-sm btn-secondary" title="查看记录"><i class="ti ti-eye"></i></a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定清空 <?= Security::eAttr($info['name']) ?> 的全部记录？')">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="clear_engine">
                        <input type="hidden" name="engine" value="<?= Security::eAttr($engine) ?>">
                        <button type="submit" class="btn btn-sm" style="color:#ef4444;" title="清空该引擎"><i class="ti ti-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <!-- 合计行 -->
            <tr style="background:#f8f9fa;font-weight:700;">
                <td style="padding:10px 12px;border:1px solid #e9ecef;">合计</td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $todayTotal ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $yesterdayTotal ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $recent7Total ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= $recent30Total ?></td>
                <td style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;"><?= array_sum($uniqueIps) ?></td>
                <td colspan="2" style="padding:10px 12px;border:1px solid #e9ecef;"></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ========== 30天趋势图 ========== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header flex-between-center">
        <span class="card-title"><i class="ti ti-chart-line"></i> 近30天来访趋势</span>
        <div id="trend-legend" style="display:flex;flex-wrap:wrap;gap:8px;">
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px;">
                <input type="checkbox" id="toggle-total" checked style="accent-color:#666;"> 总计
            </label>
            <?php foreach ($enabledEngines as $engine): 
                $info = SpiderModel::$engines[$engine];
            ?>
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px;">
                <input type="checkbox" class="toggle-engine" data-engine="<?= Security::eAttr($engine) ?>" checked style="accent-color:<?= $info['color'] ?>;">
                <span style="color:<?= $info['color'] ?>;"><?= Security::e($info['name']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div style="position:relative;padding:16px;">
        <canvas id="trendChart" style="width:100%;height:320px;"></canvas>
    </div>
</div>

<!-- ========== 来访记录列表 ========== -->
<div class="card">
    <div class="card-header flex-between-center">
        <span class="card-title">
            <i class="ti ti-history"></i> 来访记录
            <?php if (!empty($filterEngine)): ?>
            - <?= Security::e(SpiderModel::$engines[$filterEngine]['name'] ?? $filterEngine) ?>
            <?php endif; ?>
            （共 <?= $total ?> 条）
        </span>
        <?php if (!empty($filterEngine)): ?>
        <a href="/admin/plugin.php?p=spider" class="btn btn-sm btn-secondary"><i class="ti ti-x"></i> 清除筛选</a>
        <?php endif; ?>
    </div>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">引擎</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">访问URL</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">IP</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">时间</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visits)): ?>
            <tr><td colspan="5" style="padding:24px;text-align:center;border:1px solid #e9ecef;color:#999;">
                <i class="ti ti-database-off" style="font-size:24px;"></i>
                <p style="margin:8px 0 0;">暂无来访记录</p>
            </td></tr>
            <?php else: foreach ($visits as $v):
                $info = SpiderModel::$engines[$v['spider_type']] ?? null;
            ?>
            <tr>
                <td style="padding:8px 12px;border:1px solid #e9ecef;">
                    <?php if ($info): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;">
                        <i class="ti <?= $info['icon'] ?>" style="color:<?= $info['color'] ?>;font-size:16px;"></i>
                        <span style="font-weight:600;font-size:13px;"><?= Security::e($info['name']) ?></span>
                    </span>
                    <?php else: ?>
                    <span class="badge badge-info"><?= Security::e($v['spider_type']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;" title="<?= Security::eAttr($v['url']) ?>">
                    <a href="<?= Security::eAttr($v['url']) ?>" target="_blank" style="color:#3b82f6;"><?= Security::e($v['url']) ?></a>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;font-size:13px;font-family:monospace;"><?= Security::e($v['ip']) ?></td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;font-size:13px;color:#666;white-space:nowrap;"><?= formatDate($v['visited_at']) ?></td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;text-align:center;">
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除此记录？')">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="color:#ef4444;" title="删除"><i class="ti ti-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="flex-center-gap-8" style="padding:12px;border-top:1px solid #e9ecef;">
        <?php if ($page > 1): ?>
        <a href="/admin/plugin.php?p=spider&page=<?= $page - 1 ?><?= $filterEngine ? '&type=' . Security::eAttr($filterEngine) : '' ?>" class="btn btn-sm btn-secondary">上一页</a>
        <?php endif; ?>
        <span style="color:#666;font-size:13px;">第 <?= $page ?> / <?= $totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
        <a href="/admin/plugin.php?p=spider&page=<?= $page + 1 ?><?= $filterEngine ? '&type=' . Security::eAttr($filterEngine) : '' ?>" class="btn btn-sm btn-secondary">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ========== 趋势图绘制脚本 ========== -->
<script>
(function() {
    // 趋势数据（PHP -> JS）
    var trendData = <?= json_encode($trend30Days) ?>;
    var engines = <?= json_encode($enabledEngines) ?>;
    var engineInfo = <?= json_encode(SpiderModel::$engines) ?>;

    var canvas = document.getElementById('trendChart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    // 高DPI适配
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = 320 * dpr;
    ctx.scale(dpr, dpr);

    var W = rect.width;
    var H = 320;
    var padding = { top: 20, right: 20, bottom: 40, left: 50 };
    var chartW = W - padding.left - padding.right;
    var chartH = H - padding.top - padding.bottom;

    var dates = Object.keys(trendData);
    var dataCount = dates.length;
    if (dataCount === 0) return;

    // 计算最大值
    function getMaxValue() {
        var max = 1;
        dates.forEach(function(d) {
            var row = trendData[d];
            if (document.getElementById('toggle-total').checked) {
                max = Math.max(max, row.total || 0);
            }
            engines.forEach(function(e) {
                var cb = document.querySelector('.toggle-engine[data-engine="' + e + '"]');
                if (cb && cb.checked) {
                    max = Math.max(max, row[e] || 0);
                }
            });
        });
        return Math.ceil(max * 1.1);
    }

    function drawChart() {
        ctx.clearRect(0, 0, W, H);

        var maxVal = getMaxValue();
        var stepX = chartW / Math.max(1, dataCount - 1);

        // 网格线 & Y轴标签
        ctx.strokeStyle = '#e9ecef';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#999';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        var gridLines = 5;
        for (var i = 0; i <= gridLines; i++) {
            var y = padding.top + chartH - (chartH / gridLines) * i;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(W - padding.right, y);
            ctx.stroke();
            var labelVal = Math.round(maxVal / gridLines * i);
            ctx.fillText(labelVal, padding.left - 6, y + 4);
        }

        // X轴标签（每5天显示一个）
        ctx.textAlign = 'center';
        for (var i = 0; i < dataCount; i++) {
            if (i % 5 === 0 || i === dataCount - 1) {
                var x = padding.left + stepX * i;
                var label = dates[i].substring(5); // MM-DD
                ctx.fillText(label, x, H - padding.bottom + 16);
            }
        }

        // 绘制总计线
        if (document.getElementById('toggle-total').checked) {
            drawLine('total', '#666', 2, stepX, maxVal, dates);
        }

        // 绘制各引擎线
        engines.forEach(function(e) {
            var cb = document.querySelector('.toggle-engine[data-engine="' + e + '"]');
            if (cb && cb.checked) {
                var color = engineInfo[e] ? engineInfo[e].color : '#999';
                drawLine(e, color, 1.5, stepX, maxVal, dates);
            }
        });
    }

    function drawLine(key, color, lineWidth, stepX, maxVal, dates) {
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        ctx.beginPath();

        var points = [];
        for (var i = 0; i < dates.length; i++) {
            var val = trendData[dates[i]][key] || 0;
            var x = padding.left + stepX * i;
            var y = padding.top + chartH - (chartH / maxVal) * val;
            points.push({ x: x, y: y });
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();

        // 填充面积（仅总计线）
        if (key === 'total') {
            ctx.lineTo(padding.left + stepX * (dates.length - 1), padding.top + chartH);
            ctx.lineTo(padding.left, padding.top + chartH);
            ctx.closePath();
            ctx.fillStyle = 'rgba(102,102,102,0.06)';
            ctx.fill();
        }

        // 数据点
        ctx.fillStyle = color;
        for (var j = 0; j < points.length; j++) {
            if (j % 5 === 0 || j === points.length - 1) {
                ctx.beginPath();
                ctx.arc(points[j].x, points[j].y, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    // 初始绘制
    drawChart();

    // 图例切换重绘
    var checkboxes = document.querySelectorAll('#trend-legend input[type="checkbox"]');
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', drawChart);
    });

    // 窗口缩放重绘
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var newRect = canvas.getBoundingClientRect();
            canvas.width = newRect.width * dpr;
            canvas.height = 320 * dpr;
            ctx.scale(dpr, dpr);
            W = newRect.width;
            chartW = W - padding.left - padding.right;
            drawChart();
        }, 200);
    });
})();
</script>
