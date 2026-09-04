<?php
/**
 * 开放 API 注册表
 *
 * 职责：
 *   1. 集中维护核心 open/* 接口清单（路由分发、后台「API 密钥」使用说明、对接文档共用同一份数据）
 *   2. 自动加载内置插件目录下的 api.php 接口声明：
 *      - 插件启用后，其声明的接口自动生效（无需改动 api/index.php）
 *      - 后台「API 密钥」使用说明自动列出已启用插件的查询案例与使用说明
 *
 * 插件接口声明文件约定（可选）：plugins/{插件名}/api.php
 *   <?php
 *   if (!defined('APP_VERSION') || !class_exists('Plugin')) { die('Forbidden'); }
 *
 *   function open_api_article_list(): void { ... }   // 处理器函数，命名建议 open_api_{插件}_{动作}
 *
 *   return [
 *       [
 *           'endpoint' => 'open/article/list',   // 端点名（URL 中 /api/open/article/list）
 *           'method'   => 'GET',                 // 请求方法
 *           'handler'  => 'open_api_article_list',// 处理器函数名
 *           'group'    => '文章插件',             // 文档分组
 *           'title'    => '文章列表',             // 文档标题
 *           'desc'     => '说明',
 *           'params'   => 'page / limit / status',
 *           'example'  => 'GET /api/open/article/list?page=1',
 *       ],
 *   ];
 */

class OpenApi
{
    /** 已加载的全部插件接口缓存 */
    private static ?array $allPluginCache = null;

    /**
     * 判断端点是否为需要 API Key 的开放接口
     */
    public static function isOpen(string $endpoint): bool
    {
        return strpos($endpoint, 'open/') === 0;
    }

    /**
     * 核心 open/* 接口清单
     * @return array[]
     */
    public static function coreEndpoints(): array
    {
        return [
            // ===== 查询类 =====
            [
                'endpoint' => 'open/sites',
                'method'   => 'GET',
                'handler'  => 'api_open_sites',
                'group'    => '站点查询',
                'title'    => '站点列表',
                'desc'     => '获取已发布站点分页列表，支持分类筛选与排序。',
                'params'   => 'category=all|分类slug, page, limit(1-100), sort=views|clicks|br|newest|name',
                'example'  => 'GET /api/open/sites?category=all&page=1&limit=20&sort=newest',
            ],
            [
                'endpoint' => 'open/site',
                'method'   => 'GET',
                'handler'  => 'api_open_site_detail',
                'group'    => '站点查询',
                'title'    => '站点详情',
                'desc'     => '按站点 ID 获取已发布站点详情。',
                'params'   => 'id（必填）',
                'example'  => 'GET /api/open/site?id=1',
            ],
            [
                'endpoint' => 'open/site/check',
                'method'   => 'GET',
                'handler'  => 'api_open_site_check',
                'group'    => '站点查询',
                'title'    => '网址收录查询',
                'desc'     => '检查一个网址是否已被收录及其状态（pending=待审核 / published=已收录 / 未收录）。可用于 App、小程序查询收录进度，也可用于前台提交前去重检测。',
                'params'   => 'url（必填，如 https://example.com）',
                'example'  => 'GET /api/open/site/check?url=https://example.com',
            ],
            [
                'endpoint' => 'open/site/related',
                'method'   => 'GET',
                'handler'  => 'api_open_related',
                'group'    => '站点查询',
                'title'    => '相关站点',
                'desc'     => '按站点 ID 获取同分类下的相关站点。',
                'params'   => 'id（必填）, limit(1-12, 默认6)',
                'example'  => 'GET /api/open/site/related?id=1&limit=6',
            ],
            [
                'endpoint' => 'open/featured',
                'method'   => 'GET',
                'handler'  => 'api_open_featured',
                'group'    => '站点查询',
                'title'    => '推荐位站点',
                'desc'     => '获取全局推荐（精选）站点列表。',
                'params'   => 'limit(1-50, 默认12)',
                'example'  => 'GET /api/open/featured?limit=12',
            ],
            [
                'endpoint' => 'open/rank',
                'method'   => 'GET',
                'handler'  => 'api_open_rank',
                'group'    => '站点查询',
                'title'    => '排行榜',
                'desc'     => '获取站点排行榜。',
                'params'   => 'type=views|clicks|br_pc|br_mobile|newest, limit(1-100, 默认20)',
                'example'  => 'GET /api/open/rank?type=views&limit=20',
            ],
            [
                'endpoint' => 'open/search',
                'method'   => 'GET',
                'handler'  => 'api_open_search',
                'group'    => '站点查询',
                'title'    => '搜索站点',
                'desc'     => '按关键词搜索站点。',
                'params'   => 'q（必填）, page, limit(1-100)',
                'example'  => 'GET /api/open/search?q=AI&page=1&limit=20',
            ],
            [
                'endpoint' => 'open/stats',
                'method'   => 'GET',
                'handler'  => 'api_open_stats',
                'group'    => '站点查询',
                'title'    => '站点统计',
                'desc'     => '获取站点整体统计数据（总数、浏览量、点击量、平均权重等）。',
                'params'   => '无',
                'example'  => 'GET /api/open/stats',
            ],
            [
                'endpoint' => 'open/plugins',
                'method'   => 'GET',
                'handler'  => 'api_open_plugins',
                'group'    => '系统查询',
                'title'    => '插件列表',
                'desc'     => '获取系统已安装的插件及其启用状态，方便客户端判断哪些插件接口可用。',
                'params'   => '无',
                'example'  => 'GET /api/open/plugins',
            ],

            // ===== 发布 / 编辑 / 删除（API Key 视为受信凭证，可操作任意站点） =====
            [
                'endpoint' => 'open/submit',
                'method'   => 'POST',
                'handler'  => 'api_open_submit',
                'group'    => '站点发布',
                'title'    => '发布 / 提交站点',
                'desc'     => '新增一个站点。带 API Key 的调用等同于后台录入：默认直接发布，可用 status=pending 改为待审核。App / 小程序 / 前台自定义提交页均可直接调用此接口。',
                'params'   => 'JSON：name* / url* / category_id* / description / tags(数组或逗号分隔) / email / br_pc / br_mobile / br_360 / br_shenma / status(published|pending)',
                'example'  => "POST /api/open/submit  body: {\"name\":\"示例站\",\"url\":\"https://example.com\",\"category_id\":1,\"description\":\"简介\",\"tags\":[\"工具\"]}",
            ],
            [
                'endpoint' => 'open/site/update',
                'method'   => 'POST',
                'handler'  => 'api_open_site_update',
                'group'    => '站点编辑',
                'title'    => '编辑站点',
                'desc'     => '按 id 编辑站点信息，传哪些字段就更新哪些字段（部分更新）。',
                'params'   => 'JSON：id* / name / url / category_id / description / tags / br_pc / br_mobile / br_360 / br_shenma / is_featured(0|1) / sort_order / status(published|pending)',
                'example'  => "POST /api/open/site/update  body: {\"id\":1,\"name\":\"新名称\",\"status\":\"published\"}",
            ],
            [
                'endpoint' => 'open/site/delete',
                'method'   => 'POST',
                'handler'  => 'api_open_site_delete',
                'group'    => '站点编辑',
                'title'    => '删除站点',
                'desc'     => '按 id 物理删除站点（与后台删除一致，会同时清理推荐、统计关联数据）。',
                'params'   => 'JSON：id*',
                'example'  => "POST /api/open/site/delete  body: {\"id\":1}",
            ],

            // ===== 分类管理 =====
            [
                'endpoint' => 'open/categories',
                'method'   => 'GET',
                'handler'  => 'api_open_categories',
                'group'    => '分类管理',
                'title'    => '分类列表',
                'desc'     => '获取分类列表（含站点数、SEO 信息）。',
                'params'   => 'all=1 返回全部分类（含隐藏，管理端用）；默认仅前台展示分类',
                'example'  => 'GET /api/open/categories?all=1',
            ],
            [
                'endpoint' => 'open/category/create',
                'method'   => 'POST',
                'handler'  => 'api_open_category_create',
                'group'    => '分类管理',
                'title'    => '新增分类',
                'desc'     => '新增一个分类，slug 需唯一且仅含小写字母、数字、中划线。',
                'params'   => 'JSON：name* / slug* / icon / sort_order / show_count / is_show(1|0) / seo_title / seo_desc / fill_sort(newest|views|br)',
                'example'  => "POST /api/open/category/create  body: {\"name\":\"AI 工具\",\"slug\":\"ai\",\"is_show\":1}",
            ],
            [
                'endpoint' => 'open/category/update',
                'method'   => 'POST',
                'handler'  => 'api_open_category_update',
                'group'    => '分类管理',
                'title'    => '编辑分类',
                'desc'     => '按 id 编辑分类信息，传哪些字段就更新哪些字段。',
                'params'   => 'JSON：id* / name / slug / icon / sort_order / show_count / is_show(1|0) / seo_title / seo_desc / fill_sort',
                'example'  => "POST /api/open/category/update  body: {\"id\":1,\"name\":\"AI 工具箱\",\"is_show\":0}",
            ],
            [
                'endpoint' => 'open/category/delete',
                'method'   => 'POST',
                'handler'  => 'api_open_category_delete',
                'group'    => '分类管理',
                'title'    => '删除分类',
                'desc'     => '按 id 删除分类。分类下仍有站点时拒绝删除（与后台一致），请先迁移或删除站点。',
                'params'   => 'JSON：id*',
                'example'  => "POST /api/open/category/delete  body: {\"id\":1}",
            ],
        ];
    }

    /**
     * 获取全部插件（含未启用）声明的接口定义
     * @return array[]
     */
    public static function allPluginEndpoints(): array
    {
        if (self::$allPluginCache !== null) {
            return self::$allPluginCache;
        }

        $defs = [];
        foreach (Plugin::scan() as $name => $info) {
            $file = Plugin::getDir($name) . '/api.php';
            if (!is_file($file)) {
                continue;
            }
            $declared = @include $file;
            if (!is_array($declared)) {
                continue;
            }
            $enabled = Plugin::isEnabled($name);
            foreach ($declared as $def) {
                if (!is_array($def) || empty($def['endpoint'])) {
                    continue;
                }
                $def['plugin']          = $name;
                $def['plugin_title']    = $info['title'] ?? $name;
                $def['plugin_enabled']  = $enabled;
                $defs[] = $def;
            }
        }

        self::$allPluginCache = $defs;
        return $defs;
    }

    /**
     * 获取已启用插件的接口定义（启用插件 → 接口自动注册并出现在文档）
     * @return array[]
     */
    public static function enabledPluginEndpoints(): array
    {
        $result = [];
        foreach (self::allPluginEndpoints() as $def) {
            if (!empty($def['plugin_enabled'])) {
                $result[] = $def;
            }
        }
        return $result;
    }

    /**
     * 全部开放接口定义（核心 + 已启用插件）
     * @return array[]
     */
    public static function allEndpoints(): array
    {
        return array_merge(self::coreEndpoints(), self::enabledPluginEndpoints());
    }

    /**
     * 构建 核心 open/* 接口路由表（不加载任何插件文件）
     * @return array
     */
    public static function coreRoutes(): array
    {
        $routes = [];
        foreach (self::coreEndpoints() as $def) {
            if (!empty($def['handler'])) {
                $routes[$def['endpoint']] = [$def['method'], $def['handler']];
            }
        }
        return $routes;
    }

    /**
     * 构建 已启用插件 接口路由表（仅当有 open/* 请求时才应调用，避免每次请求加载插件 api.php）
     * @return array
     */
    public static function pluginRoutes(): array
    {
        $routes = [];
        foreach (self::enabledPluginEndpoints() as $def) {
            if (!empty($def['handler'])) {
                $routes[$def['endpoint']] = [$def['method'], $def['handler']];
            }
        }
        return $routes;
    }

    /**
     * 构建 端点 => [请求方法, 处理器函数名] 路由表（核心 + 已启用插件）
     * @return array
     */
    public static function buildRoutes(): array
    {
        return array_merge(self::coreRoutes(), self::pluginRoutes());
    }

    /**
     * 在全部插件（含未启用）声明中查找端点定义
     * 用于：端点属于某个插件但插件未启用时给出明确提示
     */
    public static function findPluginDef(string $endpoint): ?array
    {
        foreach (self::allPluginEndpoints() as $def) {
            if (($def['endpoint'] ?? '') === $endpoint) {
                return $def;
            }
        }
        return null;
    }
}
