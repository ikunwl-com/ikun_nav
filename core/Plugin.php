<?php
/**
 * 插件系统核心类
 * 负责扫描 /plugins/ 目录、解析 plugin.json、管理钩子注册与执行
 *
 * 插件目录结构：
 *   plugins/
 *     {plugin_name}/
 *       plugin.json    元数据
 *       include.php    主文件（函数+前台钩子，仅启用时加载）
 *       main.php       设置面板（后台设置Tab，仅启用时加载，可选）
 *       schema.php     数据库声明（表、字段、默认配置，仅启用/卸载时加载，可选）
 *
 * 插件启用状态存储在 settings 表，key = plugin_{name}_enabled
 * 插件自定义配置存储在 settings 表，key = plugin_{name}_{config_key}
 *
 * schema.php 返回格式：
 *   return [
 *       'tables' => [           // 需要创建的独立表
 *           'table_name' => "CREATE TABLE IF NOT EXISTS `{prefix}table_name` (...) ENGINE=InnoDB ...",
 *       ],
 *       'columns' => [          // 需要添加到已有表的字段
 *           'existing_table' => [
 *               'column_name' => "COLUMN_TYPE DEFAULT ... COMMENT '...'",
 *           ],
 *       ],
 *       'config' => [           // 默认配置项（key => value）
 *           'setting_key' => 'value',
 *       ],
 *   ];
 */
class Plugin
{
    /** 插件根目录 */
    private static string $baseDir = __DIR__ . '/../plugins';

    /** 已扫描的插件列表（缓存） */
    private static ?array $plugins = null;

    /** 已注册的钩子回调 [hook_name => [[callback, priority], ...]] */
    private static array $hooks = [];

    /** 是否已初始化 */
    private static bool $initialized = false;

    // ========== 初始化 ==========

    /**
     * 初始化插件系统：扫描并加载所有插件
     * 规则：
     *   - include.php（函数+前台钩子）仅在插件启用时加载
     *   - main.php（设置面板）仅在插件启用时加载
     *   - 未启用插件完全不加载，不注册任何钩子
     * 应在应用引导阶段调用一次
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $plugins = self::scan();
        foreach ($plugins as $name => $info) {
            // 仅加载已启用插件，未启用完全不加载（不注册任何钩子）
            if (!self::isEnabled($name)) {
                continue;
            }

            // 1. 加载主文件 include.php（定义类/函数、注册前台钩子）
            $mainFile = self::$baseDir . '/' . $name . '/' . $info['main_file'];
            if (file_exists($mainFile)) {
                try {
                    include_once $mainFile;
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::log('plugin_error', "加载插件主文件失败: {$name} - " . $e->getMessage());
                    }
                }
            }

            // 2. 加载设置面板 main.php（注册后台设置 Tab 钩子）
            if (!empty($info['config_file'])) {
                $configFile = self::$baseDir . '/' . $name . '/' . $info['config_file'];
                if (file_exists($configFile)) {
                    try {
                        include_once $configFile;
                    } catch (Throwable $e) {
                        if (class_exists('Logger')) {
                            Logger::log('plugin_error', "加载插件设置文件失败: {$name} - " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    // ========== 扫描与解析 ==========

    /**
     * 扫描所有可用插件
     * @return array [plugin_name => info_array]
     */
    public static function scan(): array
    {
        if (self::$plugins !== null) {
            return self::$plugins;
        }

        $plugins = [];
        if (!is_dir(self::$baseDir)) {
            self::$plugins = $plugins;
            return $plugins;
        }

        foreach (new DirectoryIterator(self::$baseDir) as $item) {
            if (!$item->isDir() || $item->isDot()) {
                continue;
            }
            $name = $item->getBasename();
            // 跳过以点开头的目录
            if (self::strStartsWith($name, '.')) {
                continue;
            }
            $info = self::getInfo($name);
            if ($info !== null) {
                $plugins[$name] = $info;
            }
        }

        ksort($plugins);
        self::$plugins = $plugins;
        return $plugins;
    }

    /**
     * 获取单个插件的元数据
     * @return array|null 不存在返回 null
     */
    public static function getInfo(string $name): ?array
    {
        $dir = self::$baseDir . '/' . $name;
        $jsonFile = $dir . '/plugin.json';

        if (!file_exists($jsonFile)) {
            return null;
        }

        $json = @json_decode(file_get_contents($jsonFile), true);
        if (!is_array($json)) {
            return null;
        }

        // 默认值
        $info = array_merge([
            'name'        => $name,
            'title'       => $name,
            'version'     => '1.0',
            'author'      => '',
            'description' => '',
            'main_file'   => $name . '.php',
            'config_file' => '',
            'hooks'       => [],
            'tables'      => [],
            'builtin'     => true,
        ], $json);

        $info['enabled'] = self::isEnabled($name);
        $info['main_file'] = $info['main_file'] ?: ($name . '.php');
        $info['dir'] = $dir;

        return $info;
    }

    // ========== 启用 / 停用 ==========

    /**
     * 检查插件是否已启用
     */
    public static function isEnabled(string $name): bool
    {
        $settings = new SettingsModel();
        return $settings->get('plugin_' . $name . '_enabled', '0') === '1';
    }

    /**
     * 设置插件启用状态
     * 启用时会自动执行 ensureSchema（建表、加字段、写默认配置）
     */
    public static function setEnabled(string $name, bool $enabled): void
    {
        $settings = new SettingsModel();
        $settings->set('plugin_' . $name . '_enabled', $enabled ? '1' : '0');

        // 启用时：自动安装插件所需的表、字段和默认配置
        if ($enabled) {
            self::ensureSchema($name);
        }
    }

    /**
     * 加载插件的 schema.php 声明
     * @return array ['tables' => [], 'columns' => [], 'config' => []]
     */
    public static function loadSchema(string $name): array
    {
        $schemaFile = self::$baseDir . '/' . $name . '/schema.php';
        if (!file_exists($schemaFile)) {
            return ['tables' => [], 'columns' => [], 'config' => []];
        }

        $schema = @include $schemaFile;
        if (!is_array($schema)) {
            return ['tables' => [], 'columns' => [], 'config' => []];
        }

        return [
            'tables'  => $schema['tables']  ?? [],
            'columns' => $schema['columns'] ?? [],
            'config'  => $schema['config']  ?? [],
        ];
    }

    /**
     * 确保插件的数据库结构已就绪：
     * 1. 创建声明的独立表（CREATE TABLE IF NOT EXISTS）
     * 2. 向已有表添加声明的字段（ALTER TABLE ADD COLUMN，跳过已存在的）
     * 3. 写入默认配置项（仅当配置项不存在时写入）
     */
    public static function ensureSchema(string $name): void
    {
        $info = self::getInfo($name);
        if ($info === null) {
            return;
        }

        $schema = self::loadSchema($name);
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'nav_';

        // 1. 创建独立表
        foreach ($schema['tables'] as $tableName => $rawSql) {
            $fullTable = Database::table($tableName);
            try {
                $exists = Database::queryOne(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                    [$fullTable]
                );
                if ($exists) {
                    continue;
                }
                $sql = str_replace('{prefix}', $prefix, $rawSql);
                Database::execute($sql);
                if (class_exists('Logger')) {
                    Logger::log('plugin_info', "插件启用自动建表: {$name} table={$tableName}");
                }
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_error', "插件启用建表失败: {$name} table={$tableName} - " . $e->getMessage());
                }
            }
        }

        // 2. 向已有表添加字段
        foreach ($schema['columns'] as $targetTable => $columns) {
            $fullTable = Database::table($targetTable);
            foreach ($columns as $columnName => $columnDef) {
                try {
                    $exists = Database::queryOne(
                        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
                        [$fullTable, $columnName]
                    );
                    if ($exists) {
                        continue;
                    }
                    Database::execute("ALTER TABLE `{$fullTable}` ADD COLUMN `{$columnName}` {$columnDef}");
                    if (class_exists('Logger')) {
                        Logger::log('plugin_info', "插件启用自动加字段: {$name} table={$targetTable} column={$columnName}");
                    }
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::log('plugin_error', "插件启用加字段失败: {$name} table={$targetTable} column={$columnName} - " . $e->getMessage());
                    }
                }
            }
        }

        // 3. 写入默认配置（仅当配置项不存在时）
        $settings = new SettingsModel();
        foreach ($schema['config'] as $key => $value) {
            try {
                $existing = $settings->get($key, null);
                if ($existing === null) {
                    $settings->set($key, (string)$value);
                    if (class_exists('Logger')) {
                        Logger::log('plugin_info', "插件启用写入默认配置: {$name} key={$key}");
                    }
                }
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_error', "插件启用写配置失败: {$name} key={$key} - " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 兼容旧版接口：确保插件声明的自建表已存在
     * 新版改为调用 ensureSchema
     * @deprecated 使用 ensureSchema 代替
     */
    public static function ensureTables(string $name): void
    {
        self::ensureSchema($name);
    }

    /**
     * 获取所有声明了某个表的插件列表
     * 用于共享表的智能卸载：仅当所有使用该表的插件都卸载时才删表
     * @param string $tableName 表名（不含前缀）
     * @param string $excludePlugin 排除的插件名（正在卸载的插件）
     * @return array 仍声明该表且仍启用的插件名列表
     */
    private static function getPluginsDeclaringTable(string $tableName, string $excludePlugin): array
    {
        $result = [];
        foreach (self::scan() as $name => $info) {
            if ($name === $excludePlugin) {
                continue;
            }
            $schema = self::loadSchema($name);
            if (isset($schema['tables'][$tableName])) {
                // 只要插件存在就算声明了该表（无论是否启用）
                $result[] = $name;
            }
            // 兼容 plugin.json 中的 tables 声明
            $jsonTables = $info['tables'] ?? [];
            if (is_array($jsonTables) && in_array($tableName, $jsonTables)) {
                if (!in_array($name, $result)) {
                    $result[] = $name;
                }
            }
        }
        return $result;
    }

    /**
     * 卸载插件：停用 + 删除插件自建表 + 删除插件添加的字段 + 清除插件配置
     * 与 setEnabled(false) 的区别：停用只改状态保留数据，卸载彻底清除
     *
     * 共享表处理：如果某张表被多个插件声明（如 blacklist 被 wormhole 和 auto-link 共享），
     * 仅当所有声明该表的插件都已卸载时才删表。
     *
     * @param string $name 插件名
     * @return array ['success' => bool, 'dropped_tables' => string[], 'dropped_columns' => string[], 'cleared_keys' => int]
     */
    public static function uninstall(string $name): array
    {
        $result = ['success' => false, 'dropped_tables' => [], 'dropped_columns' => [], 'cleared_keys' => 0];
        $info = self::getInfo($name);
        if ($info === null) {
            return $result;
        }

        // 1. 停用插件
        self::setEnabled($name, false);

        $schema = self::loadSchema($name);

        // 2. 删除插件自建表（智能处理共享表）
        // 收集所有需要删除的表名（schema.php 声明的 + plugin.json 声明的）
        $tablesToDrop = array_keys($schema['tables']);
        $jsonTables = $info['tables'] ?? [];
        if (is_array($jsonTables)) {
            foreach ($jsonTables as $tbl) {
                if (!in_array($tbl, $tablesToDrop)) {
                    $tablesToDrop[] = $tbl;
                }
            }
        }

        foreach ($tablesToDrop as $tbl) {
            // 检查是否有其他插件也声明了这张表
            $otherPlugins = self::getPluginsDeclaringTable($tbl, $name);
            if (!empty($otherPlugins)) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_info', "卸载跳过共享表: {$name} table={$tbl} 仍被以下插件声明: " . implode(', ', $otherPlugins));
                }
                continue;
            }

            $fullTable = Database::table($tbl);
            try {
                Database::execute("DROP TABLE IF EXISTS `{$fullTable}`");
                $result['dropped_tables'][] = $tbl;
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_error', "卸载删表失败: {$name} table={$tbl} - " . $e->getMessage());
                }
            }
        }

        // 3. 删除插件添加到已有表的字段
        foreach ($schema['columns'] as $targetTable => $columns) {
            $fullTable = Database::table($targetTable);
            foreach ($columns as $columnName => $columnDef) {
                try {
                    $exists = Database::queryOne(
                        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
                        [$fullTable, $columnName]
                    );
                    if (!$exists) {
                        continue;
                    }
                    Database::execute("ALTER TABLE `{$fullTable}` DROP COLUMN `{$columnName}`");
                    $result['dropped_columns'][] = "{$targetTable}.{$columnName}";
                } catch (Throwable $e) {
                    if (class_exists('Logger')) {
                        Logger::log('plugin_error', "卸载删字段失败: {$name} table={$targetTable} column={$columnName} - " . $e->getMessage());
                    }
                }
            }
        }

        // 4. 清除该插件的所有配置项（plugin_{name}_* 通配删除 + schema.php 中声明的配置）
        try {
            $settings = new SettingsModel();
            $settingsTbl = Database::table('settings');

            // 收集 schema.php 中声明的配置 key
            $declaredKeys = array_keys($schema['config']);

            // LIKE 匹配 plugin_{name}_ 前缀的所有 key
            $pattern = 'plugin_' . $name . '_%';
            $rows = Database::query(
                "SELECT setting_key FROM {$settingsTbl} WHERE setting_key LIKE ?",
                [$pattern]
            );
            $allKeys = $declaredKeys;
            foreach ($rows as $row) {
                if (!in_array($row['setting_key'], $allKeys)) {
                    $allKeys[] = $row['setting_key'];
                }
            }

            $cleared = 0;
            foreach ($allKeys as $key) {
                $settings->delete($key);
                $cleared++;
            }
            $result['cleared_keys'] = $cleared;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::log('plugin_error', "卸载清配置失败: {$name} - " . $e->getMessage());
            }
        }

        self::clearCache();
        $result['success'] = true;

        if (class_exists('Logger')) {
            Logger::log('plugin_uninstall', "卸载插件 {$name}，删表 " . implode(',', $result['dropped_tables']) . "，删字段 " . implode(',', $result['dropped_columns']) . "，清配置 {$result['cleared_keys']} 项");
        }

        return $result;
    }

    /**
     * 获取所有已启用的插件
     * @return array [plugin_name => info_array]
     */
    public static function getEnabledPlugins(): array
    {
        $result = [];
        foreach (self::scan() as $name => $info) {
            if ($info['enabled']) {
                $result[$name] = $info;
            }
        }
        return $result;
    }

    // ========== 钩子系统 ==========

    /**
     * 注册钩子回调
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级（数字越小越先执行）
     */
    public static function registerHook(string $hook, callable $callback, int $priority = 10): void
    {
        self::$hooks[$hook][] = [$callback, $priority];
    }

    /**
     * 注册过滤器（filter 的别名，语义更清晰）
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::registerHook($hook, $callback, $priority);
    }

    /**
     * 执行动作钩子（输出模式）
     * 按优先级顺序执行所有注册的回调，直接输出内容
     * @param string $hook 钩子名称
     * @param array $args 传递给回调的参数
     */
    public static function hook(string $hook, array $args = []): void
    {
        if (!isset(self::$hooks[$hook])) {
            return;
        }

        // 按优先级排序
        $callbacks = self::$hooks[$hook];
        usort($callbacks, function ($a, $b) {
            return $a[1] <=> $b[1];
        });

        foreach ($callbacks as $item) {
            try {
                call_user_func_array($item[0], $args);
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_error', "钩子执行失败: {$hook} - " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 执行过滤钩子（返回模式）
     * 按优先级顺序执行所有注册的回调，将返回值传递给下一个回调
     * @param string $hook 钩子名称
     * @param mixed $value 初始值
     * @param array $args 额外参数
     * @return mixed 过滤后的值
     */
    public static function filter(string $hook, $value, array $args = [])
    {
        if (!isset(self::$hooks[$hook])) {
            return $value;
        }

        $callbacks = self::$hooks[$hook];
        usort($callbacks, function ($a, $b) {
            return $a[1] <=> $b[1];
        });

        foreach ($callbacks as $item) {
            try {
                $params = array_merge([$value], $args);
                $result = call_user_func_array($item[0], $params);
                if ($result !== null) {
                    $value = $result;
                }
            } catch (Throwable $e) {
                if (class_exists('Logger')) {
                    Logger::log('plugin_error', "过滤器执行失败: {$hook} - " . $e->getMessage());
                }
            }
        }

        return $value;
    }

    /**
     * 检查钩子是否有注册的回调
     */
    public static function hasHook(string $hook): bool
    {
        return !empty(self::$hooks[$hook]);
    }

    // ========== 配置管理 ==========

    /**
     * 获取插件配置值
     * @param string $plugin 插件名
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function config(string $plugin, string $key, $default = null)
    {
        $settings = new SettingsModel();
        return $settings->get('plugin_' . $plugin . '_' . $key, $default);
    }

    /**
     * 设置插件配置值
     * @param string $plugin 插件名
     * @param string $key 配置键
     * @param string $value 配置值
     */
    public static function setConfig(string $plugin, string $key, string $value): void
    {
        $settings = new SettingsModel();
        $settings->set('plugin_' . $plugin . '_' . $key, $value);
    }

    // ========== 辅助方法 ==========

    /**
     * 获取插件目录路径
     */
    public static function getDir(string $name): string
    {
        return self::$baseDir . '/' . $name;
    }

    /**
     * 获取插件资源 URL
     */
    public static function asset(string $plugin, string $file): string
    {
        return '/plugins/' . $plugin . '/' . $file;
    }

    /**
     * 清除扫描缓存（用于后台操作后刷新）
     */
    public static function clearCache(): void
    {
        self::$plugins = null;
    }

    /**
     * 兼容 PHP < 8.0 的 str_starts_with
     */
    private static function strStartsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
