<?php
/**
 * 应用中心插件 - 共享函数库
 *
 * 职责：
 *   1. 服务器目录拉取与本地缓存（list.php 协议）
 *   2. 安装包安全下载（同域/白名单 + 防内网 + 可选 SHA-256 校验）
 *   3. 压缩包逐条安全解压（路径穿越 / 绝对路径 / 符号链接逃逸防护）
 *   4. 本地安装 / 升级（先备份、失败回滚、不动数据库表）
 *   5. 版本比较与兼容性计算
 *
 * 由 admin.php（后台页面）与 api.php（AJAX 接口）引入。
 * 本文件不直接输出任何内容，禁止被当作入口直接访问。
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// ==================== 路径与目录 ====================

/** 站点根目录（plugins/appcenter → 站点根） */
function appcenter_site_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * 应用中心数据目录（data/appcenter），自动创建子目录
 * @param string|null $sub packages / backups / tmp 等子目录名
 */
function appcenter_data_dir(?string $sub = null): string
{
    $base = appcenter_site_root() . '/data/appcenter';
    foreach ([$base, $base . '/packages', $base . '/backups', $base . '/tmp'] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
    }
    // Apache 防护：禁止 Web 直接访问本目录
    // （目录内含升级备份的插件代码、解压临时文件、下载包，PHP 被直访执行后果严重）
    // Nginx 用户需在站点配置中对 /data/appcenter/ 加 deny，见插件 README 安全说明
    $htaccess = $base . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess,
            "# 禁止直接访问此目录下的所有文件（应用中心数据：备份/临时解压/下载包）\n"
            . "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule ^ - [F,L]\n</IfModule>\n"
            . "<IfModule mod_autoindex.c>\n    Options -Indexes\n</IfModule>\n",
            LOCK_EX);
    }
    if ($sub !== null) {
        $sub = trim($sub, '/');
        if ($sub !== '') {
            $full = $base . '/' . $sub;
            if (!is_dir($full)) {
                @mkdir($full, 0755, true);
            }
            $base = $full;
        }
    }
    return $base;
}

/** 写入应用中心日志（appcenter 频道，受后台日志开关控制） */
function appcenter_log(string $msg): void
{
    if (class_exists('Logger')) {
        try {
            Logger::log('appcenter', $msg);
        } catch (Throwable $e) {
            // 日志失败不影响主流程
        }
    }
}

// ==================== 配置读取 ====================

/** 预设服务器表（key => ['label','url']） */
function appcenter_preset_urls(): array
{
    return [
        'official' => ['label' => '官方服务器', 'url' => 'https://site.ikunwl.com/appcenter-server'],
        'third'    => ['label' => '第三方服务器', 'url' => 'https://www.92wl.com/appcenter-server'],
    ];
}

/** 当前勾选的预设 key（official / third） */
function appcenter_preset(): string
{
    $p = (string)Plugin::config('appcenter', 'preset', 'official');
    return in_array($p, ['official', 'third'], true) ? $p : 'official';
}

/** 自定义服务器地址（非空时优先于预设使用） */
function appcenter_custom_url(): string
{
    $custom = trim((string)Plugin::config('appcenter', 'custom_url', ''));
    if ($custom !== '') {
        return $custom;
    }
    // 兼容旧版「单一服务器地址」字段：曾保存的地址视为自定义地址
    return trim((string)Plugin::config('appcenter', 'server_url', ''));
}

/** 生效中的服务器基地址（自定义优先，否则用勾选的预设；其后会拼接 /list.php） */
function appcenter_server_url(): string
{
    $custom = appcenter_custom_url();
    if ($custom !== '') {
        return $custom;
    }
    $presets = appcenter_preset_urls();
    return $presets[appcenter_preset()]['url'] ?? '';
}

/** 生效服务器的来源标签（界面展示用：官方服务器 / 第三方服务器 / 自定义服务器） */
function appcenter_server_label(): string
{
    if (appcenter_custom_url() !== '') {
        return '自定义服务器';
    }
    $presets = appcenter_preset_urls();
    return $presets[appcenter_preset()]['label'] ?? '';
}

/** 服务器选择完整状态（页面 / 接口展示用） */
function appcenter_server_info(): array
{
    $presets = [];
    foreach (appcenter_preset_urls() as $key => $p) {
        $presets[] = ['key' => $key, 'label' => $p['label'], 'url' => $p['url']];
    }
    return [
        'presets'      => $presets,
        'preset'       => appcenter_preset(),
        'custom_url'   => appcenter_custom_url(),
        'server_url'   => appcenter_server_url(),
        'server_label' => appcenter_server_label(),
        'server_set'   => appcenter_server_url() !== '',
        'tls_loose'    => appcenter_tls_loose(),
    ];
}

/** 新插件安装后是否自动启用（升级保持原状态） */
function appcenter_auto_enable(): bool
{
    return Plugin::config('appcenter', 'auto_enable', '1') === '1';
}

/**
 * 宽松 TLS：证书校验失败仍继续（默认关闭；仅建议用于证书链不完整但可信的第三方服务器）
 * 开启后存在中间人风险，请谨慎。
 */
function appcenter_tls_loose(): bool
{
    return Plugin::config('appcenter', 'tls_loose', '0') === '1';
}

// ==================== 安装来源标记（官方 / 第三方 / 自定义） ====================

/** 当前生效来源的短标签：官方 / 第三方 / 自定义 */
function appcenter_source_tag(): string
{
    $label = appcenter_server_label(); // 官方服务器 / 第三方服务器 / 自定义服务器
    $tag = str_replace('服务器', '', $label);
    return in_array($tag, ['官方', '第三方', '自定义'], true) ? $tag : '';
}

/** 来源记录在 settings 表中的 key（按 类型+id 区分，如 appcenter_origin_plugin_spider） */
function appcenter_origin_key(string $type, string $id): string
{
    return 'appcenter_origin_' . ($type === 'theme' ? 'theme_' : 'plugin_') . $id;
}

/** 读取某扩展的安装来源标签（''=从未通过应用中心安装） */
function appcenter_origin(string $type, string $id): string
{
    return trim((string)(new SettingsModel())->get(appcenter_origin_key($type, $id), ''));
}

/** 记录扩展安装来源 */
function appcenter_origin_set(string $type, string $id, string $label): void
{
    if ($label === '') {
        return;
    }
    (new SettingsModel())->set(appcenter_origin_key($type, $id), $label);
}

/**
 * 展示用来源标签：
 *   已记录来源 → 官方 / 第三方 / 自定义；
 *   未记录但为内置扩展（builtin=true）→ 官方（随程序自带）；
 *   主题 default → 官方（系统默认主题）；
 *   其余 → ''
 */
function appcenter_display_label(string $type, string $id, bool $builtin = false): string
{
    $origin = appcenter_origin($type, $id);
    if ($origin !== '') {
        return $origin;
    }
    if ($builtin) {
        return '官方';
    }
    if ($type === 'theme' && $id === 'default') {
        return '官方';
    }
    return '';
}

/**
 * 自动补记来源（兼容旧版本安装的无来源记录扩展）：
 * 仅当「目录缓存来源 == 当前生效服务器」且该服务器目录里有已安装的扩展时才补记，
 * 已有来源记录的扩展不会被覆盖。应用中心自身永远视为官方，不参与补记。
 */
function appcenter_backfill_origins(): void
{
    $cat = appcenter_read_catalog();
    if (($cat['source'] ?? '') !== appcenter_server_url()) {
        return; // 目录与当前生效服务器不一致时不补记（防止标错来源）
    }
    $tag = appcenter_source_tag();
    if ($tag === '') {
        return;
    }
    $changed = 0;
    foreach ($cat['items'] as $item) {
        $id   = (string)($item['id'] ?? '');
        $type = (string)($item['type'] ?? '');
        if ($id === '' || !in_array($type, ['plugin', 'theme'], true)) {
            continue;
        }
        if ($type === 'plugin' && $id === 'appcenter') {
            continue; // 应用中心自身为官方自带，不参与来源补记
        }
        if (appcenter_origin($type, $id) !== '') {
            continue; // 已有记录不覆盖
        }
        $inst = appcenter_installed($type, $id);
        if (!$inst['installed']) {
            continue;
        }
        appcenter_origin_set($type, $id, $tag);
        $changed++;
    }
    if ($changed > 0) {
        appcenter_log('自动补记安装来源 服务器=' . appcenter_server_label() . ' 来源=' . $tag . ' 数量=' . $changed);
    }
}

/** 额外允许的下载域名白名单（逗号/空白分隔），默认仅允许服务器同域 */
function appcenter_download_hosts(): array
{
    $raw  = (string)Plugin::config('appcenter', 'download_hosts', '');
    $hosts = [];
    foreach (preg_split('/[\s,]+/', trim($raw)) ?: [] as $h) {
        $h = strtolower(trim($h));
        if ($h !== '' && !Security::isInternalHost($h)) {
            $hosts[] = $h;
        }
    }
    return array_unique($hosts);
}

/** 应用标识符白名单（与系统插件名规范一致） */
function appcenter_id_ok(string $id): bool
{
    return (bool)preg_match('/^[a-z0-9\-]+$/', $id);
}

/**
 * 校验服务器基地址
 * @return array [ok, value|reason]  ok=true 时 value 为规整后的基地址
 */
function appcenter_check_server(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return [false, '服务器地址不能为空'];
    }
    if (!preg_match('#^https?://#i', $url)) {
        return [false, '服务器地址必须以 http:// 或 https:// 开头'];
    }
    $parts = parse_url($url);
    $host  = strtolower((string)($parts['host'] ?? ''));
    if ($host === '') {
        return [false, '服务器地址格式不正确'];
    }
    if (Security::isInternalHost($host)) {
        return [false, '不允许使用内网/本机地址（防 SSRF）'];
    }
    return [true, rtrim($url, '/')];
}

/** 下载域名是否被允许（服务器同域 或 配置白名单，且非内网） */
function appcenter_host_allowed(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || Security::isInternalHost($host)) {
        return false;
    }
    [$ok, $base] = appcenter_check_server(appcenter_server_url());
    if (!$ok) {
        return false;
    }
    $serverHost = strtolower((string)parse_url($base, PHP_URL_HOST));
    if ($host === $serverHost) {
        return true;
    }
    return in_array($host, appcenter_download_hosts(), true);
}

// ==================== 版本工具 ====================

/** 版本号规范化：去空格、去 v 前缀、仅保留数字字母与点（version_compare 兼容集），最长 32 字符 */
function appcenter_normalize_version(string $v): string
{
    $v = strtolower(trim($v));
    if ($v === '') {
        return '';
    }
    $v = (string)preg_replace('/^v/i', '', $v);
    $v = (string)preg_replace('/[^0-9a-z.]/', '', $v);
    return (string)mb_substr($v, 0, 32);
}

/** 版本比较：a<b 返回 -1，a=b 返回 0，a>b 返回 1 */
function appcenter_compare_versions(string $a, string $b): int
{
    $a = appcenter_normalize_version($a);
    $b = appcenter_normalize_version($b);
    if ($a === '') { $a = '0'; }
    if ($b === '') { $b = '0'; }
    return version_compare($a, $b);
}

// ==================== 目录条目规范化 ====================

/**
 * 清洗 ZIP 条目名（防路径穿越 / 绝对路径 / 特殊字符）
 * @return string|null|false
 *   string   规范化的相对路径（可继续处理）
 *   null     非法条目（应整体终止并报错）
 *   false    顶层杂物条目（__MACOSX / .DS_Store 等，可安全忽略）
 */
function appcenter_clean_zip_name(string $name)
{
    if (strpos($name, "\0") !== false) {
        return null;
    }
    // 统一为 / 分隔（兼容 Windows 打包的 ZIP）
    $name = str_replace('\\', '/', $name);
    // 去掉开头 ./ 与结尾多余 /
    while (strpos($name, './') === 0) {
        $name = substr($name, 2);
    }
    $name = trim($name, '/');
    if ($name === '') {
        return null;
    }
    // 绝对路径 / 盘符
    if ($name[0] === '/' || preg_match('#^[a-zA-Z]:/#', $name)) {
        return null;
    }
    $segs = explode('/', $name);
    $clean = [];
    foreach ($segs as $s) {
        if ($s === '..') {
            return null; // 路径穿越
        }
        if ($s === '' || $s === '.') {
            continue;
        }
        $clean[] = $s;
    }
    if (empty($clean)) {
        return null;
    }
    // 顶层打包噪音目录：安全忽略
    if (in_array($clean[0], ['__MACOSX', '.DS_Store', 'Thumbs.db', 'desktop.ini'], true)) {
        return false;
    }
    return implode('/', $clean);
}

// ==================== 目录协议：拉取与缓存 ====================

/** 目录缓存文件路径 */
function appcenter_cache_file(): string
{
    return appcenter_data_dir() . '/catalog.json';
}

/**
 * 从服务器拉取目录并写入缓存
 * @return array {success, message, ...}
 */
function appcenter_fetch_catalog(): array
{
    $server = appcenter_server_url();
    [$ok, $reason] = appcenter_check_server($server);
    if (!$ok) {
        return ['success' => false, 'message' => $reason];
    }

    $url  = $server . '/list.php?_=' . time();
    $resp = appcenter_http_get($url, 20);
    if (!$resp['ok']) {
        $detail = $resp['err'] !== '' ? $resp['err'] : '未知错误';
        appcenter_log('拉取目录失败 url=' . $url . ' -> ' . $detail);
        // SSL 校验失败时给出可操作的提示（宽松模式默认关闭，需站长显式开启）
        $hint = '';
        if (stripos($detail, 'SSL') !== false && !appcenter_tls_loose()) {
            $hint = ' 提示：若确认该服务器可信但证书链不完整，可在「服务器设置 → 高级选项」开启“宽松 TLS 校验”后重试。';
        }
        return ['success' => false, 'message' => '连接应用中心服务器失败：' . $detail
            . '。请确认该服务器已部署 appcenter-server（能访问到 list.php、apps/ 目录存在）' . $hint];
    }
    $data = json_decode($resp['body'], true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => '服务器返回的数据无法解析（不是有效的 JSON）'];
    }
    // 服务端明确返回错误时，透传其 message，便于定位问题
    if (isset($data['success']) && $data['success'] === false && !empty($data['message'])) {
        return ['success' => false, 'message' => '服务器返回错误：' . $data['message']];
    }
    if (!isset($data['items']) || !is_array($data['items'])) {
        return ['success' => false, 'message' => '服务器返回的数据格式不正确（缺少 items 数组）'];
    }

    $items  = [];
    $dropped = 0;
    foreach ($data['items'] as $raw) {
        $item = appcenter_normalize_item($raw);
        if ($item !== null) {
            $items[] = $item;
        } else {
            $dropped++;
        }
    }
    if (empty($items)) {
        $emptyMsg = !empty($data['message']) ? $data['message']
                   : '服务器目录为空，或没有符合协议规范的条目';
        return [
            'success' => true,
            'message' => '目录为空：' . $emptyMsg,
            'count'   => 0,
            'dropped' => $dropped,
        ];
    }

    $payload = [
        'fetched_at' => time(),
        'source'     => $server,
        'items'      => $items,
    ];
    $cacheFile = appcenter_cache_file();
    if (@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return ['success' => false, 'message' => '无法写入目录缓存，请检查 data/appcenter 目录写权限'];
    }

    // 目录来源与当前服务器一致时，顺带为旧版本安装的扩展补记来源
    appcenter_backfill_origins();

    appcenter_log('拉取应用目录 服务器=' . $server . ' 条目=' . count($items)
        . ($dropped > 0 ? ' 忽略无效条目=' . $dropped : ''));
    return [
        'success' => true,
        'message' => '目录已更新：' . count($items) . ' 个应用'
            . ($dropped > 0 ? '（已忽略 ' . $dropped . ' 个无效条目，通常为下载地址域名不在白名单或元数据不符）' : ''),
        'count'   => count($items),
        'dropped' => $dropped,
    ];
}

/**
 * 校验并规范化服务器返回的单条目录
 * @return array|null 非法返回 null
 */
function appcenter_normalize_item(array $raw): ?array
{
    $id      = strtolower(trim((string)($raw['id'] ?? '')));
    $type    = strtolower(trim((string)($raw['type'] ?? '')));
    $version = appcenter_normalize_version((string)($raw['version'] ?? ''));
    $url     = trim((string)($raw['download_url'] ?? ''));

    if ($id === '' || !appcenter_id_ok($id)) {
        return null;
    }
    if (!in_array($type, ['plugin', 'theme'], true)) {
        return null;
    }
    if ($version === '' || $version === '0') {
        return null;
    }
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!appcenter_host_allowed($host)) {
        return null;
    }

    return [
        'id'          => $id,
        'type'        => $type,
        'version'     => $version,
        'title'       => (string)mb_substr(trim((string)($raw['title'] ?? $id)), 0, 120),
        'description' => (string)mb_substr(trim((string)($raw['description'] ?? '')), 0, 1000),
        'author'      => (string)mb_substr(trim((string)($raw['author'] ?? '')), 0, 100),
        'homepage'    => Security::safeUrl((string)($raw['homepage'] ?? '')),
        'min_version' => appcenter_normalize_version((string)($raw['min_version'] ?? '')),
        'max_version' => appcenter_normalize_version((string)($raw['max_version'] ?? '')),
        'changelog'   => (string)mb_substr(trim((string)($raw['changelog'] ?? '')), 0, 2000),
        'download_url'=> $url,
        'size'        => (int)($raw['size'] ?? 0),
        'sha256'      => preg_match('/^[a-f0-9]{64}$/i', (string)($raw['sha256'] ?? ''))
                            ? strtolower((string)$raw['sha256']) : '',
    ];
}

/** 读取目录缓存 @return array {items, fetched_at, source} */
function appcenter_read_catalog(): array
{
    $file = appcenter_cache_file();
    if (!is_file($file)) {
        return ['items' => [], 'fetched_at' => 0, 'source' => ''];
    }
    $data = @json_decode((string)@file_get_contents($file), true);
    if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
        return ['items' => [], 'fetched_at' => 0, 'source' => ''];
    }
    return [
        'items'      => $data['items'],
        'fetched_at' => (int)($data['fetched_at'] ?? 0),
        'source'     => (string)($data['source'] ?? ''),
    ];
}

// ==================== 本地状态与兼容性 ====================

/** 扩展所在根目录（plugins 或 templates） */
function appcenter_type_dir(string $type): string
{
    return appcenter_site_root() . '/' . ($type === 'theme' ? 'templates' : 'plugins');
}

/** 扩展元数据文件绝对路径（plugin.json / theme.json） */
function appcenter_meta_file(string $type, string $id): string
{
    return appcenter_type_dir($type) . '/' . $id . '/'
         . ($type === 'theme' ? 'theme.json' : 'plugin.json');
}

/** 读取本地已安装扩展的元数据 @return array|null */
function appcenter_read_meta(string $type, string $id): ?array
{
    $file = appcenter_meta_file($type, $id);
    if (!is_file($file)) {
        return null;
    }
    $json = @json_decode((string)@file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

/** 本地安装状态 @return array {installed, version, title} */
function appcenter_installed(string $type, string $id): array
{
    $meta = appcenter_read_meta($type, $id);
    if ($meta === null || !is_dir(appcenter_type_dir($type) . '/' . $id)) {
        return ['installed' => false, 'version' => '', 'title' => ''];
    }
    return [
        'installed' => true,
        'version'   => appcenter_normalize_version((string)($meta['version'] ?? '')),
        'title'     => trim((string)($meta['title'] ?? $id)),
    ];
}

/** 兼容性检查 @return array [ok, reason] */
function appcenter_compat(array $item): array
{
    $cur = appcenter_normalize_version((string)APP_VERSION);
    $min = (string)($item['min_version'] ?? '');
    $max = (string)($item['max_version'] ?? '');
    if ($min !== '' && appcenter_compare_versions($cur, $min) < 0) {
        return [false, '需要懒人导航 v' . $min . ' 及以上（当前 v' . APP_VERSION . '）'];
    }
    if ($max !== '' && appcenter_compare_versions($cur, $max) > 0) {
        return [false, '仅支持懒人导航 v' . $max . ' 及以下（当前 v' . APP_VERSION . '）'];
    }
    return [true, ''];
}

/** 组装列表行（含本地版本、升级判断、操作类型），供页面/接口直接输出 */
function appcenter_row(array $item): array
{
    $local = appcenter_installed($item['type'], $item['id']);
    [$compatOk, $compatReason] = appcenter_compat($item);

    $action    = 'install';
    $state     = 'not_installed';
    $stateLabel = '未安装';
    if ($local['installed']) {
        if (appcenter_compare_versions($item['version'], $local['version']) > 0) {
            $action     = 'upgrade';
            $state      = 'upgrade';
            $stateLabel = '可升级';
        } else {
            $action     = 'none';
            $state      = 'latest';
            $stateLabel = $local['version'] !== '' ? '已是最新' : '已安装';
        }
    }

    // 应用中心自身不允许通过商店安装/覆盖
    $builtinBlock = false;
    if ($item['type'] === 'plugin' && $item['id'] === 'appcenter') {
        $builtinBlock = true;
        $action       = 'blocked';
        $compatOk     = false;
        $compatReason = '应用中心为系统官方自带功能，不能通过商店安装或覆盖';
        $stateLabel   = $local['installed'] ? '官方自带' : '不可安装';
    } elseif (!$builtinBlock && !$compatOk && $action !== 'none') {
        $action     = 'blocked';
        $stateLabel = '不兼容';
    }

    $installedTitle = '';
    if ($local['installed'] && $local['title'] !== '' && $local['title'] !== $item['title']) {
        $installedTitle = $local['title'];
    }

    // 来源标签（官方 / 第三方 / 自定义）：已安装的本地扩展才展示，appcenter 视为官方内置
    $originLabel = '';
    if ($local['installed']) {
        $builtinFlag = ($item['type'] === 'plugin' && $item['id'] === 'appcenter');
        $originLabel = appcenter_display_label($item['type'], $item['id'], $builtinFlag);
    }

    return [
        'id'             => $item['id'],
        'type'           => $item['type'],
        'title'          => $item['title'],
        'author'         => $item['author'],
        'description'    => $item['description'],
        'homepage'       => $item['homepage'],
        'version'        => $item['version'],
        'local_version'  => $local['version'],
        'local_title'    => $installedTitle,
        'installed'      => $local['installed'],
        'can_upgrade'    => $action === 'upgrade',
        'size'           => $item['size'],
        'changelog'      => $item['changelog'],
        'action'         => $action,
        'state'          => $state,
        'state_label'    => $stateLabel,
        'compat_ok'      => $compatOk,
        'compat_reason'  => $compatReason,
        'origin_label'   => $originLabel,
    ];
}

/** 组装整个列表响应 */
function appcenter_rows(): array
{
    // 打开/刷新目录时顺带补记来源（仅目录与当前服务器一致且存在缺失记录时才会写入）
    appcenter_backfill_origins();

    $cat = appcenter_read_catalog();
    $rows = [];
    foreach ($cat['items'] as $item) {
        $rows[] = appcenter_row($item);
    }
    return [
        'rows'       => $rows,
        'fetched_at' => (int)$cat['fetched_at'],
        'source'     => $cat['source'],
    ];
}

// ==================== HTTP 请求 ====================

/** 获取本站 URL（随请求头上报，供服务器统计站点来源） */
function appcenter_site_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown';
    return $protocol . '://' . $host;
}

/**
 * 安全 GET（curl 优先，file_get_contents 兜底；仅 http/https，TLS 校验证书）
 * @return array {ok, body, err, code}
 *   ok=true 时 body 为响应内容；ok=false 时 err 为可读原因、code 为 HTTP 状态码（0=传输层失败）
 */
function appcenter_http_get(string $url, int $timeout = 15): array
{
    if (!preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'body' => '', 'err' => '仅支持 http/https 地址', 'code' => 0];
    }
    $siteUrl = appcenter_site_url();
    $ua      = 'LanRenNav-AppCenter/' . (defined('APP_VERSION') ? APP_VERSION : '1.0.0');
    $errCurl = '';
    $loose   = appcenter_tls_loose(); // 默认严格校验证书；宽松模式仅用于可信第三方服务器

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$loose);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $loose ? 0 : 2);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Site-Url: ' . $siteUrl]);
        $result  = curl_exec($ch);
        $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errCurl = (string)curl_error($ch);
        curl_close($ch);
        if ($code === 200 && $result !== false) {
            if (strlen((string)$result) <= 6 * 1024 * 1024) {
                return ['ok' => true, 'body' => (string)$result, 'err' => '', 'code' => 200];
            }
            return ['ok' => false, 'body' => '', 'err' => '服务器响应超过 6MB 上限', 'code' => 200];
        }
    } else {
        $errCurl = 'curl 扩展不可用（已尝试备用下载方式）';
    }

    // 兜底：file_get_contents（ignore_errors=true 以便读取真实状态码做诊断）
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => $timeout,
            'user_agent'     => $ua,
            'follow_location'=> 1,
            'max_redirects'  => 3,
            'ignore_errors'  => true,
            'header'         => 'X-Site-Url: ' . $siteUrl . "\r\n",
        ],
        'ssl' => [
            'verify_peer'      => !$loose,
            'verify_peer_name' => !$loose,
        ],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $m)) {
                $status = (int)$m[1];
            }
        }
    }

    if ($result === false) {
        $msg = $status > 0
            ? 'HTTP ' . $status
            : ($errCurl !== '' ? $errCurl : '连接失败（DNS / 超时 / TLS 证书不被信任）');
        return ['ok' => false, 'body' => '', 'err' => $msg, 'code' => $status];
    }
    if ($status !== 200) {
        return ['ok' => false, 'body' => '', 'err' => 'HTTP ' . $status . '（页面返回了非成功状态）', 'code' => $status];
    }
    if (strlen((string)$result) > 6 * 1024 * 1024) {
        return ['ok' => false, 'body' => '', 'err' => '服务器响应超过 6MB 上限', 'code' => 200];
    }
    return ['ok' => true, 'body' => (string)$result, 'err' => '', 'code' => 200];
}

// ==================== 安装包下载 ====================

/**
 * 下载安装包到本地缓存
 * @return array [ok, path|message]
 */
function appcenter_download_package(array $item): array
{
    $url  = (string)($item['download_url'] ?? '');
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return [false, '下载地址非法'];
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!appcenter_host_allowed($host)) {
        $serverHost = strtolower((string)parse_url(appcenter_server_url(), PHP_URL_HOST));
        return [false, '下载地址域名（' . $host . '）与当前生效服务器（' . $serverHost
            . '）不同域且不在下载白名单中，已拒绝。若刚切换过服务器请先「刷新目录」；若对方安装包放在 CDN 域名，请到高级选项加入白名单'];
    }
    if (!function_exists('curl_init')) {
        return [false, '服务器缺少 PHP curl 扩展，无法下载安装包（宝塔：PHP设置 → 安装扩展 → curl）'];
    }

    $version  = appcenter_normalize_version((string)($item['version'] ?? ''));
    $version  = $version !== '' ? $version : 'unknown';
    $dir      = appcenter_data_dir('packages');
    $localFile = $dir . '/' . $item['type'] . '-' . $item['id'] . '-' . $version . '.zip';

    // 缓存文件已存在且通过校验则复用
    if (is_file($localFile) && filesize($localFile) > 0) {
        if ($item['sha256'] === '' || appcenter_verify_sha256($localFile, $item['sha256'])) {
            return [true, $localFile];
        }
        @unlink($localFile); // 校验失败视为损坏，重新下载
    }

    $fp = @fopen($localFile, 'wb');
    if (!$fp) {
        return [false, '无法写入缓存目录（data/appcenter/packages 权限不足）'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    $loose = appcenter_tls_loose();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$loose);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $loose ? 0 : 2);
    curl_setopt($ch, CURLOPT_MAXFILESIZE, 400 * 1024 * 1024); // 与解压上限匹配，防磁盘写满
    curl_setopt($ch, CURLOPT_USERAGENT, 'LanRenNav-AppCenter/' . (defined('APP_VERSION') ? APP_VERSION : '1.0.0'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Site-Url: ' . appcenter_site_url()]);
    $success      = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlErr      = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || $httpCode !== 200) {
        @unlink($localFile);
        $msg = 'HTTP ' . $httpCode . '，cURL: ' . $curlErr . '（下载地址：' . $url . '）';
        if ($httpCode === 404) {
            $msg .= '；404 通常表示该服务器缺少 download.php，或 apps/'
                 . ($item['type'] === 'theme' ? 'themes' : 'plugins') . '/' . $item['id']
                 . ' 目录不存在/名称不一致，请核对服务器部署';
        }
        appcenter_log('安装包下载失败 url=' . $url . ' -> HTTP ' . $httpCode . ' ' . $curlErr);
        return [false, $msg];
    }
    // 重定向（含跨域跳转）结束后，最终地址仍必须落在允许的域名内（防 SSRF / 下载劫持）
    $effHost = strtolower((string)parse_url($effectiveUrl, PHP_URL_HOST));
    if ($effHost === '' || !appcenter_host_allowed($effHost)) {
        @unlink($localFile);
        appcenter_log('安装包下载被重定向到不允许的地址 url=' . $url . ' -> ' . $effectiveUrl);
        return [false, '下载被重定向到不允许的地址（' . $effectiveUrl . '），已拒绝'];
    }
    if (filesize($localFile) <= 0) {
        @unlink($localFile);
        return [false, '下载的文件为空'];
    }
    if (filesize($localFile) > 400 * 1024 * 1024) {
        @unlink($localFile);
        return [false, '下载文件超过 400MB 上限，已拒绝'];
    }
    if ($item['sha256'] !== '' && !appcenter_verify_sha256($localFile, $item['sha256'])) {
        @unlink($localFile);
        return [false, '安装包 SHA-256 校验失败（文件可能被篡改或下载不完整）'];
    }
    return [true, $localFile];
}

/** SHA-256 校验 */
function appcenter_verify_sha256(string $file, string $sha): bool
{
    if (!is_file($file) || !preg_match('/^[a-f0-9]{64}$/i', $sha)) {
        return false;
    }
    return hash_file('sha256', $file) === strtolower($sha);
}

// ==================== 解压与暂存 ====================

/**
 * 安全解压安装包到临时目录并校验结构
 * 包结构要求：压缩包根目录必须包含 plugins/{id}/ 或 templates/{id}/（取决于 type）
 * @return array [ok, message] 或 [ok, {staged, tmp_root, meta, wrote, skipped}]
 */
function appcenter_stage_package(string $zipFile, string $type, string $id): array
{
    if (!class_exists('ZipArchive')) {
        return [false, '服务器缺少 PHP zip 扩展，无法解压安装包（宝塔：PHP设置 → 安装扩展 → zip）'];
    }

    $tmpRoot = appcenter_data_dir('tmp') . '/ac-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 8);
    if (!@mkdir($tmpRoot, 0755, true)) {
        return [false, '无法创建临时解压目录'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        appcenter_rmdir($tmpRoot);
        return [false, '安装包无法打开（可能不是有效的 ZIP 文件）'];
    }

    $numFiles = $zip->numFiles;
    if ($numFiles > 5000) {
        $zip->close();
        appcenter_rmdir($tmpRoot);
        return [false, '安装包内文件数量异常（超过 5000），已拒绝'];
    }

    $prefix = ($type === 'theme' ? 'templates' : 'plugins') . '/' . $id . '/';
    $wrote   = 0;
    $skipped = 0;
    $totalBytes = 0;
    $maxBytes   = 300 * 1024 * 1024; // 解压总大小上限 300MB

    for ($i = 0; $i < $numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = (string)($stat['name'] ?? '');

        $clean = appcenter_clean_zip_name($name);
        if ($clean === null) {
            $zip->close();
            appcenter_rmdir($tmpRoot);
            return [false, '安装包包含非法路径条目（路径穿越/绝对路径），已拒绝：' . $name];
        }
        if ($clean === false) {
            $skipped++;
            continue; // 顶层打包噪音，忽略
        }
        // 只接受目标扩展目录内的条目，目录外条目一律忽略
        if (strpos($clean, $prefix) !== 0) {
            $skipped++;
            continue;
        }

        $target = $tmpRoot . '/' . $clean;
        if (substr($name, -1) === '/') {
            // 目录条目（子目录/父目录由文件条目自动创建，此处仅为完整性创建）
            if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                $zip->close();
                appcenter_rmdir($tmpRoot);
                return [false, '无法创建目录：' . $clean];
            }
            continue;
        }

        // 预检单条体积：超大条目直接拒绝，避免一次性读入内存
        $entrySize = (int)($stat['size'] ?? 0);
        if ($entrySize > 0 && $totalBytes + $entrySize > $maxBytes) {
            $zip->close();
            appcenter_rmdir($tmpRoot);
            return [false, '安装包解压后体积超过 300MB 上限，已中止'];
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) {
            // 无内容的条目（部分打包工具生成的目录条目不带结尾斜杠）按目录处理
            if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                $zip->close();
                appcenter_rmdir($tmpRoot);
                return [false, '无法创建目录：' . $clean];
            }
            continue;
        }
        $totalBytes += strlen($content);
        if ($totalBytes > $maxBytes) {
            $zip->close();
            appcenter_rmdir($tmpRoot);
            return [false, '安装包解压后体积超过 300MB 上限，已中止'];
        }

        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            $zip->close();
            appcenter_rmdir($tmpRoot);
            return [false, '无法创建目录：' . $clean];
        }
        if (@file_put_contents($target, $content, LOCK_EX) === false) {
            $zip->close();
            appcenter_rmdir($tmpRoot);
            return [false, '写入临时文件失败：' . $clean];
        }
        $wrote++;
    }
    $zip->close();

    if ($wrote === 0) {
        appcenter_rmdir($tmpRoot);
        return [false, '安装包结构不正确：压缩包根目录应包含 '
            . (($type === 'theme' ? 'templates' : 'plugins') . '/' . $id)
            . '/ 目录（当前没有解压出任何该目录下的文件）'];
    }

    // 校验元数据文件
    $metaFile = $tmpRoot . '/' . $prefix . ($type === 'theme' ? 'theme.json' : 'plugin.json');
    if (!is_file($metaFile)) {
        appcenter_rmdir($tmpRoot);
        return [false, '安装包缺少元数据文件：' . $prefix . ($type === 'theme' ? 'theme.json' : 'plugin.json')];
    }
    $meta = @json_decode((string)@file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        appcenter_rmdir($tmpRoot);
        return [false, '安装包元数据文件解析失败'];
    }
    $metaName = strtolower(trim((string)($meta['name'] ?? $id)));
    if ($metaName !== $id) {
        appcenter_rmdir($tmpRoot);
        return [false, '安装包元数据 name（' . $metaName . '）与目录条目 id（' . $id . '）不一致，已拒绝'];
    }

    // 主题必须包含 index.php，否则无法在后台启用为当前主题
    if ($type === 'theme' && !is_file($tmpRoot . '/' . $prefix . 'index.php')) {
        appcenter_rmdir($tmpRoot);
        return [false, '主题安装包缺少 templates/' . $id . '/index.php，无法作为主题启用'];
    }

    return [
        true,
        [
            'staged'   => $tmpRoot . '/' . $prefix,
            'tmp_root' => $tmpRoot,
            'meta'     => $meta,
            'wrote'    => $wrote,
            'skipped'  => $skipped,
        ],
    ];
}

// ==================== 备份 / 安装 / 回滚 ====================

/** 备份已安装目录 @return string 备份目录绝对路径；失败返回空字符串 */
function appcenter_backup_dir(string $type, string $id): string
{
    $src = appcenter_type_dir($type) . '/' . $id;
    if (!is_dir($src)) {
        return '';
    }
    $dst = appcenter_data_dir('backups') . '/' . $type . '-' . $id . '-' . date('Ymd-His');
    return appcenter_copy_tree($src, $dst) ? $dst : '';
}

/** 递归复制目录 @return bool */
function appcenter_copy_tree(string $src, string $dst): bool
{
    if (!is_dir($src)) {
        return false;
    }
    if (!@mkdir($dst, 0755, true)) {
        return false;
    }
    $items = @scandir($src);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            if (!appcenter_copy_tree($s, $d)) {
                return false;
            }
        } else {
            if (!@copy($s, $d)) {
                return false;
            }
        }
    }
    return true;
}

/** 递归删除目录 */
function appcenter_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        if (is_file($dir)) {
            @unlink($dir);
        }
        return;
    }
    $items = @scandir($dir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? appcenter_rmdir($path) : @unlink($path);
        }
    }
    @rmdir($dir);
}

/** 清理旧备份（保留最近 N 个） */
function appcenter_prune_backups(int $keep = 3): void
{
    $dir = appcenter_data_dir('backups');
    $all = glob($dir . '/{plugin,theme}-*', GLOB_BRACE) ?: [];
    if (count($all) <= $keep) {
        return;
    }
    // 按修改时间从新到旧排序
    usort($all, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach (array_slice($all, $keep) as $old) {
        appcenter_rmdir($old);
        appcenter_log('清理旧备份: ' . basename($old));
    }
}

/**
 * 安装 / 升级一个应用（完整流程：下载 → 校验 → 解压 → 备份 → 应用 → 回滚兜底 → 启用）
 * @return array {success, message, ...}
 */
function appcenter_install(string $itemId): array
{
    if (!appcenter_id_ok($itemId)) {
        return ['success' => false, 'message' => '应用标识非法'];
    }

    // 目录缓存必须与当前生效服务器一致，防止切换服务器后误装旧服务器的包
    $catalog = appcenter_read_catalog();
    if (!empty($catalog['source']) && $catalog['source'] !== appcenter_server_url()) {
        return ['success' => false, 'message' => '当前目录来自其他服务器（' . $catalog['source']
            . '），与当前生效服务器不一致，已拒绝操作。请先点击「刷新目录」后再安装/升级'];
    }
    $item = null;
    foreach ($catalog['items'] as $it) {
        if (($it['id'] ?? '') === $itemId) {
            $item = $it;
            break;
        }
    }
    if ($item === null) {
        return ['success' => false, 'message' => '目录中不存在该应用，请先刷新目录'];
    }

    // 应用中心自身保护
    if ($item['type'] === 'plugin' && $item['id'] === 'appcenter') {
        return ['success' => false, 'message' => '应用中心为系统官方自带功能，不能通过商店安装或覆盖'];
    }

    // 兼容性检查
    [$compatOk, $compatReason] = appcenter_compat($item);
    if (!$compatOk) {
        return ['success' => false, 'message' => $compatReason];
    }

    $local = appcenter_installed($item['type'], $item['id']);
    $mode  = $local['installed'] ? 'upgrade' : 'install';

    // 1. 下载
    [$dlOk, $dlRes] = appcenter_download_package($item);
    if (!$dlOk) {
        return ['success' => false, 'message' => '下载失败：' . $dlRes];
    }
    $zipFile = $dlRes;

    // 2. 安全解压暂存
    $staged = appcenter_stage_package($zipFile, $item['type'], $item['id']);
    if (!$staged[0]) {
        @unlink($zipFile);
        return ['success' => false, 'message' => $staged[1]];
    }
    $stagedDir = $staged[1]['staged'];
    $tmpRoot   = $staged[1]['tmp_root'];
    $target    = appcenter_type_dir($item['type']) . '/' . $item['id'];

    // 3. 升级前备份
    $backupDir = '';
    if ($mode === 'upgrade' && is_dir($target)) {
        $backupDir = appcenter_backup_dir($item['type'], $item['id']);
        if ($backupDir === '') {
            appcenter_rmdir($tmpRoot);
            @unlink($zipFile);
            return ['success' => false, 'message' => '升级前备份失败，已中止（请检查 data/appcenter/backups 目录写权限）'];
        }
    }

    // 4. 应用新文件（先移除旧目录，再移动/复制暂存目录）
    if (is_dir($target)) {
        appcenter_rmdir($target);
    }
    $applied = false;
    if (@rename($stagedDir, $target)) {
        $applied = true;
    } elseif (appcenter_copy_tree($stagedDir, $target)) {
        appcenter_rmdir($stagedDir);
        $applied = true;
    }

    $metaOk = $applied && is_file(appcenter_meta_file($item['type'], $item['id']));
    if (!$applied || !$metaOk) {
        // 5. 失败回滚
        $rollbackMsg = '';
        if ($backupDir !== '' && is_dir($backupDir)) {
            if (is_dir($target)) {
                appcenter_rmdir($target);
            }
            if (@rename($backupDir, $target) || appcenter_copy_tree($backupDir, $target)) {
                appcenter_rmdir($backupDir);
                $rollbackMsg = '；已自动回滚到原版本';
            }
        }
        appcenter_rmdir($tmpRoot);
        @unlink($zipFile);
        appcenter_log('应用中心安装失败: type=' . $item['type'] . ' id=' . $item['id']
            . ' version=' . $item['version'] . ' mode=' . $mode);
        return ['success' => false, 'message' => '文件写入失败，请检查 '
            . ($item['type'] === 'theme' ? 'templates' : 'plugins') . '/ 目录写权限' . $rollbackMsg];
    }

    // 6. 清理临时目录与压缩包
    appcenter_rmdir($tmpRoot);
    @unlink($zipFile);

    // 7. 插件：新安装且开启自动启用时启用；升级保持原状态
    $enabledNow = false;
    $wasEnabled = false;
    if ($item['type'] === 'plugin') {
        $wasEnabled = Plugin::isEnabled($item['id']);
        if (!$wasEnabled && appcenter_auto_enable()) {
            try {
                Plugin::setEnabled($item['id'], true);
                $enabledNow = true;
            } catch (Throwable $e) {
                appcenter_log('自动启用插件失败: ' . $item['id'] . ' - ' . $e->getMessage());
            }
        }
        Plugin::clearCache();
    }

    // 8. 清理多余备份
    appcenter_prune_backups(3);

    // 记录安装来源（官方 / 第三方 / 自定义），供插件管理 / 主题管理 / 应用中心目录展示
    $originTag = appcenter_source_tag();
    appcenter_origin_set($item['type'], $item['id'], $originTag);

    $admin = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : '?';
    appcenter_log('应用中心' . ($mode === 'upgrade' ? '升级' : '安装')
        . ': type=' . $item['type'] . ' id=' . $item['id']
        . ' version=' . $item['version']
        . ($local['version'] !== '' ? ' 原版本=' . $local['version'] : '')
        . ' 来源=' . ($originTag !== '' ? $originTag : '未知')
        . ' admin=' . $admin . ($enabledNow ? ' 已自动启用' : ''));

    $extra = '';
    if ($item['type'] === 'plugin') {
        if ($enabledNow) {
            $extra = '（已自动启用）';
        } elseif ($mode === 'install' && !appcenter_auto_enable()) {
            $extra = '（安装完成，请到插件管理启动）';
        }
    }

    return [
        'success' => true,
        'message' => ($mode === 'upgrade' ? '升级成功' : '安装成功')
            . '：「' . $item['title'] . '」v' . $item['version'] . $extra,
        'mode'    => $mode,
        'type'    => $item['type'],
        'id'      => $item['id'],
        'version' => $item['version'],
    ];
}
