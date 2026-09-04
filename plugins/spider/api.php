<?php
/**
 * 蜘蛛来访插件 - 开放 API 接口声明
 *
 * 本文件由 core/OpenApi.php 自动加载：
 *   - 插件「启用」后，下方接口自动注册（/api/open/spider*，需 API Key）
 *   - 后台「API 密钥 - 使用说明」自动展示本文件的查询案例与使用说明
 *
 * 处理器依赖的 SpiderModel 由插件 include.php 提供（启用后自动加载）。
 */

if (!defined('APP_VERSION') || !class_exists('Database') || !class_exists('Plugin')) {
    die('Forbidden');
}

/**
 * GET /api/open/spider/stats?range=7
 * 蜘蛛来访汇总：range=today|yesterday|7|30
 */
function open_api_spider_stats(): void
{
    $range = Security::enum($_GET['range'] ?? '7', ['today', 'yesterday', '7', '30'], '7');

    $model = new SpiderModel();
    switch ($range) {
        case 'today':
            $byEngine = $model->getTodayStats();
            break;
        case 'yesterday':
            $byEngine = $model->getYesterdayStats();
            break;
        case '30':
            $byEngine = $model->getRecent30Stats();
            break;
        default:
            $byEngine = $model->getRecent7Stats();
    }

    $total = 0;
    $list  = [];
    foreach ($byEngine as $engine => $count) {
        $total += (int)$count;
        $list[] = ['engine' => $engine, 'count' => (int)$count];
    }
    usort($list, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'range' => $range,
            'list'  => $list,
            'total' => $total,
        ],
    ]);
}

/**
 * GET /api/open/spider/trend
 * 近 30 天每日来访趋势
 */
function open_api_spider_trend(): void
{
    $model = new SpiderModel();
    $trend = $model->getTrend30Days();

    $list = [];
    foreach ($trend as $date => $day) {
        $engines = $day;
        unset($engines['total']);
        $list[] = [
            'date'    => $date,
            'total'   => (int)($day['total'] ?? 0),
            'engines' => $engines,
        ];
    }

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => ['list' => $list, 'total' => count($list)],
    ]);
}

/**
 * GET /api/open/spider/visits?page=1&limit=20&engine=baidu
 * 来访记录明细（分页）
 */
function open_api_spider_visits(): void
{
    $page   = max(1, min(1000, (int)($_GET['page'] ?? 1)));
    $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $engine = Security::cleanString($_GET['engine'] ?? '', 30);

    $model = new SpiderModel();
    $rows  = $model->getVisitList($page, $limit, $engine);
    $total = $model->count($engine);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'list'        => array_values($rows),
            'total'       => (int)$total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

return [
    [
        'endpoint' => 'open/spider/stats',
        'method'   => 'GET',
        'handler'  => 'open_api_spider_stats',
        'group'    => '蜘蛛来访插件',
        'title'    => '来访汇总',
        'desc'     => '获取各搜索引擎蜘蛛来访汇总（按引擎分组计数）。',
        'params'   => 'range=today|yesterday|7|30(默认7天)',
        'example'  => 'GET /api/open/spider/stats?range=7',
    ],
    [
        'endpoint' => 'open/spider/trend',
        'method'   => 'GET',
        'handler'  => 'open_api_spider_trend',
        'group'    => '蜘蛛来访插件',
        'title'    => '30天趋势',
        'desc'     => '获取近 30 天每日来访趋势（含各引擎明细）。',
        'params'   => '无',
        'example'  => 'GET /api/open/spider/trend',
    ],
    [
        'endpoint' => 'open/spider/visits',
        'method'   => 'GET',
        'handler'  => 'open_api_spider_visits',
        'group'    => '蜘蛛来访插件',
        'title'    => '来访明细',
        'desc'     => '分页获取来访记录明细，可按引擎筛选。',
        'params'   => 'page, limit(1-100, 默认20), engine(如 baidu/google/bing, 可选)',
        'example'  => 'GET /api/open/spider/visits?page=1&limit=20&engine=baidu',
    ],
];
