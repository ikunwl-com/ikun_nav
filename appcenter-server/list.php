<?php
/**
 * 应用中心 - 服务端目录接口 list.php（自动扫描版）
 *
 * 无需维护任何清单文件：本接口自动扫描 apps/ 目录下的扩展文件夹，
 * 读取每个扩展的 plugin.json / theme.json 生成目录；安装包由 download.php 按需打包。
 *
 * 目录结构（把扩展文件夹直接放进来即可）：
 *   apps/
 *   ├── plugins/
 *   │   └── {插件id}/            ← 内含 plugin.json、include.php 等
 *   └── themes/
 *       └── {主题id}/            ← 内含 theme.json、index.php 等
 *
 * 目录协议（与客户端一致）：
 *   返回 JSON：{ "success": true, "items": [...] }
 *   items[].download_url 指向同目录 download.php，size 为源文件体积近似值（展示用）。
 *
 * 安全建议：
 *   1. 本接口公开只读；如需授权分发，可在本文件校验客户端请求头 X-Site-Url，
 *      仅对授权域名返回条目；
 *   2. 生产环境请全程 HTTPS。
 */

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function acsrv_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$appsDir = __DIR__ . '/apps';
$items   = [];
$warnings = [];

/**
 * 递归统计目录内文件总大小（近似展示用）
 */
function acsrv_folder_size(string $dir): int
{
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

/**
 * 扫描一类扩展（plugins / themes）
 */
function acsrv_scan_type(string $type, string $baseUrl, string $appsDir, array &$items, array &$warnings): void
{
    $folderName = $type === 'theme' ? 'themes' : 'plugins';
    $metaName   = $type === 'theme' ? 'theme.json' : 'plugin.json';
    $dir        = $appsDir . '/' . $folderName;
    if (!is_dir($dir)) {
        return;
    }
    $names = scandir($dir);
    if ($names === false) {
        return;
    }
    sort($names);

    foreach ($names as $name) {
        if ($name === '.' || $name === '..' || $name[0] === '.') {
            continue; // 隐藏目录（如 .git）忽略
        }
        if (!preg_match('/^[a-z0-9\-]+$/', $name)) {
            $warnings[] = $folderName . '/' . $name . '：目录名不合法（仅允许 a-z0-9-），已忽略';
            continue;
        }
        $extDir = $dir . '/' . $name;
        if (!is_dir($extDir)) {
            continue;
        }
        $metaFile = $extDir . '/' . $metaName;
        if (!is_file($metaFile)) {
            $warnings[] = $folderName . '/' . $name . '：缺少 ' . $metaName . '，已忽略';
            continue;
        }
        $meta = @json_decode((string)@file_get_contents($metaFile), true);
        if (!is_array($meta)) {
            $warnings[] = $folderName . '/' . $name . '：' . $metaName . ' 解析失败，已忽略';
            continue;
        }
        $metaId = strtolower(trim((string)($meta['name'] ?? $name)));
        if ($metaId !== $name) {
            $warnings[] = $folderName . '/' . $name . '：元数据 name（' . $metaId . '）与目录名不一致，已忽略';
            continue;
        }

        $version = (string)($meta['version'] ?? '1.0');
        if (trim($version) === '') {
            $version = '1.0';
        }

        $items[] = [
            'type'         => $type,
            'id'           => $name,
            'version'      => $version,
            'title'        => (string)mb_substr(trim((string)($meta['title'] ?? $name)), 0, 120),
            'description'  => (string)mb_substr(trim((string)($meta['description'] ?? '')), 0, 1000),
            'author'       => (string)mb_substr(trim((string)($meta['author'] ?? '')), 0, 100),
            'homepage'     => (string)trim((string)($meta['homepage'] ?? '')),
            'min_version'  => (string)trim((string)($meta['min_version'] ?? '')),
            'max_version'  => (string)trim((string)($meta['max_version'] ?? '')),
            'changelog'    => (string)mb_substr(trim((string)($meta['changelog'] ?? '')), 0, 2000),
            'download_url' => $baseUrl . '/download.php?type=' . $type . '&id=' . rawurlencode($name),
            'size'         => acsrv_folder_size($extDir), // 源文件体积近似值（仅展示）
            'sha256'       => '', // 按需打包，不做静态校验
        ];
    }
}

acsrv_scan_type('plugin', $baseUrl, $appsDir, $items, $warnings);
acsrv_scan_type('theme', $baseUrl, $appsDir, $items, $warnings);

// 稳定排序：插件在前，主题在后；同类型按 id
usort($items, function ($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'plugin' ? -1 : 1;
    }
    return strcmp($a['id'], $b['id']);
});

acsrv_out([
    'success'  => true,
    'message'  => empty($items)
        ? 'apps 目录为空：请将插件文件夹放入 apps/plugins/，主题文件夹放入 apps/themes/'
        : '',
    'count'    => count($items),
    'warnings' => $warnings,
    'items'    => $items,
]);
