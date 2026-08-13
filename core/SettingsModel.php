<?php
/**
 * 设置管理业务逻辑层
 */

class SettingsModel
{
    public static ?array $cache = null;

    /**
     * 加载全部设置（缓存）
     */
    public function loadAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $tbl = Database::table('settings');
        $rows = Database::query("SELECT setting_key, setting_value FROM {$tbl}");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // 默认值：SEO 字段留空，由 Route.php 根据 site_name + site_slogan 动态生成
        // 注意：插件专属配置（wormhole_*、autolink_*、sitemap_cache_ttl、url_format_* 等）
        // 已移至各插件的 schema.php，在插件启用时由 Plugin::ensureSchema 写入。
        // Rewrite.php 核心类内置了 URL 格式默认值，无需在此提供。
        $defaults = [
            'site_name'        => '懒人导航',
            'site_slogan'      => '精选优质站点，一个页面搞定日常上网需求',
            'site_logo'        => '',
            'site_footer'      => '',
            'home_featured_count' => '6',
            'home_category_count' => '11',
            'home_per_category'   => '12',
            'seo_title'        => '',
            'seo_keywords'     => '',
            'seo_description'  => '',
            'enable_submit'    => '1',
            'need_review'      => '1',
            'show_weight'      => '1',
            'api_key_5118'     => '',
            'admin_email'      => '',
            'site_url'         => '',
            'debug_mode'       => '0',
            'default_per_page' => '12',
            'session_timeout'    => '3600',
            'enable_captcha'      => '0',
            'current_theme'       => 'default',
        ];

        // 兼容旧数据：数据库中仍保存着旧的硬编码 SEO 默认值时，自动当作空
        $oldSeoDefaults = [
            'seo_title'        => '懒人导航 - 精选优质站点',
            'seo_keywords'     => '导航站,懒人导航,网站导航',
            'seo_description'  => '懒人导航，精选音乐、视频、小说、漫画、动漫、壁纸、游戏、工具、AI等分类的优质站点',
        ];
        foreach ($oldSeoDefaults as $k => $oldVal) {
            if (isset($settings[$k]) && $settings[$k] === $oldVal) {
                $settings[$k] = '';
            }
        }

        self::$cache = array_merge($defaults, $settings);
        return self::$cache;
    }

    /**
     * 获取单个设置
     */
    public function get(string $key, $default = null)
    {
        $all = $this->loadAll();
        return $all[$key] ?? $default;
    }

    /**
     * 设置单个值
     */
    public function set(string $key, string $value): void
    {
        // 确保缓存已完整加载，避免写入不完整缓存导致后续读取缺失
        if (self::$cache === null) {
            $this->loadAll();
        }

        $tbl = Database::table('settings');
        $sql = "INSERT INTO {$tbl} (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        Database::execute($sql, [$key, $value]);

        // 更新缓存
        self::$cache[$key] = $value;
    }

    /**
     * 删除单个设置项
     */
    public function delete(string $key): void
    {
        $tbl = Database::table('settings');
        Database::execute("DELETE FROM {$tbl} WHERE setting_key = ?", [$key]);

        // 同步缓存
        if (self::$cache !== null) {
            unset(self::$cache[$key]);
        }
    }

    /**
     * 清空缓存（用于脚本执行后强制刷新）
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * 批量设置（合并为单条 INSERT ... ON DUPLICATE KEY UPDATE，减少数据库往返）
     */
    public function setMany(array $settings): void
    {
        // 确保缓存已完整加载
        if (self::$cache === null) {
            $this->loadAll();
        }

        if (empty($settings)) {
            return;
        }

        $tbl = Database::table('settings');
        $placeholders = [];
        $values = [];
        foreach ($settings as $key => $value) {
            $placeholders[] = '(?, ?)';
            $values[] = $key;
            $values[] = (string)$value;
        }

        $sql = "INSERT INTO {$tbl} (setting_key, setting_value) VALUES " . implode(', ', $placeholders)
             . " ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        Database::execute($sql, $values);

        // 同步更新缓存
        foreach ($settings as $key => $value) {
            self::$cache[$key] = (string)$value;
        }
    }
}
