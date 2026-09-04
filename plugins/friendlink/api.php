<?php
/**
 * 友情链接插件 - 开放 API 接口声明
 *
 * 本文件由 core/OpenApi.php 自动加载：
 *   - 插件「启用」后，下方接口自动注册（/api/open/friendlink*，需 API Key）
 *   - 后台「API 密钥 - 使用说明」自动展示本文件的查询案例与使用说明
 *
 * 处理器依赖的 FriendLinkModel 由插件 include.php 提供（启用后自动加载）。
 */

if (!defined('APP_VERSION') || !class_exists('Database') || !class_exists('Plugin')) {
    die('Forbidden');
}

/**
 * GET /api/open/friendlinks?limit=50&status=active|all
 * 友链列表：默认仅显示启用中的；status=all 返回全部（含隐藏），供管理端使用
 */
function open_api_friendlinks_list(): void
{
    $limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $status = Security::enum($_GET['status'] ?? 'active', ['active', 'all'], 'active');

    $model = new FriendLinkModel();
    if ($status === 'all') {
        $rows = $model->getAllLinks();
        $total = count($rows);
    } else {
        $rows = $model->getActiveLinks($limit);
        $total = count($rows);
    }

    $list = array_map(function ($l) {
        return [
            'id'         => (int)$l['id'],
            'name'       => $l['name'],
            'url'        => $l['url'],
            'css_class'  => $l['css_class'] ?? '',
            'icon'       => $l['icon'] ?? '',
            'sort_order' => (int)($l['sort_order'] ?? 0),
            'status'     => isset($l['status']) ? (int)$l['status'] : 1,
            'created_at' => $l['created_at'] ?? '',
        ];
    }, $rows);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'list'  => array_values($list),
            'total' => $total,
            'limit' => $limit,
        ],
    ]);
}

/**
 * POST /api/open/friendlink/create - 新增友链
 */
function open_api_friendlink_create(): void
{
    $input = api_json_input();
    $name  = Security::cleanString($input['name'] ?? '', 100);
    $url   = Security::safeUrl($input['url'] ?? '');
    if ($name === '' || $url === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称和网址不能为空'], 400);
    }

    $model = new FriendLinkModel();
    $id = $model->create([
        'name'       => $name,
        'url'        => $url,
        'css_class'  => Security::cleanString($input['css_class'] ?? '', 200),
        'icon'       => Security::cleanString($input['icon'] ?? '', 500),
        'sort_order' => max(0, Security::int($input['sort_order'] ?? 0)),
        'status'     => isset($input['status']) ? ((int)$input['status'] === 1 ? 1 : 0) : 1,
    ]);

    api_open_log("[开放API-新增友链] 友链ID={$id} 名称={$name}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '友链添加成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/open/friendlink/update - 编辑友链
 */
function open_api_friendlink_update(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少友链ID参数'], 400);
    }

    $model = new FriendLinkModel();
    $cur = $model->getById($id);
    if (!$cur) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '友链不存在'], 404);
    }

    // 部分更新：未传字段保留原值
    $data = [
        'name'       => array_key_exists('name', $input) ? Security::cleanString($input['name'] ?? '', 100) : (string)($cur['name'] ?? ''),
        'url'        => array_key_exists('url', $input) ? Security::safeUrl($input['url'] ?? '') : (string)($cur['url'] ?? ''),
        'css_class'  => array_key_exists('css_class', $input) ? Security::cleanString($input['css_class'] ?? '', 200) : (string)($cur['css_class'] ?? ''),
        'icon'       => array_key_exists('icon', $input) ? Security::cleanString($input['icon'] ?? '', 500) : (string)($cur['icon'] ?? ''),
        'sort_order' => array_key_exists('sort_order', $input) ? max(0, Security::int($input['sort_order'] ?? 0)) : (int)($cur['sort_order'] ?? 0),
        'status'     => array_key_exists('status', $input) ? ((int)$input['status'] === 1 ? 1 : 0) : (int)($cur['status'] ?? 1),
    ];
    if ($data['name'] === '' || $data['url'] === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '名称和网址不能为空'], 400);
    }

    $model->update($id, $data);
    api_open_log("[开放API-编辑友链] 友链ID={$id} 名称=" . $data['name']);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '更新成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/open/friendlink/delete - 删除友链
 */
function open_api_friendlink_delete(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少友链ID参数'], 400);
    }

    $model = new FriendLinkModel();
    if (!$model->getById($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '友链不存在'], 404);
    }

    $model->delete($id);
    api_open_log("[开放API-删除友链] 友链ID={$id}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '删除成功',
        'id'      => $id,
    ]);
}

return [
    [
        'endpoint' => 'open/friendlinks',
        'method'   => 'GET',
        'handler'  => 'open_api_friendlinks_list',
        'group'    => '友情链接插件',
        'title'    => '友链列表',
        'desc'     => '获取友情链接列表：默认仅返回启用中的；status=all 返回全部（含隐藏）。',
        'params'   => 'limit(1-200, 默认50), status=active|all(默认active)',
        'example'  => 'GET /api/open/friendlinks?limit=50&status=active',
    ],
    [
        'endpoint' => 'open/friendlink/create',
        'method'   => 'POST',
        'handler'  => 'open_api_friendlink_create',
        'group'    => '友情链接插件',
        'title'    => '新增友链',
        'desc'     => '新增一条友情链接。',
        'params'   => 'JSON：name* / url* / css_class / icon / sort_order / status(1|0)',
        'example'  => "POST /api/open/friendlink/create  body: {\"name\":\"示例站\",\"url\":\"https://example.com\"}",
    ],
    [
        'endpoint' => 'open/friendlink/update',
        'method'   => 'POST',
        'handler'  => 'open_api_friendlink_update',
        'group'    => '友情链接插件',
        'title'    => '编辑友链',
        'desc'     => '按 id 编辑友链名称、链接、样式、图标、排序与显示状态。',
        'params'   => 'JSON：id* / name / url / css_class / icon / sort_order / status(1|0)',
        'example'  => "POST /api/open/friendlink/update  body: {\"id\":1,\"status\":0}",
    ],
    [
        'endpoint' => 'open/friendlink/delete',
        'method'   => 'POST',
        'handler'  => 'open_api_friendlink_delete',
        'group'    => '友情链接插件',
        'title'    => '删除友链',
        'desc'     => '按 id 删除友链。',
        'params'   => 'JSON：id*',
        'example'  => "POST /api/open/friendlink/delete  body: {\"id\":1}",
    ],
];
