<?php
/**
 * 应用中心 - 服务端按需打包下载 download.php
 *
 * 由 list.php 生成的 download_url 调用：根据 type + id 找到 apps/ 下的扩展文件夹，
 * 实时打包成 ZIP（结构为 plugins/{id}/... 或 templates/{id}/...，与客户端解压协议一致）后下发。
 *
 * 安全：
 *   - type 仅允许 plugin/theme，id 仅允许 a-z0-9-；
 *   - realpath 校验目标目录必须位于 apps/ 目录内（防路径穿越）；
 *   - 打包时跳过 .DS_Store / Thumbs.db / desktop.ini 等噪音文件；
 *   - 文件名不信任来源，版本号取自元数据并做白名单清洗。
 */

error_reporting(0);

function acsrv_dl_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

// 参数白名单校验
$type = strtolower((string)($_GET['type'] ?? ''));
$id   = strtolower(trim((string)($_GET['id'] ?? '')));
if (!in_array($type, ['plugin', 'theme'], true)) {
    acsrv_dl_fail(400, 'type 参数非法');
}
if ($id === '' || !preg_match('/^[a-z0-9\-]+$/', $id)) {
    acsrv_dl_fail(400, 'id 参数非法');
}

$folderName = $type === 'theme' ? 'themes' : 'plugins';
$rootDir    = realpath(__DIR__ . '/apps/' . $folderName);
$extDir     = realpath(__DIR__ . '/apps/' . $folderName . '/' . $id);
if ($rootDir === false || $extDir === false || !is_dir($extDir)) {
    acsrv_dl_fail(404, '应用不存在');
}
// 防路径穿越：目标必须位于 apps/{plugins|themes} 之内
if (strpos($extDir, $rootDir . DIRECTORY_SEPARATOR) !== 0 && $extDir !== $rootDir) {
    acsrv_dl_fail(403, '路径非法');
}

// 读取元数据（版本、标题）
$metaName = $type === 'theme' ? 'theme.json' : 'plugin.json';
$metaFile = $extDir . '/' . $metaName;
if (!is_file($metaFile)) {
    acsrv_dl_fail(404, '应用缺少元数据文件');
}
$meta = @json_decode((string)@file_get_contents($metaFile), true);
$version = is_array($meta) ? trim((string)($meta['version'] ?? '1.0')) : '1.0';
if ($version === '' || !preg_match('/^[a-z0-9][a-z0-9.\-+]{0,31}$/i', $version)) {
    $version = '1.0';
}

if (!class_exists('ZipArchive')) {
    acsrv_dl_fail(500, '服务器缺少 PHP zip 扩展，无法打包下载');
}

// 打包到临时文件后下发（用完即删）
$tmpFile = tempnam(sys_get_temp_dir(), 'acdl');
if ($tmpFile === false) {
    acsrv_dl_fail(500, '无法创建临时文件');
}

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpFile);
    acsrv_dl_fail(500, '无法创建压缩包');
}

$prefix = ($type === 'theme' ? 'templates' : 'plugins') . '/' . $id . '/';
$skipNames = ['.DS_Store', 'Thumbs.db', 'desktop.ini'];
$fileCount = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($extDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $fileInfo) {
    $rel = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($extDir) + 1));
    if ($fileInfo->isDir()) {
        if (basename($fileInfo->getPathname()) === '__MACOSX') {
            continue;
        }
        $zip->addEmptyDir($prefix . $rel);
        continue;
    }
    if (in_array($fileInfo->getFilename(), $skipNames, true)) {
        continue;
    }
    $zip->addFile($fileInfo->getPathname(), $prefix . $rel);
    $fileCount++;
}
$zip->close();

if (!is_file($tmpFile) || filesize($tmpFile) <= 0) {
    @unlink($tmpFile);
    acsrv_dl_fail(500, '打包失败（压缩包为空）');
}

$size = (int)filesize($tmpFile);
$dlName = $id . '-' . $version . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $dlName . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
header('X-AppCenter-Files: ' . $fileCount);

// 输出文件（分段读取，避免一次性载入内存）
$fp = fopen($tmpFile, 'rb');
if ($fp === false) {
    @unlink($tmpFile);
    acsrv_dl_fail(500, '读取压缩包失败');
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
@unlink($tmpFile);
exit;
