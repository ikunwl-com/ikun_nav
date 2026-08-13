<?php
/**
 * 伪静态系统 - URL 解析、生成、规则管理
 * 支持三种模式：dynamic（动态）/ rewrite（伪静态）/ index（index.php式）
 */
class Rewrite
{
    // 默认 URL 格式
    private static array $defaults = [
        'home'          => '/',
        'category'      => 'category/{%slug%}/',
        'category_page' => 'category/{%slug%}/page-{%page%}/',
        'site'          => 'site/{%id%}/',
        'search'        => 'search/',
        'submit'        => 'submit/',
        'wormhole'      => 'wormhole/',
        'article_list'  => 'articles/',
        'article'       => 'article/{%id%}/',
    ];

    /** 获取配置 */
    public static function getConfig(): array
    {
        $cfg = ['mode' => 'dynamic'];
        foreach (self::$defaults as $key => $def) {
            $cfg[$key] = $def;
        }

        $settings = new SettingsModel();
        $mode = $settings->get('rewrite_mode', 'dynamic');
        if (in_array($mode, ['dynamic', 'rewrite', 'index'], true)) {
            $cfg['mode'] = $mode;
        }
        foreach (array_keys(self::$defaults) as $key) {
            $val = $settings->get('url_format_' . $key);
            if ($val) {
                $cfg[$key] = $val;
            }
        }
        return $cfg;
    }

    /** 保存配置 */
    public static function saveConfig(array $data): void
    {
        $settings = new SettingsModel();
        $settings->set('rewrite_mode', $data['mode'] ?? 'dynamic');
        foreach (array_keys(self::$defaults) as $key) {
            if (isset($data[$key])) {
                $settings->set('url_format_' . $key, $data[$key]);
            }
        }
    }

    /** 解析当前请求 → 路由参数 */
    public static function parseRequest(): ?array
    {
        $config = self::getConfig();
        $mode   = $config['mode'];
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH) ?? '/';

        // PHP 内置服务器使用路由脚本时，SCRIPT_NAME 会被设置为请求的 URL
        // 此时应该使用 SCRIPT_FILENAME 来判断是否是路由脚本
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $isRouterScript = (php_sapi_name() === 'cli-server' && 
                           basename($scriptFilename) === 'index.php' && 
                           $scriptName !== '/index.php');
        
        // 减去项目基础路径（支持子目录部署）
        if (!$isRouterScript) {
            $scriptPath = parse_url($scriptName, PHP_URL_PATH) ?? '/';
            $basePath   = rtrim(dirname($scriptPath), '/\\');
            if ($basePath !== '' && $basePath !== '/' && strpos($path, $basePath) === 0) {
                $path = substr($path, strlen($basePath));
            }
        }

        // index.php 模式：去掉 /index.php 前缀
        if ($mode === 'index' && strpos($path, '/index.php') === 0) {
            $path = substr($path, 10);
        }

        $path = trim($path, '/');

        if ($mode === 'dynamic') {
            return self::parseDynamic();
        }
        return self::parsePath($path, $config);
    }

    /** 动态模式：从 $_GET 解析 */
    private static function parseDynamic(): ?array
    {
        // sitemap.xml / robots.txt 在动态模式下也通过 PATH 判断
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        if ($path === 'sitemap.xml') {
            return ['page' => 'sitemap'];
        }
        if ($path === 'robots.txt') {
            return ['page' => 'robots'];
        }
        if (preg_match('/^sitemap-(\d+)\.xml$/', $path, $m)) {
            return ['page' => 'sitemap', 'shard' => (int)$m[1]];
        }

        $route = $_GET['route'] ?? 'home';
        switch ($route) {
            case 'home':
            case '':
                return ['page' => 'home'];
            case 'category':
                $slug = Security::validateSlug($_GET['slug'] ?? '');
                if (empty($slug)) {
                    return null;
                }
                return [
                    'page'  => 'category',
                    'slug'  => $slug,
                    'cpage' => max(1, Security::int($_GET['page'] ?? 1)),
                    'sort'  => Security::enum($_GET['sort'] ?? 'newest', ['br', 'newest', 'views', 'clicks', 'time'], 'newest'),
                ];
            case 'site':
                $id = Security::int($_GET['id'] ?? 0);
                if ($id <= 0) {
                    return null;
                }
                return ['page' => 'site', 'id' => $id];
            case 'search':
                return [
                    'page'  => 'search',
                    'q'     => Security::cleanString($_GET['q'] ?? '', 100),
                    'cpage' => max(1, Security::int($_GET['page'] ?? 1)),
                ];
            case 'submit':
                return ['page' => 'submit'];
            case 'wormhole':
                return ['page' => 'wormhole'];
            case 'articles':
                return [
                    'page'  => 'article_list',
                    'apage' => max(1, Security::int($_GET['page'] ?? 1)),
                ];
            case 'article':
                $aid = Security::int($_GET['id'] ?? 0);
                if ($aid <= 0) {
                    return null;
                }
                return ['page' => 'article', 'id' => $aid];
            default:
                return null;
        }
    }

    /** 伪静态 / index.php 模式：从 PATH 解析 */
    private static function parsePath(string $path, array $config): ?array
    {
        // API 接口路由
        if (strpos($path, 'api/') === 0) {
            $action = substr($path, 4);
            return ['page' => 'api', 'action' => $action];
        }

        // sitemap.xml / robots.txt 固定路由
        if ($path === 'sitemap.xml') {
            return ['page' => 'sitemap'];
        }
        if ($path === 'robots.txt') {
            return ['page' => 'robots'];
        }
        // sitemap 分片：sitemap-1.xml, sitemap-2.xml ...
        if (preg_match('/^sitemap-(\d+)\.xml$/', $path, $m)) {
            return ['page' => 'sitemap', 'shard' => (int)$m[1]];
        }

        // === 步骤1：硬匹配固定路径（无变量），最高优先级 ===
        $fixedPaths = [
            'submit'       => $config['submit'] ?? self::$defaults['submit'],
            'search'       => $config['search'] ?? self::$defaults['search'],
            'wormhole'     => $config['wormhole'] ?? self::$defaults['wormhole'],
            'article_list' => $config['article_list'] ?? self::$defaults['article_list'],
        ];
        foreach ($fixedPaths as $type => $fmt) {
            $re = self::fmtToRegex($fmt);
            if (preg_match($re, $path, $m)) {
                if ($type === 'search') {
                    return [
                        'page'  => 'search',
                        'q'     => Security::cleanString($_GET['q'] ?? '', 100),
                        'cpage' => max(1, Security::int($_GET['page'] ?? 1)),
                    ];
                }
                if ($type === 'submit') {
                    return ['page' => 'submit'];
                }
                if ($type === 'wormhole') {
                    return ['page' => 'wormhole'];
                }
                if ($type === 'article_list') {
                    return [
                        'page'  => 'article_list',
                        'apage' => max(1, Security::int($_GET['page'] ?? 1)),
                    ];
                }
            }
        }

        // === 步骤2：带变量的路径，按长度降序（分页 > 单页） ===
        $rules = [
            'category_page' => $config['category_page'] ?? self::$defaults['category_page'],
            'category'      => $config['category']      ?? self::$defaults['category'],
            'site'          => $config['site']          ?? self::$defaults['site'],
            'article'       => $config['article']       ?? self::$defaults['article'],
        ];
        uasort($rules, function($a, $b) { return strlen($b) - strlen($a); });

        foreach ($rules as $type => $fmt) {
            $re = self::fmtToRegex($fmt);
            if (preg_match($re, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                if ($type === 'category' || $type === 'category_page') {
                    $params['sort'] = Security::enum($_GET['sort'] ?? 'newest', ['br', 'newest', 'views', 'clicks', 'time'], 'newest');
                }
                if ($type === 'category_page') {
                    $params['cpage'] = (int)($params['page'] ?? 1);
                    unset($params['page']);
                }
                return array_merge(['page' => str_replace('_page', '', $type)], $params);
            }
        }

        // 首页
        if ($path === '' || $path === '/') {
            return ['page' => 'home'];
        }
        return null;
    }

    /** URL 格式 → 正则 */
    public static function fmtToRegex(string $fmt): string
    {
        $fmt = trim($fmt, '/');

        // 按占位符拆分字符串，保留分隔符
        // 匹配模式：{%slug%}、{%id%}、{%page%}、{%q%}
        $parts = preg_split('/(\{%[a-zA-Z]+%\})/', $fmt, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $part) {
            switch ($part) {
                case '{%slug%}':
                    $result .= '(?P<slug>[a-zA-Z0-9_\-]+)';
                    break;
                case '{%id%}':
                    $result .= '(?P<id>[0-9]+)';
                    break;
                case '{%page%}':
                    $result .= '(?P<page>[0-9]+)';
                    break;
                case '{%q%}':
                    $result .= '(?P<q>[^/]+)';
                    break;
                default:
                    $result .= preg_quote($part, '/');
            }
        }

        return '/^' . $result . '$/';
    }

    /** 生成 URL（模板中调用） */
    public static function url(string $type, array $params = []): string
    {
        $config = self::getConfig();
        $mode   = $config['mode'] ?? 'dynamic';

        // 分页超过 1 时使用分页格式
        $cpage = $params['page'] ?? 1;
        if ($type === 'category' && $cpage > 1) {
            $type = 'category_page';
            $params['page'] = $cpage;
        }

        if ($mode === 'dynamic') {
            return self::buildDynamicUrl($type, $params);
        }
        return self::buildRewriteUrl($type, $params, $config);
    }

    /** 动态模式 URL */
    private static function buildDynamicUrl(string $type, array $params): string
    {
        // article_list → route=articles, article → route=article
        $route = str_replace('_page', '', $type);
        if ($route === 'article_list') {
            $route = 'articles';
        }
        $query = ['route' => $route];
        foreach ($params as $k => $v) {
            if ($k === 'page') {
                $query['page'] = $v;
            } else {
                $query[$k] = $v;
            }
        }
        return '/index.php?' . http_build_query($query);
    }

    /** 伪静态 / index.php 模式 URL */
    private static function buildRewriteUrl(string $type, array $params, array $config): string
    {
        $fmt = $config[$type] ?? self::$defaults[$type] ?? '/';
        $url = $fmt;
        $queryParams = [];
        foreach ($params as $k => $v) {
            $placeholder = '{%' . $k . '%}';
            if (strpos($url, $placeholder) !== false) {
                $url = str_replace($placeholder, (string)$v, $url);
            } else {
                $queryParams[$k] = $v;
            }
        }
        // 清理模板中未使用的占位符
        $url = preg_replace('/{%[^%]+%}/', '', $url);
        // 移除连续斜杠
        $url = preg_replace('#/+#', '/', $url);
        // 只有分页和排序参数才追加为查询字符串（伪静态下 slug 等不应作为查询参数）
        $allowedQuery = [];
        foreach (['page', 'sort'] as $qk) {
            if (isset($queryParams[$qk])) {
                $allowedQuery[$qk] = $queryParams[$qk];
            }
        }
        if (!empty($allowedQuery)) {
            $url .= '?' . http_build_query($allowedQuery);
        }
        return '/' . ltrim($url, '/');
    }

    /** 生成 Apache .htaccess */
    public static function generateHtaccess(): string
    {
        $mode = self::getConfig()['mode'] ?? 'dynamic';
        if ($mode === 'dynamic') {
            return "# 动态模式：前台 URL 带参数，无需伪静态重写\n";
        }

        $rules = "# 懒人导航 - 自动生成的伪静态规则\n";
        $rules .= "RewriteEngine On\n";
        $rules .= "RewriteBase /\n\n";
        $rules .= "# 安全：禁止访问敏感目录\n";
        $rules .= "RewriteRule ^core/ - [F,L]\n";
        $rules .= "RewriteRule ^config/ - [F,L]\n";
        $rules .= "RewriteRule ^install/ - [F,L]\n";
        $rules .= "RewriteRule ^\.git/ - [F,L]\n";
        $rules .= "\n# 拒绝直接访问模板 PHP 文件（安全）\n";
        $rules .= "RewriteRule ^templates/.*\\.php$ - [F,L]\n";
        $rules .= "\n# 存在的文件/目录直接访问\n";
        $rules .= "RewriteCond %{REQUEST_FILENAME} -f [OR]\n";
        $rules .= "RewriteCond %{REQUEST_FILENAME} -d\n";
        $rules .= "RewriteRule ^ - [L]\n\n";

        if ($mode === 'index') {
            $rules .= "# index.php 模式：URL 已包含 index.php，无需额外重写\n";
        } else {
            $rules .= "# 伪静态模式：所有前台请求 → index.php\n";
            $rules .= "RewriteCond %{REQUEST_URI} !^/admin/\n";
            $rules .= "RewriteCond %{REQUEST_URI} !^/api/\n";
            $rules .= "RewriteCond %{REQUEST_URI} !^/assets/\n";
            $rules .= "RewriteCond %{REQUEST_URI} !^/install/\n";
            $rules .= "RewriteRule ^(.*)$ /index.php [QSA,L]\n";
        }

        $rules .= "\n# 安全头\n";
        $rules .= "<IfModule mod_headers.c>\n";
        $rules .= "  Header set X-Content-Type-Options \"nosniff\"\n";
        $rules .= "  Header set X-Frame-Options \"SAMEORIGIN\"\n";
        $rules .= "  Header set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        $rules .= "</IfModule>\n\n";
        $rules .= "# 禁止目录列表\n";
        $rules .= "Options -Indexes\n";

        return $rules;
    }

    /** 生成 Nginx 规则 */
    public static function generateNginx(): string
    {
        $mode = self::getConfig()['mode'] ?? 'dynamic';
        if ($mode === 'dynamic') {
            return "# 动态模式：前台 URL 带参数，无需伪静态重写\n";
        }

        $rules = "# 懒人导航 - Nginx 伪静态规则\n";
        $rules .= "# 拒绝访问敏感目录\n";
        $rules .= "location ~ ^/(core|config|install|\.git)/ {\n";
        $rules .= "  deny all;\n";
        $rules .= "}\n\n";
        $rules .= "# 拒绝直接访问模板 PHP 文件（安全）\n";
        $rules .= "location ~ ^/templates/.*\\.php$ {\n";
        $rules .= "  deny all;\n";
        $rules .= "}\n\n";
        $rules .= "location / {\n";

        if ($mode === 'index') {
            $rules .= "  # index.php 模式：URL 已包含 index.php，无需额外重写\n";
            $rules .= "  try_files \$uri \$uri/ =404;\n";
        } else {
            $rules .= "  # 伪静态模式：存在的文件直接访问，否则 → index.php\n";
            $rules .= "  try_files \$uri \$uri/ /index.php\$is_args\$args;\n";
        }

        $rules .= "}\n";

        return $rules;
    }

    /** 获取所有 URL 格式项（用于后台表单） */
    public static function getUrlItems(): array
    {
        return [
            'home'          => ['label' => '首页',            'placeholders' => ''],
            'category'      => ['label' => '分类页',          'placeholders' => '{%slug%} = 分类识别名'],
            'category_page' => ['label' => '分类分页',        'placeholders' => '{%slug%} = 分类识别名, {%page%} = 页码'],
            'site'          => ['label' => '站点详情页',      'placeholders' => '{%slug%} = 分类识别名, {%id%} = 站点ID'],
            'search'        => ['label' => '搜索页',          'placeholders' => ''],
            'submit'        => ['label' => '提交收录页',      'placeholders' => ''],
            'wormhole'      => ['label' => '虫洞联盟页',      'placeholders' => ''],
            'article_list'  => ['label' => '文章列表页',      'placeholders' => ''],
            'article'       => ['label' => '文章详情页',      'placeholders' => '{%id%} = 文章ID'],
        ];
    }

    /** 保存 .htaccess 到项目根目录 */
    public static function writeHtaccess(): void
    {
        $file = dirname(__DIR__) . '/.htaccess';
        file_put_contents($file, self::generateHtaccess(), LOCK_EX);
    }
}
