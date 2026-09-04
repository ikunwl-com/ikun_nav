<?php
/**
 * 文章插件 - 开放 API 接口声明
 *
 * 本文件由 core/OpenApi.php 自动加载：
 *   - 插件「启用」后，下方接口自动注册（/api/open/article/*，需 API Key）
 *   - 后台「API 密钥 - 使用说明」自动展示本文件的查询案例与使用说明
 *   - 插件「停用」后，接口自动失效（访问时提示需启用插件）
 *
 * 处理器依赖的 ArticleModel 由插件 include.php 提供（启用后自动加载）。
 */

if (!defined('APP_VERSION') || !class_exists('Database') || !class_exists('Plugin')) {
    die('Forbidden');
}

/**
 * GET /api/open/article/list?page=1&limit=10&status=published
 * 文章列表（不含正文）
 */
function open_api_article_list(): void
{
    $page   = max(1, min(100, (int)($_GET['page'] ?? 1)));
    $limit  = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $status = Security::enum($_GET['status'] ?? 'published', ['published', 'draft', 'pending', 'all'], 'published');

    $model = new ArticleModel();
    $rows  = $model->getList($page, $limit, $status);
    $total = $model->count($status);

    $list = array_map(function ($a) {
        return [
            'id'         => (int)$a['id'],
            'title'      => $a['title'],
            'slug'       => $a['slug'] ?? '',
            'excerpt'    => $a['excerpt'] ?? '',
            'author'     => $a['author'] ?? '',
            'category'   => $a['category'] ?? '',
            'tags'       => $a['tags'] ?? '',
            'status'     => $a['status'] ?? 'published',
            'views'      => (int)($a['views'] ?? 0),
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'],
        ];
    }, $rows);

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'list'        => array_values($list),
            'total'       => (int)$total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
            'status'      => $status,
        ],
    ]);
}

/**
 * GET /api/open/article/detail?id=1  或  ?slug=xxx
 * 文章详情（含正文）
 */
function open_api_article_detail(): void
{
    $id   = (int)($_GET['id'] ?? 0);
    $slug = Security::cleanString($_GET['slug'] ?? '');
    if ($id <= 0 && $slug === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少 id 或 slug 参数'], 400);
    }

    $model = new ArticleModel();
    $article = $id > 0 ? $model->getById($id) : $model->getBySlug($slug);
    if (!$article) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '文章不存在'], 404);
    }

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => 'ok',
        'data'    => [
            'id'         => (int)$article['id'],
            'title'      => $article['title'],
            'slug'       => $article['slug'] ?? '',
            'content'    => $article['content'] ?? '',
            'excerpt'    => $article['excerpt'] ?? '',
            'author'     => $article['author'] ?? '',
            'category'   => $article['category'] ?? '',
            'tags'       => $article['tags'] ?? '',
            'status'     => $article['status'] ?? 'published',
            'views'      => (int)($article['views'] ?? 0),
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'],
        ],
    ]);
}

/**
 * 标签入参转逗号分隔字符串（文章表 tags 为逗号分隔文本）
 */
function open_api_article_tags($tags): string
{
    $arr = [];
    if (is_array($tags)) {
        $arr = $tags;
    } elseif (is_string($tags) && $tags !== '') {
        $trimmed = trim($tags);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $arr = $decoded;
            }
        } else {
            $arr = explode(',', $trimmed);
        }
    }
    return implode(',', Security::cleanTags($arr));
}

/**
 * POST /api/open/article/publish - 发布文章
 */
function open_api_article_publish(): void
{
    $input = api_json_input();
    $title = Security::cleanString($input['title'] ?? '', 200);
    if ($title === '') {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '标题不能为空'], 400);
    }

    $model = new ArticleModel();
    $id = $model->create([
        'title'    => $title,
        'slug'     => Security::cleanString($input['slug'] ?? '', 200),
        'content'  => Security::cleanString($input['content'] ?? '', 0),
        'excerpt'  => Security::cleanString($input['excerpt'] ?? '', 500),
        'author'   => Security::cleanString($input['author'] ?? '', 100),
        'category' => Security::cleanString($input['category'] ?? '', 100),
        'tags'     => open_api_article_tags($input['tags'] ?? ''),
        'status'   => Security::enum($input['status'] ?? '', ['published', 'draft', 'pending'], 'published'),
    ]);

    api_open_log("[开放API-发布文章] 文章ID={$id} 标题={$title}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '文章发布成功',
        'id'      => $id,
    ]);
}

/**
 * POST /api/open/article/update - 编辑文章（部分更新）
 */
function open_api_article_update(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少文章ID参数'], 400);
    }

    $model = new ArticleModel();
    if (!$model->getById($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '文章不存在'], 404);
    }

    $data = [];
    if (array_key_exists('title', $input)) {
        $title = Security::cleanString($input['title'] ?? '', 200);
        if ($title === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '标题不能为空'], 400);
        }
        $data['title'] = $title;
    }
    if (array_key_exists('slug', $input)) {
        $data['slug'] = Security::cleanString($input['slug'] ?? '', 200);
    }
    if (array_key_exists('content', $input)) {
        $data['content'] = Security::cleanString($input['content'] ?? '', 0);
    }
    if (array_key_exists('excerpt', $input)) {
        $data['excerpt'] = Security::cleanString($input['excerpt'] ?? '', 500);
    }
    if (array_key_exists('author', $input)) {
        $data['author'] = Security::cleanString($input['author'] ?? '', 100);
    }
    if (array_key_exists('category', $input)) {
        $data['category'] = Security::cleanString($input['category'] ?? '', 100);
    }
    if (array_key_exists('tags', $input)) {
        $data['tags'] = open_api_article_tags($input['tags']);
    }
    if (array_key_exists('status', $input)) {
        $status = Security::enum($input['status'] ?? '', ['published', 'draft', 'pending'], '');
        if ($status === '') {
            Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => 'status 仅支持 published / draft / pending'], 400);
        }
        $data['status'] = $status;
    }

    if (empty($data)) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '没有可更新的字段'], 400);
    }

    $model->update($id, $data);
    api_open_log("[开放API-编辑文章] 文章ID={$id} 更新字段=" . implode(',', array_keys($data)));

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '更新成功',
        'id'      => $id,
        'updated' => array_keys($data),
    ]);
}

/**
 * POST /api/open/article/delete - 删除文章
 */
function open_api_article_delete(): void
{
    $input = api_json_input();
    $id    = Security::int($input['id'] ?? 0);
    if ($id <= 0) {
        Security::jsonOutput(['success' => false, 'code' => 40001, 'message' => '缺少文章ID参数'], 400);
    }

    $model = new ArticleModel();
    if (!$model->getById($id)) {
        Security::jsonOutput(['success' => false, 'code' => 40401, 'message' => '文章不存在'], 404);
    }

    $model->delete($id);
    api_open_log("[开放API-删除文章] 文章ID={$id}");

    Security::jsonOutput([
        'success' => true,
        'code'    => 0,
        'message' => '删除成功',
        'id'      => $id,
    ]);
}

return [
    [
        'endpoint' => 'open/article/list',
        'method'   => 'GET',
        'handler'  => 'open_api_article_list',
        'group'    => '文章插件',
        'title'    => '文章列表',
        'desc'     => '获取文章分页列表（不含正文）。',
        'params'   => 'page, limit(1-50, 默认10), status=published|draft|pending|all(默认published)',
        'example'  => 'GET /api/open/article/list?page=1&limit=10&status=published',
    ],
    [
        'endpoint' => 'open/article/detail',
        'method'   => 'GET',
        'handler'  => 'open_api_article_detail',
        'group'    => '文章插件',
        'title'    => '文章详情',
        'desc'     => '按 id 或 slug 获取单篇文章（含正文）。',
        'params'   => 'id 或 slug（二选一）',
        'example'  => 'GET /api/open/article/detail?id=1',
    ],
    [
        'endpoint' => 'open/article/publish',
        'method'   => 'POST',
        'handler'  => 'open_api_article_publish',
        'group'    => '文章插件',
        'title'    => '发布文章',
        'desc'     => '新增一篇文章，默认直接发布（可用 status 改为 draft / pending）。',
        'params'   => 'JSON：title* / slug / content / excerpt / author / category / tags(数组或逗号分隔) / status(published|draft|pending)',
        'example'  => "POST /api/open/article/publish  body: {\"title\":\"公告\",\"content\":\"<p>正文</p>\",\"status\":\"published\"}",
    ],
    [
        'endpoint' => 'open/article/update',
        'method'   => 'POST',
        'handler'  => 'open_api_article_update',
        'group'    => '文章插件',
        'title'    => '编辑文章',
        'desc'     => '按 id 编辑文章，传哪些字段就更新哪些字段。',
        'params'   => 'JSON：id* / title / slug / content / excerpt / author / category / tags / status',
        'example'  => "POST /api/open/article/update  body: {\"id\":1,\"title\":\"新标题\"}",
    ],
    [
        'endpoint' => 'open/article/delete',
        'method'   => 'POST',
        'handler'  => 'open_api_article_delete',
        'group'    => '文章插件',
        'title'    => '删除文章',
        'desc'     => '按 id 删除文章。',
        'params'   => 'JSON：id*',
        'example'  => "POST /api/open/article/delete  body: {\"id\":1}",
    ],
];
