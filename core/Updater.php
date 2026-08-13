<?php
/**
 * 在线更新核心类
 * 功能：版本检测、下载更新包、备份、安装、回滚
 */
class Updater
{
    /** 当前版本 */
    public static function currentVersion(): string
    {
        return defined('APP_VERSION') ? APP_VERSION : '1.0.0';
    }

    /** 更新服务器基地址（可在 config.php 中覆盖 UPDATE_SERVER） */
    public static function serverUrl(): string
    {
        return defined('UPDATE_SERVER') ? UPDATE_SERVER : 'https://update.example.com/lanrennav';
    }

    /** 本地更新缓存目录 */
    public static function cacheDir(): string
    {
        $dir = __DIR__ . '/../data/updates';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            throw new RuntimeException('无法创建更新缓存目录 ' . $dir . '，请检查 data/ 目录的写入权限（建议通过面板/FTP 将拥有者改为 www 或设为 777）');
        }
        return $dir;
    }

    /** 本地备份目录 */
    public static function backupDir(): string
    {
        $dir = __DIR__ . '/../data/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            throw new RuntimeException('无法创建备份目录 ' . $dir . '，请检查 data/ 目录的写入权限（建议通过面板/FTP 将拥有者改为 www 或设为 777）');
        }
        return $dir;
    }

    /**
     * 检测最新版本
     * @return array {version, download_url, changelog, required_php, size, force}
     */
    public static function check(): array
    {
        $server = rtrim(self::serverUrl(), '/');

        // 智能判断：如果 UPDATE_SERVER 指向具体文件（如 .json/.php），直接使用；
        // 否则拼接 /check.php?version=xxx
        $hasExt = preg_match('/\.[a-zA-Z0-9]+$/', parse_url($server, PHP_URL_PATH) ?? '');
        if ($hasExt) {
            $url = $server;
        } else {
            $url = $server . '/check.php?version=' . urlencode(self::currentVersion());
        }

        $response = self::httpGet($url, 10);
        if ($response === false) {
            return ['error' => '连接更新服务器失败，请检查网络'];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['error' => '更新服务器返回数据无效'];
        }

        // 合并默认字段
        $data += [
            'version'       => self::currentVersion(),
            'has_update'    => false,
            'download_url'  => '',
            'changelog'     => '',
            'required_php'  => '7.4',
            'size'          => 0,
            'force'         => false,
        ];

        return $data;
    }

    /**
     * 下载更新包到本地缓存
     * @param string $downloadUrl 下载地址
     * @param string $version 版本号
     * @return string|false 本地文件路径或 false
     */
    public static function download(string $downloadUrl, string $version)
    {
        $ext = pathinfo(parse_url($downloadUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'zip';

        // 先确保缓存目录存在且可写
        try {
            $cacheDir = self::cacheDir();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }

        $localFile = $cacheDir . '/update-' . $version . '.' . $ext;

        $fp = @fopen($localFile, 'wb');
        if (!$fp) {
            $err = error_get_last();
            $msg = $err ? $err['message'] : '无法创建文件，请检查 data/updates 目录写入权限';
            return ['error' => $msg];
        }

        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [__CLASS__, 'curlProgress']);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            // 写入诊断日志到 data/updates/.download_errors，方便排查
            $errLog = $cacheDir . '/.download_errors';
            $entry  = date('Y-m-d H:i:s') . " | URL={$downloadUrl} | HTTP={$httpCode} | cURL={$curlErr} | fopen=ok\n";
            @file_put_contents($errLog, $entry, FILE_APPEND);
            @unlink($localFile);
            return ['error' => "HTTP {$httpCode}, cURL: {$curlErr}"];
        }

        return $localFile;
    }

    /**
     * 检查系统是否支持 ZIP 扩展
     */
    public static function hasZipExtension(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * 创建完整备份（文件 + 数据库）
     * 无 ZipArchive 时降级为目录复制
     * @return string|false 备份目录名或 false
     */
    public static function backup()
    {
        $timestamp = date('Ymd-His');
        $backupDir = self::backupDir() . '/backup-' . $timestamp;
        if (!@mkdir($backupDir, 0755, true)) {
            return false;
        }

        // 1. 备份核心文件（排除 data/ 和 install/）
        $source = __DIR__ . '/..';
        $exclude = ['data', 'install.lock', '.git', '.dumate', '.env', '.user.ini'];
        
        if (self::hasZipExtension()) {
            $fileBackup = $backupDir . '/files.zip';
            $zip = new ZipArchive();
            if ($zip->open($fileBackup, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }
            self::zipAddDir($zip, $source, '', $exclude);
            $zip->close();
            $filesEntry = 'files.zip';
        } else {
            // 降级：目录复制
            $fileBackup = $backupDir . '/files';
            if (!@mkdir($fileBackup, 0755, true)) {
                return false;
            }
            self::copyDir($source, $fileBackup, '', $exclude);
            $filesEntry = 'files';
        }

        // 2. 备份数据库
        $dbBackup = $backupDir . '/database.sql';
        self::backupDatabase($dbBackup);

        // 3. 写入备份元信息
        file_put_contents($backupDir . '/info.json', json_encode([
            'version'   => self::currentVersion(),
            'time'      => date('Y-m-d H:i:s'),
            'php'       => PHP_VERSION,
            'files'     => $filesEntry,
            'database'  => 'database.sql',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $backupDir;
    }

    /**
     * 执行更新安装
     * @param string $packageFile 更新包本地路径
     * @return array {success, message, need_migrate}
     */
    public static function install(string $packageFile): array
    {
        if (!file_exists($packageFile)) {
            return ['success' => false, 'message' => '更新包文件不存在'];
        }

        $ext = strtolower(pathinfo($packageFile, PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip', 'zba'])) {
            return ['success' => false, 'message' => '不支持的更新包格式：' . $ext];
        }

        $tempDir = self::cacheDir() . '/extract-' . time();
        if (!@mkdir($tempDir, 0755, true)) {
            return ['success' => false, 'message' => '无法创建解压目录'];
        }

        // 解压
        if ($ext === 'zip') {
            if (self::hasZipExtension()) {
                $zip = new ZipArchive();
                if ($zip->open($packageFile) !== true) {
                    self::rmDir($tempDir);
                    return ['success' => false, 'message' => '更新包解压失败'];
                }
                $zip->extractTo($tempDir);
                $zip->close();
            } elseif (function_exists('exec') && self::systemUnzip($packageFile, $tempDir)) {
                // 降级：系统 unzip 命令
            } else {
                self::rmDir($tempDir);
                return ['success' => false, 'message' => '服务器缺少 ZIP 扩展，无法解压更新包。请联系管理员安装 PHP zip 扩展，或在宝塔面板 → 软件商店 → PHP设置 → 安装扩展 → 添加 zip'];
            }
        } else {
            // .zba 为 XML 格式（兼容 Z-Blog 生态）
            $result = self::extractZba($packageFile, $tempDir);
            if (!$result['success']) {
                self::rmDir($tempDir);
                return $result;
            }
        }

        // 读取更新包元信息
        $manifestFile = $tempDir . '/manifest.json';
        $manifest = [];
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
        }

        // 校验清单（校验文件哈希，防篡改）
        if (!empty($manifest['checksums'])) {
            foreach ($manifest['checksums'] as $relPath => $expectedHash) {
                $filePath = $tempDir . '/' . $relPath;
                if (!file_exists($filePath)) {
                    self::rmDir($tempDir);
                    return ['success' => false, 'message' => '更新包不完整：缺少 ' . $relPath];
                }
                if (hash_file('sha256', $filePath) !== $expectedHash) {
                    self::rmDir($tempDir);
                    return ['success' => false, 'message' => '更新包校验失败：' . $relPath];
                }
            }
        }

        // 执行文件替换（保留 data/ 和 config.php）
        $root = __DIR__ . '/..';
        $excludePaths = ['data', 'config.php', 'install.lock'];
        $copyResult = self::copyUpdateFiles($tempDir, $root, '', $excludePaths);
        if (!$copyResult['success']) {
            self::rmDir($tempDir);
            return $copyResult;
        }

        // 执行数据库迁移脚本（如果存在）
        $migrateResult = self::runMigrations($tempDir);

        // 清理临时文件
        self::rmDir($tempDir);

        // 尝试获取新版本号（多来源：manifest.json → core/bootstrap.php）
        $newVersion = $manifest['version'] ?? null;
        if (empty($newVersion)) {
            $newVersion = self::extractVersionFromBootstrap($root . '/core/bootstrap.php');
        }
        // 如果 manifest 有版本号，自动同步写入 core/bootstrap.php
        if (!empty($manifest['version'])) {
            self::updateBootstrapVersion($manifest['version']);
        }

        // 更新安装锁中的版本标记（如存在）
        $lockFile = $root . '/install.lock';
        if (file_exists($lockFile)) {
            touch($lockFile);
        }

        return [
            'success'     => true,
            'message'     => '更新安装成功' . ($migrateResult ? '，数据库迁移已执行' : ''),
            'need_migrate'=> $migrateResult,
            'new_version' => $newVersion ?? '未知',
        ];
    }

    /**
     * 从 core/bootstrap.php 提取 APP_VERSION
     */
    private static function extractVersionFromBootstrap(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        $content = file_get_contents($file);
        if (preg_match("/define\('APP_VERSION',\s*'([^']+)'\);/", $content, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * 替换 core/bootstrap.php 中的 APP_VERSION
     */
    public static function updateBootstrapVersion(string $version): bool
    {
        $file = __DIR__ . '/bootstrap.php';
        if (!file_exists($file)) {
            return false;
        }
        $content = file_get_contents($file);
        $newContent = preg_replace(
            "/define\('APP_VERSION',\s*'[^']*'\);/",
            "define('APP_VERSION', '" . addslashes($version) . "');",
            $content
        );
        if ($newContent !== $content) {
            return file_put_contents($file, $newContent) !== false;
        }
        return true;
    }

    /**
     * 从备份回滚
     * @param string $backupDir 备份目录绝对路径
     * @return array {success, message}
     */
    public static function rollback(string $backupDir): array
    {
        if (!is_dir($backupDir)) {
            return ['success' => false, 'message' => '备份目录不存在'];
        }

        $infoFile = $backupDir . '/info.json';
        if (!file_exists($infoFile)) {
            return ['success' => false, 'message' => '备份信息文件缺失，无法回滚'];
        }

        $info = json_decode(file_get_contents($infoFile), true);
        if (empty($info)) {
            return ['success' => false, 'message' => '备份信息损坏'];
        }

        $root = __DIR__ . '/..';

        // 预检查：关键目录是否有写入权限
        $writable = self::checkWritable($root, ['core', 'admin', 'assets', 'api', 'templates', 'index.php', 'go.php']);
        if (!empty($writable)) {
            $list = implode(', ', $writable);
            return [
                'success' => false,
                'message' => "回滚失败：以下文件/目录没有写入权限，请通过面板/FTP 将拥有者改为 www 或设为 777：{$list}"
            ];
        }

        // 1. 回滚文件
        $fileBackup = $backupDir . '/' . ($info['files'] ?? 'files.zip');
        if (file_exists($fileBackup)) {
            $exclude = ['data', 'config.php', 'install.lock', '.user.ini', '.htaccess'];
            
            if (is_dir($fileBackup)) {
                // 降级：目录格式备份
                $result = self::rollbackFromDir($fileBackup, $root, $exclude);
            } elseif (self::hasZipExtension()) {
                $result = self::rollbackFromZip($fileBackup, $root, $exclude);
            } else {
                return ['success' => false, 'message' => '服务器缺少 ZIP 扩展，无法回滚 ZIP 格式备份'];
            }
            
            if (!$result['success']) {
                return $result;
            }
        }

        // 2. 回滚数据库（如果存在 SQL 备份）
        $dbBackup = $backupDir . '/' . ($info['database'] ?? 'database.sql');
        if (file_exists($dbBackup)) {
            if (!self::restoreDatabase($dbBackup)) {
                return ['success' => false, 'message' => '文件回滚成功，但数据库回滚失败'];
            }
        }

        return ['success' => true, 'message' => '已成功回滚到版本 ' . ($info['version'] ?? '未知')];
    }

    /**
     * 检查关键路径是否可写
     * @return array 不可写的路径列表
     */
    public static function checkWritable(string $root, array $items): array
    {
        $failed = [];
        foreach ($items as $item) {
            $path = $root . '/' . $item;
            if (!file_exists($path)) {
                continue; // 不存在的视为无需检查
            }
            if (!is_writable($path)) {
                $failed[] = $item;
            }
        }
        return $failed;
    }

    /** 获取本地备份列表 */
    public static function listBackups(): array
    {
        $dir = self::backupDir();
        $backups = [];
        if (!is_dir($dir)) {
            return $backups;
        }

        foreach (glob($dir . '/backup-*', GLOB_ONLYDIR) as $path) {
            $infoFile = $path . '/info.json';
            $info = file_exists($infoFile) ? json_decode(file_get_contents($infoFile), true) : [];
            $backups[] = [
                'dir'       => basename($path),
                'path'      => $path,
                'version'   => $info['version'] ?? '未知',
                'time'      => $info['time'] ?? date('Y-m-d H:i:s', filemtime($path)),
                'size'      => self::dirSize($path),
            ];
        }

        // 按时间倒序
        usort($backups, function ($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        return $backups;
    }

    /** 清理旧备份（保留最近 N 个） */
    public static function cleanOldBackups(int $keep = 5): void
    {
        $backups = self::listBackups();
        foreach (array_slice($backups, $keep) as $b) {
            self::rmDir($b['path']);
        }
    }

    // ==================== 私有辅助方法 ====================

    private static function httpGet(string $url, int $timeout = 10)
    {
        $siteUrl = self::siteUrl();

        // 优先使用 curl，若不可用或失败则回退到 file_get_contents
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'LanRenNav-Updater/' . self::currentVersion());
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Site-Url: ' . $siteUrl]);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200 && $result !== false) {
                return $result;
            }
            // curl 失败时不直接返回 false，继续尝试 file_get_contents
        }

        // file_get_contents 回退
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'LanRenNav-Updater/' . self::currentVersion(),
                'follow_location' => 1,
                'header' => 'X-Site-Url: ' . $siteUrl . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        return ($result !== false) ? $result : false;
    }

    /**
     * 获取当前网站的完整 URL（用于统计时上报给更新服务器）
     */
    private static function siteUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown';
        return $protocol . '://' . $host;
    }

    private static function curlProgress($resource, $downloadSize, $downloaded, $uploadSize, $uploaded): int
    {
        // 可被外部覆盖做进度回调，当前仅占位
        return 0;
    }

    /**
     * 使用系统 unzip 命令解压（降级方案）
     */
    private static function systemUnzip(string $zipFile, string $outputDir): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $cmd = 'unzip -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($outputDir) . ' 2>&1';
        exec($cmd, $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * 目录递归复制（无 ZipArchive 时的降级方案）
     */
    private static function copyDir(string $src, string $dst, string $relPath, array $exclude): void
    {
        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            foreach ($exclude as $ex) {
                if (strpos($item, $ex) === 0) {
                    continue 2;
                }
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            $relEntry = $relPath ? $relPath . '/' . $item : $item;
            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) {
                    @mkdir($dstPath, 0755, true);
                }
                self::copyDir($srcPath, $dstPath, $relEntry, $exclude);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    private static function zipAddDir(ZipArchive $zip, string $realPath, string $zipPath, array $exclude): void
    {
        $items = scandir($realPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            // 跳过排除项（data/ 目录、.user.ini、.git 等）
            foreach ($exclude as $ex) {
                if (strpos($item, $ex) === 0) {
                    continue 2;
                }
            }

            $fullPath = $realPath . '/' . $item;
            $zipEntry = $zipPath ? $zipPath . '/' . $item : $item;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipEntry);
                self::zipAddDir($zip, $fullPath, $zipEntry, $exclude);
            } else {
                $zip->addFile($fullPath, $zipEntry);
            }
        }
    }

    private static function backupDatabase(string $outputFile): bool
    {
        try {
            $pdo = Database::getInstance();
            $tables = Database::query("SHOW TABLES");
            $sql = "-- 懒人导航数据库备份\n-- 生成时间: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $row) {
                $table = array_values($row)[0];
                // 表结构
                $create = Database::queryOne("SHOW CREATE TABLE `{$table}`");
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $create['Create Table'] ?? $create[1] ?? '';
                $sql .= ";\n\n";

                // 表数据 — 分批查询避免内存溢出
                $count = (int)Database::queryOne("SELECT COUNT(*) AS c FROM `{$table}`")['c'];
                if ($count <= 0) {
                    continue;
                }
                $batchSize = 500;
                $columns = null;
                $firstBatch = true;
                for ($offset = 0; $offset < $count; $offset += $batchSize) {
                    $rows = Database::query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}");
                    if (empty($rows)) {
                        continue;
                    }
                    if ($columns === null) {
                        $columns = array_keys($rows[0]);
                    }
                    if ($firstBatch) {
                        $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        $firstBatch = false;
                    } else {
                        $sql .= ";\nINSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                    }
                    $values = [];
                    foreach ($rows as $r) {
                        $vals = [];
                        foreach ($r as $val) {
                            $vals[] = $val === null ? 'NULL' : $pdo->quote((string)$val);
                        }
                        $values[] = '(' . implode(', ', $vals) . ')';
                    }
                    $sql .= implode(",\n", $values) . "\n";
                }
                if (!$firstBatch) {
                    $sql .= ";\n\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($outputFile, $sql);
            return true;
        } catch (Throwable $e) {
            error_log('数据库备份失败: ' . $e->getMessage());
            return false;
        }
    }

    private static function restoreDatabase(string $sqlFile): bool
    {
        try {
            $pdo = Database::getInstance();
            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                return false;
            }
            // 分割多条语句执行
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (empty($stmt) || strpos($stmt, '--') === 0) {
                    continue;
                }
                $pdo->exec($stmt);
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            return true;
        } catch (Throwable $e) {
            error_log('数据库回滚失败: ' . $e->getMessage());
            return false;
        }
    }

    private static function extractZba(string $zbaFile, string $outputDir): array
    {
        // .zba 是 Z-Blog 应用中心格式：可能为 GZip 压缩的 XML
        $content = file_get_contents($zbaFile);
        if ($content === false) {
            return ['success' => false, 'message' => '无法读取 .zba 文件'];
        }

        // 检测 GZip 压缩
        $magic = substr($content, 0, 2);
        if ($magic === "\x1f\x8b") {
            $content = @gzdecode($content);
            if ($content === false) {
                return ['success' => false, 'message' => '.zba GZip 解压失败'];
            }
        }

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return ['success' => false, 'message' => '.zba XML 解析失败'];
        }

        // 提取 <file><path>...</path><stream>base64...</stream></file>
        foreach ($xml->file ?? [] as $fileNode) {
            $path = (string)($fileNode->path ?? '');
            $stream = (string)($fileNode->stream ?? '');
            if (empty($path) || empty($stream)) {
                continue;
            }
            $target = $outputDir . '/' . $path;
            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($target, base64_decode($stream));
        }

        return ['success' => true];
    }

    private static function copyUpdateFiles(string $src, string $dst, string $sub, array $exclude): array
    {
        $items = scandir($src . ($sub ? '/' . $sub : ''));
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'manifest.json') {
                continue;
            }

            $relPath = $sub ? $sub . '/' . $item : $item;

            // 跳过排除路径
            foreach ($exclude as $ex) {
                if (strpos($relPath, $ex) === 0) {
                    continue 2;
                }
            }

            $srcPath = $src . '/' . $relPath;
            $dstPath = $dst . '/' . $relPath;

            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) {
                    @mkdir($dstPath, 0755, true);
                }
                $result = self::copyUpdateFiles($src, $dst, $relPath, $exclude);
                if (!$result['success']) {
                    return $result;
                }
            } else {
                // 写入前先备份目标文件（用于回滚）
                if (file_exists($dstPath)) {
                    $bakDir = self::cacheDir() . '/file-backups';
                    if (!is_dir($bakDir)) {
                        @mkdir($bakDir, 0755, true);
                    }
                    copy($dstPath, $bakDir . '/' . str_replace('/', '__', $relPath) . '.' . time());
                }
                if (!copy($srcPath, $dstPath)) {
                    return ['success' => false, 'message' => '文件写入失败: ' . $relPath];
                }
            }
        }
        return ['success' => true];
    }

    private static function runMigrations(string $extractDir): bool
    {
        $migrateDir = $extractDir . '/migrations';
        if (!is_dir($migrateDir)) {
            return false;
        }

        $files = glob($migrateDir . '/*.sql');
        sort($files);
        $pdo = Database::getInstance();

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (empty($stmt)) {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    error_log('迁移执行失败 [' . basename($file) . ']: ' . $e->getMessage());
                }
            }
        }
        return true;
    }

    private static function cleanCoreFiles(string $root): void
    {
        $keep = ['data', 'config.php', 'install.lock', '.htaccess', '.user.ini'];
        $items = array_diff(scandir($root), ['.', '..']);
        foreach ($items as $item) {
            if (in_array($item, $keep, true)) {
                continue;
            }
            $path = $root . '/' . $item;
            if (is_dir($path)) {
                self::rmDir($path);
            } else {
                @unlink($path);
            }
        }
    }

    /**
     * 从 ZIP 格式备份回滚文件
     */
    private static function rollbackFromZip(string $zipFile, string $root, array $exclude): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['success' => false, 'message' => '备份文件无法打开，无法回滚'];
        }

        $numFiles = $zip->numFiles;
        $restored = 0;
        $failedFiles = [];

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            $skip = false;
            foreach ($exclude as $ex) {
                if (strpos($name, $ex) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $target = $root . '/' . $name;

            if (substr($name, -1) === '/') {
                if (!is_dir($target)) {
                    if (!@mkdir($target, 0755, true)) {
                        $failedFiles[] = $name . ' (创建目录失败)';
                    }
                }
                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent)) {
                @mkdir($parent, 0755, true);
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $failedFiles[] = $name . ' (读取 ZIP 内容失败)';
                continue;
            }

            if (file_put_contents($target, $content, LOCK_EX) === false) {
                $failedFiles[] = $name . ' (写入失败，权限不足？)';
                continue;
            }
            $restored++;
        }

        $zip->close();

        if (!empty($failedFiles)) {
            $firstFive = array_slice($failedFiles, 0, 5);
            $msg = '回滚部分失败，以下文件未还原（共 ' . count($failedFiles) . ' 个）：\n' . implode('\n', $firstFive);
            if (count($failedFiles) > 5) {
                $msg .= '\n... 等 ' . (count($failedFiles) - 5) . ' 个文件';
            }
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true];
    }

    /**
     * 从目录格式备份回滚文件
     */
    private static function rollbackFromDir(string $backupDir, string $root, array $exclude): array
    {
        $failedFiles = [];
        $restored = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $relPath = substr($fileInfo->getPathname(), strlen($backupDir) + 1);
            
            $skip = false;
            foreach ($exclude as $ex) {
                if (strpos($relPath, $ex) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $target = $root . '/' . $relPath;

            if ($fileInfo->isDir()) {
                if (!is_dir($target)) {
                    if (!@mkdir($target, 0755, true)) {
                        $failedFiles[] = $relPath . ' (创建目录失败)';
                    }
                }
                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent)) {
                @mkdir($parent, 0755, true);
            }

            if (!copy($fileInfo->getPathname(), $target)) {
                $failedFiles[] = $relPath . ' (复制失败)';
                continue;
            }
            $restored++;
        }

        if (!empty($failedFiles)) {
            $firstFive = array_slice($failedFiles, 0, 5);
            $msg = '回滚部分失败，以下文件未还原（共 ' . count($failedFiles) . ' 个）：\n' . implode('\n', $firstFive);
            if (count($failedFiles) > 5) {
                $msg .= '\n... 等 ' . (count($failedFiles) - 5) . ' 个文件';
            }
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true];
    }

    /** 递归删除目录 */
    public static function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? self::rmDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function dirSize(string $dir): int
    {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
}
