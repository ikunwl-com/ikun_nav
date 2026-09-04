<?php
/**
 * 统一日志工具
 * 所有日志写入 data/logs/YYYYMMDD/{channel}.log
 *
 * ===== 日志开关配置（在后台 settings 表中设置）=====
 *
 * 后台入口：基础设置 → 基础信息 → 日志设置（全局总开关 + 频道独立开关）
 *
 * 全局总开关：
 *   log_global = 1/0（设为 0 则关闭所有日志；关闭时不改动各频道开关值）
 *
 * ---- 虫洞联盟频道 ----
 *   log_wormhole_join    = 1/0  虫洞上报/加入联盟
 *   log_wormhole_check   = 1/0  虫洞每日检测
 *   log_wormhole_model   = 1/0  虫洞模型操作
 *   log_wormhole_display = 1/0  联盟成员列表展示
 *
 * ---- 友链自动收录频道 ----
 *   log_autolink = 1/0  自动收录全流程
 *
 * ---- 安全风控频道 ----
 *   log_security_ratelimit = 1/0  频率限制拦截
 *   log_security_csrf      = 1/0  CSRF校验失败
 *   log_security_referer   = 1/0  Referer校验失败
 *
 * ---- 跳转与API频道 ----
 *   log_go_jump  = 1/0  go.php 跳转请求
 *   log_api_5118 = 1/0  5118权重API调用
 *   log_api_tdk  = 1/0  TDK抓取API
 *   log_open_api = 1/0  开放API（open/*）调用审计
 *
 * ---- 后台管理审计频道 ----
 *   log_admin_auth      = 1/0  后台登录/登出/改密
 *   log_admin_site      = 1/0  站点增删改审
 *   log_admin_category  = 1/0  分类增删改排序
 *   log_admin_feature   = 1/0  推荐位设置
 *   log_admin_blacklist = 1/0  黑名单管理
 *   log_admin_setting   = 1/0  系统设置修改
 *   log_admin_wormhole  = 1/0  虫洞管理操作
 *   log_admin_api_key   = 1/0  API Key 管理
 *
 * ---- 系统与数据库频道 ----
 *   log_database_error    = 1/0  SQL执行失败（含具体SQL+参数）
 *   log_plugin_error      = 1/0  插件运行错误
 *   log_plugin_info       = 1/0  插件安装/启用信息
 *   log_plugin_uninstall  = 1/0  插件卸载记录
 *   log_search_fallback   = 1/0  搜索回退（FULLTEXT不可用）
 *
 * 除以上内置频道外，任意自定义频道均可写入（如 Logger::log('my_channel', ...)），
 * 未配置开关的频道默认开启。
 *
 * ===== 用法示例 =====
 *   Logger::log('wormhole_join', '[加入] 域名已加入联盟：xxx');
 *   Logger::log('go_jump', "[跳转] id={$siteId}，目标={$url}");
 *   Logger::log('admin_site', "[编辑] 管理员={$adminId}，站点ID={$siteId}，结果=成功");
 *   Logger::log('database_error', "[SQL失败] {$e->getMessage()} | SQL={$sql}");
 *
 * ===== 日志文件位置 =====
 *   data/logs/YYYYMMDD/{channel}.log
 */
class Logger {
    private static string $baseDir = __DIR__ . '/../data/logs';

    /**
     * 写单条日志（自动按日期分目录）
     * @param string $channel 日志频道，如 wormhole_join / go_jump / admin_site
     * @param string $msg     日志内容（建议中文，便于阅读）
     */
    public static function log(string $channel, string $msg): void {
        if (!self::isEnabled($channel)) return;

        $dir = self::$baseDir . '/' . date('Ymd');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . $channel . '.log';
        $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 批量写多条日志（同频道，减少 IO 次数）
     * @param string $channel 日志频道
     * @param array  $messages 日志内容数组
     */
    public static function logs(string $channel, array $messages): void {
        if (!self::isEnabled($channel)) return;

        $dir = self::$baseDir . '/' . date('Ymd');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . $channel . '.log';
        $lines = '';
        foreach ($messages as $msg) {
            $lines .= '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
        }
        @file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);
    }

    /**
     * 判断某个频道的日志是否开启
     * 优先级：全局开关 > 频道独立开关
     */
    public static function isEnabled(string $channel): bool {
        // 全局总开关：log_global = 0 时关闭所有
        if (function_exists('setting') && setting('log_global', '1') === '0') {
            return false;
        }

        // 频道独立开关：log_{channel} = 1/0
        if (function_exists('setting')) {
            return setting('log_' . $channel, '1') === '1';
        }

        return true;
    }

    /**
     * 获取某天的日志文件完整路径
     * @param string $channel 日志频道
     * @param string|null $date 日期（YYYYMMDD），默认今天
     * @return string 文件路径
     */
    public static function getLogFile(string $channel, ?string $date = null): string {
        $date = $date ?? date('Ymd');
        return self::$baseDir . '/' . $date . '/' . $channel . '.log';
    }
}