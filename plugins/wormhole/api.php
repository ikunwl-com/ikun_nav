<?php
/**
 * 虫洞联盟插件 - 开放 API 接口声明
 *
 * 本文件由 core/OpenApi.php 自动加载：
 *   - 插件「启用」后，下方接口自动注册（/api/open/wormhole*，需 API Key）
 *   - 后台「API 密钥 - 使用说明」自动展示本文件的查询案例与使用说明
 *
 * 处理器依赖 WormholeModel（core/WormholeModel.php）。
 */

if (!defined('APP_VERSION') || !class_exists('Database') || !class_exists('Plugin')) {
    die('Forbidden');
}

/**
 * GET /api/open/wormhole/members?status=all|manual|auto|pending
 * 联盟成员列表（只读）
 */
function open_api_wormhole_members(): void
{
    $status = Security::enum($_GET['status'] ?? 'all', ['all', 'manual', 'auto', 'pending'], 'all');

    $model  = new WormholeModel();
    $rows   = $model->getMembers($status);

    $list = array_map(function ($m) {
        $tags = [];
        if (!empty($m['tags'])) {
            $raw = is_string($m['tags']) ? json_decode($m['tags'], true) : $m['tags'];
            if (is_array($raw)) {
                $tags = $raw;
            }
        }
        return [
            'id'               => (int)$m['id'],
            'name'             => $m['name'] ?? '',
            'url'              => $m['url'] ?? '',
            'domain'           => getDisplayDomain($m['url'] ?? ''),
            'description'      => $m['description'] ?? '',
            'keywords'         => implode(',', $tags),
            'br_pc'            => (int)($m['br_pc'] ?? 0),
            'br_mobile'        => (int)($m['br_mobile'] ?? 0),
            'views'            => (int)($m['views'] ?? 0),
            'wormhole_status'  => $m['wormhole_status'] ?? '',
            'wormhole_source'  => $m['wormhole_source_domain'] ?? '',
            'wormhole_check'   => $m['wormhole_last_check'] ?? '',
            'wormhole_fail'    => (int)($m['wormhole_check_fail'] ?? 0),
        ];
    }, $rows);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'status' => $status,
            'list'   => array_values($list),
            'total'  => count($list),
        ],
    ]);
}

/**
 * GET /api/open/wormhole/stats
 * 联盟成员数量统计
 */
function open_api_wormhole_stats(): void
{
    $model = new WormholeModel();
    $stats = $model->getStats();

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'manual'  => (int)($stats['manual_count'] ?? 0),
            'auto'    => (int)($stats['auto_count'] ?? 0),
            'pending' => (int)($stats['pending_count'] ?? 0),
            'broken'  => (int)($stats['broken_count'] ?? 0),
            'total'   => (int)($stats['total_count'] ?? 0),
        ],
    ]);
}

/**
 * GET /api/open/wormhole/random?limit=12
 * 随机获取联盟成员（用于外站/小程序展示联盟入口）
 */
function open_api_wormhole_random(): void
{
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 12)));

    $model = new WormholeModel();
    $rows  = $model->getRandomMembers($limit);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'list'  => array_values($rows),
            'total' => count($rows),
        ],
    ]);
}

return [
    [
        'endpoint' => 'open/wormhole/members',
        'method'   => 'GET',
        'handler'  => 'open_api_wormhole_members',
        'group'    => '虫洞联盟插件',
        'title'    => '联盟成员列表',
        'desc'     => '获取虫洞联盟成员列表（只读），支持按成员状态筛选。',
        'params'   => 'status=all|manual|auto|pending(默认all)',
        'example'  => 'GET /api/open/wormhole/members?status=all',
    ],
    [
        'endpoint' => 'open/wormhole/stats',
        'method'   => 'GET',
        'handler'  => 'open_api_wormhole_stats',
        'group'    => '虫洞联盟插件',
        'title'    => '联盟统计',
        'desc'     => '获取虫洞联盟成员数量统计（人工 / 自动 / 待审核 / 失效 / 总数）。',
        'params'   => '无',
        'example'  => 'GET /api/open/wormhole/stats',
    ],
    [
        'endpoint' => 'open/wormhole/random',
        'method'   => 'GET',
        'handler'  => 'open_api_wormhole_random',
        'group'    => '虫洞联盟插件',
        'title'    => '随机联盟成员',
        'desc'     => '随机返回若干已审核联盟成员，便于小程序 / 外站页面展示联盟入口。',
        'params'   => 'limit(1-50, 默认12)',
        'example'  => 'GET /api/open/wormhole/random?limit=12',
    ],
];
