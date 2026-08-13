<?php
/**
 * 统一路由分发器
 * 单入口 index.php 的核心逻辑
 */
class Route
{
    /** 分发请求 */
    public static function dispatch(): void
    {
        $route = Rewrite::parseRequest();

        if (!$route) {
            self::error(404, '页面不存在');
            return;
        }

        $page = $route['page'] ?? 'home';

        switch ($page) {
            case 'home':
                self::home();
                break;
            case 'category':
                self::category($route);
                break;
            case 'site':
                self::site($route);
                break;
            case 'search':
                self::search($route);
                break;
            case 'submit':
                self::submit($route);
                break;
            case 'wormhole':
                self::wormhole($route);
                break;
            case 'article_list':
                self::articleList($route);
                break;
            case 'article':
                self::articleDetail($route);
                break;
            case 'sitemap':
                self::sitemap($route);
                break;
            case 'robots':
                self::robots();
                break;
            case 'api':
                self::api($route);
                break;
            default:
                self::error(404, '页面不存在');
        }
    }

    /** API 接口 */
    private static function api(array $route): void
    {
        $action = $route['action'] ?? '';

        header('Content-Type: application/json; charset=utf-8');

        switch ($action) {
            case 'rate':
                $id = (int)($_POST['id'] ?? 0);
                $rating = (int)($_POST['rating'] ?? 0);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

                if (!$id || $rating < 1 || $rating > 5) {
                    echo json_encode(['code' => 1, 'msg' => '参数错误']);
                    return;
                }

                $siteModel = new SiteModel();
                $site = $siteModel->getSite($id);
                if (!$site) {
                    echo json_encode(['code' => 1, 'msg' => '站点不存在']);
                    return;
                }

                $result = $siteModel->submitRating($id, $rating, $ip);
                $stats = $siteModel->getRatingStats($id);
                echo json_encode([
                    'code' => 0,
                    'msg' => '评分成功',
                    'data' => [
                        'avg_rating' => $stats['avg'] ?? 0,
                        'total_ratings' => $stats['count'] ?? 0
                    ]
                ]);
                return;

            default:
                // 转发到 api/index.php 统一处理（update-meta, feedback, click, fetch-tdk 等）
                $_GET['endpoint'] = $action;
                require __DIR__ . '/../api/index.php';
                exit;
        }
    }

    /** 首页 */
    private static function home(): void
    {
        $catModel   = new CategoryModel();
        $siteModel  = new SiteModel();
        $settingsModel = new SettingsModel();

        // 先加载全部设置
        $settings = $settingsModel->loadAll();

        $featuredCount  = (int)($settings['home_featured_count'] ?? '6');
        $catCount       = (int)($settings['home_category_count'] ?? '11');
        $perCategory    = (int)($settings['home_per_category'] ?? '12');

        $categories     = $catModel->getSidebarCategories();
        $activeCats     = array_slice($categories, 0, $catCount);
        $featuredSites  = $siteModel->getGlobalFeatured($featuredCount);

        $siteStats      = $siteModel->getStats();
        $pluginWeight = Plugin::config('submit', 'show_weight', null);
        $showWeight = ($pluginWeight !== null)
            ? (int)($pluginWeight === '1')
            : (int)($settings['show_weight'] ?? '1');
        $ranking        = $siteModel->getRanking(15);

        // 首页TDK：优先用后台SEO设置，未设置时自动生成
        $seoTitle    = $settings['seo_title'] ?? '';
        $seoDesc     = $settings['seo_description'] ?? '';
        $seoKeywords = $settings['seo_keywords'] ?? '';
        if (empty($seoTitle)) {
            $seoTitle = ($settings['site_name'] ?? '') . ' - ' . ($settings['site_slogan'] ?? '');
        }
        if (empty($seoDesc)) {
            $catNames = array_column($activeCats, 'name');
            $seoDesc  = '精选' . implode('、', $catNames) . '等分类的优质站点';
        }
        if (empty($seoKeywords)) {
            $catNames = array_column($activeCats, 'name');
            $seoKeywords = '导航,' . implode(',', $catNames);
        }

        $currentCat  = null;
        $currentSites = [];
        if (!empty($activeCats)) {
            $currentCat = $activeCats[0];
            // 使用 getCategorySites：推荐优先，不足用最新站点补位
            $currentSites = $siteModel->getCategorySites(
                (int)$currentCat['id'], $perCategory, 'newest'
            );
        }

        // 首页只展示数据，浏览量由用户点击进入详情页时统计
        $data = compact(
            'categories', 'activeCats', 'featuredSites',
            'seoTitle', 'seoDesc', 'seoKeywords',
            'siteStats', 'showWeight', 'perCategory',
            'currentCat', 'currentSites', 'settings', 'ranking'
        );
        Theme::render('index', $data);
    }

    /** 分类页 */
    private static function category(array $route): void
    {
        $slug    = $route['slug'] ?? '';
        $page    = $route['cpage'] ?? 1;
        $sort    = $route['sort'] ?? 'newest';

        $catModel  = new CategoryModel();
        $siteModel = new SiteModel();
        $settings  = (new SettingsModel())->loadAll();

        $category = $catModel->getBySlug($slug);
        if (!$category) {
            self::error(404, '分类不存在');
            return;
        }

        // 使用后台设置的每页显示数量
        $perPage = (int)($settings['default_per_page'] ?? 12);
        $perPage = max(6, min(60, $perPage));
        $sites   = $siteModel->getSitesByCategory((int)$category['id'], $page, $perPage, $sort);
        $total   = $siteModel->countByCategory((int)$category['id']);
        $totalPages = (int)ceil($total / max(1, $perPage));

        $showWeight  = (int)($settings['show_weight'] ?? '1');
        $pluginWeight = Plugin::config('submit', 'show_weight', null);
        if ($pluginWeight !== null) { $showWeight = (int)($pluginWeight === '1'); }
        $categories  = $catModel->getSidebarCategories();

        // TDK：优先用分类设置的SEO字段
        $siteName    = $settings['site_name'] ?? '';
        $seoTitle    = $category['seo_title'] ?: $category['name'] . ' - ' . $siteName;
        $seoDesc     = $category['seo_desc']  ?: '收录' . $total . '个优质' . $category['name'] . '，找' . $category['name'] . '就来' . $siteName;
        $seoKeywords = $category['name'] . ',' . $category['slug'] . ',导航,' . $siteName;

        $data = compact(
            'category', 'sites', 'slug', 'page', 'sort',
            'total', 'totalPages', 'perPage',
            'seoTitle', 'seoDesc', 'seoKeywords',
            'showWeight', 'categories', 'settings'
        );
        Theme::render('category', $data);
    }

    /** 站点详情 */
    private static function site(array $route): void
    {
        $id = $route['id'] ?? 0;

        $siteModel = new SiteModel();
        $site = $siteModel->getSite((int)$id);

        if (!$site || $site['status'] !== 'published') {
            self::error(404, '站点不存在或未上线');
            return;
        }

        $siteModel->incrementViews((int)$id);

        $catModel   = new CategoryModel();
        $category   = $catModel->getById((int)$site['category_id']);
        $settings   = (new SettingsModel())->loadAll();
        $perPage    = max(4, min(24, (int)($settings['default_per_page'] ?? 12)));
        $related    = $siteModel->getRelatedSites((int)$site['category_id'], (int)$id, $perPage);
        $categories = $catModel->getSidebarCategories();
        $showWeight = (int)($settings['show_weight'] ?? 1);
        $pluginWeight = Plugin::config('submit', 'show_weight', null);
        if ($pluginWeight !== null) { $showWeight = (int)($pluginWeight === '1'); }

        // 获取评分统计
        $ratingStats = $siteModel->getRatingStats((int)$id);

        // 获取最近7天日统计（趋势图数据）
        $trendData = $siteModel->getDailyStats((int)$id, 7);

        // 站点页TDK（SEO标题=站点名称，SEO关键词=标签）
        $siteName = $settings['site_name'] ?? '';
        $siteTags = parseTags($site['tags'] ?? '[]');
        $tagsStr  = tagsToKeywords($siteTags);
        $seoTitle = $site['name'] . ' - ' . ($category['name'] ?? '导航') . ' | ' . $siteName;
        $seoDesc  = $site['description'] ?: $site['name'] . '是' . ($category['name'] ?? '') . '分类下的优质站点';
        $seoKeywords = $tagsStr;

        $data = compact('site', 'category', 'related', 'categories', 'settings', 'showWeight', 'seoTitle', 'seoDesc', 'seoKeywords', 'ratingStats', 'trendData');
        Theme::render('site', $data);
    }

    /** 搜索页 */
    private static function search(array $route): void
    {
        $keyword = $route['q'] ?? '';
        $page    = $route['cpage'] ?? 1;

        $siteModel = new SiteModel();
        $catModel  = new CategoryModel();

        $perPage = (int)setting('default_per_page', 12);
        $perPage = max(6, min(60, $perPage));
        $sites   = [];
        $total   = 0;

        if ($keyword) {
            $sites = $siteModel->searchPaged($keyword, $page, $perPage);
            $total = $siteModel->searchCount($keyword);
        }

        $totalPages = (int)ceil($total / max(1, $perPage));
        $categories = $catModel->getSidebarCategories();
        $settings   = (new SettingsModel())->loadAll();

        $seoTitle    = $keyword ? $keyword . ' - 搜索结果 - ' . ($settings['site_name'] ?? '') : '搜索 - ' . ($settings['site_name'] ?? '');
        $seoDesc     = $keyword ? '搜索 "' . $keyword . '" 的结果' : '搜索站点';
        $seoKeywords = $keyword;

        $data = compact('keyword', 'sites', 'page', 'total', 'totalPages', 'perPage', 'categories', 'settings', 'seoTitle', 'seoDesc', 'seoKeywords');
        Theme::render('search', $data);
    }

    /** 提交页 */
    private static function submit(array $route): void
    {
        $catModel   = new CategoryModel();
        $categories = $catModel->getSidebarCategories();
        $siteModel = new SiteModel();
        $siteStats = $siteModel->getStats();
        $settingsModel = new SettingsModel();
        $settings   = $settingsModel->loadAll();

        // 提交开关：优先读取 submit 插件配置，兼容旧版 settings.enable_submit
        $pluginEnable = Plugin::config('submit', 'enable_submit', null);
        $enable     = ($pluginEnable !== null)
            ? ($pluginEnable === '1')
            : ($settingsModel->get('enable_submit', '1') === '1');
        // 审核开关：优先读取 submit 插件配置，兼容旧版 settings.need_review
        $pluginNeedReview = Plugin::config('submit', 'need_review', null);
        $needReview = ($pluginNeedReview !== null)
            ? ($pluginNeedReview === '1')
            : ($settingsModel->get('need_review', '1') === '1');

        $seoTitle    = '提交收录 - ' . ($settings['site_name'] ?? '');
        $seoDesc     = '提交您喜欢的网站到' . ($settings['site_name'] ?? '') . '，与更多用户分享优质资源';
        $seoKeywords = '提交网站,网站收录,站长提交';

        $data = compact('categories', 'siteStats', 'settings', 'enable', 'needReview', 'seoTitle', 'seoDesc', 'seoKeywords');
        Theme::render('submit', $data);
    }

    /** 虫洞联盟 */
    private static function wormhole(array $route): void
    {
        $catModel = new CategoryModel();
        $siteModel = new SiteModel();
        $wormholeModel = new WormholeModel();
        $settingsModel = new SettingsModel();

        $settings = $settingsModel->loadAll();
        $categories = $catModel->getSidebarCategories();
        $siteStats = $siteModel->getStats();
        $wormholeStats = $wormholeModel->getStats();
        $members = $wormholeModel->getMembers();

        $seoTitle = ($settings['site_name'] ?? '懒人导航') . ' - 虫洞联盟';
        $seoDesc = '探索虫洞联盟，发现更多优质站点';
        $seoKeywords = '虫洞联盟,网站导航,优质站点';

        $data = compact('categories', 'siteStats', 'wormholeStats', 'members', 'settings', 'seoTitle', 'seoDesc', 'seoKeywords');
        Theme::render('wormhole', $data);
    }

    /** 文章列表页 */
    private static function articleList(array $route): void
    {
        // 检查文章插件是否启用
        if (!Plugin::isEnabled('article')) {
            self::error(404, '文章功能未开启');
            return;
        }

        $page = $route['apage'] ?? 1;
        $perPage = 10;

        $articleModel = new ArticleModel();
        $articles = $articleModel->getList($page, $perPage);
        $total = $articleModel->count();
        $totalPages = (int)ceil($total / max(1, $perPage));

        $catModel = new CategoryModel();
        $settingsModel = new SettingsModel();
        $categories = $catModel->getSidebarCategories();
        $settings = $settingsModel->loadAll();

        $seoTitle = '文章 - ' . ($settings['site_name'] ?? '懒人导航');
        $seoDesc = '最新文章资讯';
        $seoKeywords = '文章,资讯';

        $data = compact('articles', 'page', 'total', 'totalPages', 'perPage', 'categories', 'settings', 'seoTitle', 'seoDesc', 'seoKeywords');
        Theme::render('article_list', $data);
    }

    /** 文章详情页 */
    private static function articleDetail(array $route): void
    {
        // 检查文章插件是否启用
        if (!Plugin::isEnabled('article')) {
            self::error(404, '文章功能未开启');
            return;
        }

        $id = (int)($route['id'] ?? 0);
        if ($id <= 0) {
            self::error(404, '文章不存在');
            return;
        }

        $articleModel = new ArticleModel();
        $article = $articleModel->getById($id);

        if (!$article || $article['status'] !== 'published') {
            self::error(404, '文章不存在或未发布');
            return;
        }

        $articleModel->incrementViews($id);

        $catModel = new CategoryModel();
        $settingsModel = new SettingsModel();
        $categories = $catModel->getSidebarCategories();
        $settings = $settingsModel->loadAll();

        $seoTitle = ($article['title'] ?? '文章') . ' - ' . ($settings['site_name'] ?? '懒人导航');
        $seoDesc = mb_strimwidth(strip_tags($article['content'] ?? ''), 0, 150, '...');
        $seoKeywords = $article['tags'] ?? '';

        $data = compact('article', 'categories', 'settings', 'seoTitle', 'seoDesc', 'seoKeywords');
        Theme::render('article_detail', $data);
    }

    /** 网站地图 (sitemap.xml) */
    private static function sitemap(array $route): void
    {
        $sitemap = new SitemapModel();
        $shard = $route['shard'] ?? 0;

        header('Content-Type: application/xml; charset=utf-8');

        if ($shard > 0) {
            // 分片 sitemap
            $result = $sitemap->getShardContent($shard);
            if ($result['found']) {
                echo $result['xml'];
            } else {
                http_response_code(404);
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
            }
        } else {
            // 主 sitemap（自动判断是否为 index）
            $result = $sitemap->getContent();
            echo $result['xml'];
        }
    }

    /** robots.txt */
    private static function robots(): void
    {
        $sitemap = new SitemapModel();
        header('Content-Type: text/plain; charset=utf-8');
        echo $sitemap->generateRobotsTxt();
    }

    /** 错误页 */
    private static function error(int $code, string $message): void
    {
        http_response_code($code);
        $settings = (new SettingsModel())->loadAll();
        Theme::render('error', compact('code', 'message', 'settings'));
    }
}
