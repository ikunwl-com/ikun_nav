<?php
/**
 * 主题系统 - 扫描/加载/渲染主题
 * 主题目录：templates/主题名/
 * 每个主题包含：index.php, category.php, site.php, search.php, submit.php, theme.json
 */
class Theme
{
    /** 主题根目录 */
    private static string $baseDir = __DIR__ . '/../templates';

    /** 当前主题名 */
    private static ?string $currentTheme = null;

    /** 当前渲染的模板变量（供 partial 继承） */
    private static array $currentVars = [];

    /** 获取当前主题名 */
    public static function current(): string
    {
        if (self::$currentTheme === null) {
            $settings = new SettingsModel();
            $theme    = $settings->get('current_theme', 'default');
            if (!self::exists($theme)) {
                $theme = 'default';
            }
            self::$currentTheme = $theme;
        }
        return self::$currentTheme;
    }

    /** 设置当前主题 */
    public static function set(string $name): bool
    {
        if (!self::exists($name)) {
            return false;
        }
        $settings = new SettingsModel();
        $settings->set('current_theme', $name);
        self::$currentTheme = $name;
        return true;
    }

    /** 扫描所有可用主题 */
    public static function scan(): array
    {
        $themes = [];
        $dir    = self::$baseDir;
        if (!is_dir($dir)) {
            return $themes;
        }

        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $name = $item->getBasename();
                $info = self::getInfo($name);
                $themes[$name] = $info;
            }
        }
        ksort($themes);
        return $themes;
    }

    /** 获取主题信息 */
    public static function getInfo(string $name): array
    {
        $dir    = self::$baseDir . '/' . $name;
        $jsonFile = $dir . '/theme.json';
        $info     = [
            'name'        => $name,
            'title'       => $name,
            'version'     => '1.0',
            'author'      => '未知作者',
            'description' => '',
            'preview'     => '',
            'screenshot'  => '',
            'files'       => [],
        ];

        if (file_exists($jsonFile)) {
            $json = @json_decode(file_get_contents($jsonFile), true);
            if (is_array($json)) {
                $info = array_merge($info, $json);
            }
        }

        // 检查模板文件是否存在
        $tplFiles = ['index.php', 'category.php', 'site.php', 'search.php', 'submit.php'];
        foreach ($tplFiles as $f) {
            $path = $dir . '/' . $f;
            $info['files'][$f] = file_exists($path);
        }
        // 主题截图
        $screenshot = $dir . '/screenshot.png';
        if (file_exists($screenshot)) {
            $info['screenshot'] = 'templates/' . $name . '/screenshot.png';
        }

        return $info;
    }

    /** 判断主题是否存在 */
    public static function exists(string $name): bool
    {
        return is_dir(self::$baseDir . '/' . $name) &&
               file_exists(self::$baseDir . '/' . $name . '/index.php');
    }

    /** 加载模板文件 */
    public static function render(string $template, array $vars = []): void
    {
        $theme = self::current();
        $path  = self::$baseDir . '/' . $theme . '/' . $template . '.php';

        if (!file_exists($path)) {
            // 回退到 default 主题
            $defaultPath = self::$baseDir . '/default/' . $template . '.php';
            if (file_exists($defaultPath)) {
                $path = $defaultPath;
            } else {
                http_response_code(500);
                echo '模板文件不存在: ' . Security::e($template);
                exit;
            }
        }

        // 缓存变量供 partial() 继承
        self::$currentVars = $vars;

        // 将数据变量注入模板作用域
        extract($vars, EXTR_SKIP);
        require $path;
    }

    /** 模板中安全输出 */
    public static function e($value): string
    {
        return Security::e($value);
    }

    /** 模板中安全输出属性 */
    public static function eAttr($value): string
    {
        return Security::eAttr($value);
    }

    /** 模板中安全 URL */
    public static function url(string $type, array $params = []): string
    {
        return Rewrite::url($type, $params);
    }

    /** 获取主题文件路径 */
    public static function path(string $template): string
    {
        return self::$baseDir . '/' . self::current() . '/' . $template . '.php';
    }

    /** 获取主题资源目录（CSS/JS/图片） */
    public static function asset(string $file): string
    {
        return '/templates/' . self::current() . '/' . $file;
    }

    /** 加载模板片段（layout partial），如 header/footer */
    public static function partial(string $name, array $vars = []): void
    {
        $theme = self::current();
        $path  = self::$baseDir . '/' . $theme . '/' . $name . '.php';

        if (!file_exists($path)) {
            // 回退到 default 主题
            $defaultPath = self::$baseDir . '/default/' . $name . '.php';
            if (file_exists($defaultPath)) {
                $path = $defaultPath;
            } else {
                return; // 片段可选，不存在则静默跳过
            }
        }

        // 合并渲染时传入的变量 + partial() 显式传入的变量（显式优先）
        $allVars = array_merge(self::$currentVars, $vars);
        extract($allVars, EXTR_SKIP);
        include $path;
    }

    // ========== 主题配置（与插件配置同存储层：settings 表） ==========
    // 存储 key 前缀：theme_{主题名}_，例如当前主题为 default 时
    // Theme::config('logo') 对应 settings 表中的 theme_default_logo

    /**
     * 获取当前主题的配置值
     * @param string $key 配置键（不带前缀）
     * @param mixed $default 默认值
     */
    public static function config(string $key, $default = null)
    {
        $settings = new SettingsModel();
        return $settings->get('theme_' . self::current() . '_' . $key, $default);
    }

    /**
     * 批量保存当前主题的配置
     * @param array $data 原始键 => 值的关联数组（自动加主题前缀）
     */
    public static function setConfig(array $data): void
    {
        $settings = new SettingsModel();
        $prefixed = [];
        $prefix   = 'theme_' . self::current() . '_';
        foreach ($data as $key => $value) {
            $prefixed[$prefix . $key] = (string)$value;
        }
        $settings->setMany($prefixed);
    }

    /**
     * 判断主题是否自带后台设置页（存在 templates/{主题名}/admin.php）
     * 存在时后台「主题管理」卡片会显示「设置」入口
     */
    public static function hasSettingsPage(string $name): bool
    {
        return is_file(self::$baseDir . '/' . $name . '/admin.php');
    }
}
