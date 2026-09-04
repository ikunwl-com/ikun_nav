<?php
/**
 * 数据库备份插件 - 核心函数
 *
 * 功能：
 *   1. 备份数据库（纯 PHP PDO 导出全部表结构和数据为 SQL 文件）
 *   2. 恢复数据库（从备份文件或上传的 SQL 文件导入）
 *   3. 删除备份文件
 *   4. 下载备份文件到本地
 *   5. 列出所有备份文件
 *
 * 备份存储目录：data/backups/
 * 安全：文件名严格校验、路径穿越防护、CSRF
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

/**
 * 获取备份目录路径（绝对路径）
 * @return string
 */
function dbtool_backupDir()
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // 写入 index.html 防止目录列表
    $indexFile = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!file_exists($indexFile)) {
        @file_put_contents($indexFile, '');
    }
    return $dir;
}

/**
 * 获取所有数据库表名（含前缀）
 * @return array
 */
function dbtool_getTables()
{
    $pdo = Database::getInstance();
    $dbName = defined('DB_NAME') ? DB_NAME : '';
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

    // 优先用 SHOW TABLES（按当前前缀过滤）
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 只备份当前项目前缀的表
    $tables = [];
    foreach ($allTables as $tbl) {
        if ($prefix === '' || strpos($tbl, $prefix) === 0) {
            $tables[] = $tbl;
        }
    }
    return $tables;
}

/**
 * 获取某张表的 CREATE TABLE 语句
 * @param string $tableName
 * @return string
 */
function dbtool_getCreateTable($tableName)
{
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SHOW CREATE TABLE `" . $tableName . "`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($row['Create Table']) ? $row['Create Table'] : '';
}

/**
 * 获取某张表的总行数
 * @param string $tableName
 * @return int
 */
function dbtool_getRowCount($tableName)
{
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM `" . $tableName . "`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['cnt'] ?? 0);
}

/**
 * 转义 SQL 值
 * @param mixed $value
 * @return string
 */
function dbtool_escapeValue($value)
{
    if (is_null($value)) {
        return 'NULL';
    }
    if (is_int($value)) {
        return (string)$value;
    }
    if (is_float($value)) {
        return (string)$value;
    }
    // 字符串：用 PDO quote
    $pdo = Database::getInstance();
    return $pdo->quote((string)$value);
}

/**
 * 备份整个数据库
 * @return array ['ok'=>bool, 'file'=>string, 'error'=>string, 'size'=>int, 'tables'=>int, 'rows'=>int]
 */
function dbtool_backup()
{
    $result = ['ok' => false, 'file' => '', 'error' => '', 'size' => 0, 'tables' => 0, 'rows' => 0];

    try {
        $tables = dbtool_getTables();
        if (empty($tables)) {
            $result['error'] = '没有找到需要备份的数据表';
            return $result;
        }

        // 生成备份文件名
        $siteName = setting('site_name', 'nav');
        $dateStr = date('Ymd_His');
        $filename = 'backup_' . $dateStr . '.sql';
        $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;

        // 防止文件名冲突（一秒内多次操作）
        $counter = 1;
        while (file_exists($filepath)) {
            $filename = 'backup_' . $dateStr . '_' . $counter . '.sql';
            $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;
            $counter++;
        }

        $pdo = Database::getInstance();
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $phpVersion = PHP_VERSION;
        $mysqlVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        $sql = '';
        $sql .= "-- 懒人导航 数据库备份\n";
        $sql .= "-- 站点: " . $siteName . "\n";
        $sql .= "-- 生成时间: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- PHP版本: " . $phpVersion . "\n";
        $sql .= "-- MySQL版本: " . $mysqlVersion . "\n";
        $sql .= "-- 数据库: " . $dbName . "\n";
        $sql .= "-- 表数量: " . count($tables) . "\n";
        $sql .= "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE='';\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "\n";

        $totalRows = 0;

        foreach ($tables as $table) {
            // 获取建表语句
            $createSql = dbtool_getCreateTable($table);
            $sql .= "-- ----------------------------\n";
            $sql .= "-- Table: `" . $table . "`\n";
            $sql .= "-- ----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
            $sql .= $createSql . ";\n";
            $sql .= "\n";

            // 获取数据
            $rowCount = dbtool_getRowCount($table);
            $totalRows += $rowCount;

            if ($rowCount > 0) {
                // 分批获取数据，避免内存溢出
                $batchSize = 500;
                $batches = (int)ceil($rowCount / $batchSize);

                for ($b = 0; $b < $batches; $b++) {
                    $offset = $b * $batchSize;
                    $limit = $batchSize;

                    $dataStmt = $pdo->prepare("SELECT * FROM `" . $table . "` LIMIT {$offset}, {$limit}");
                    $dataStmt->execute();
                    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($rows)) {
                        continue;
                    }

                    // 获取列名
                    $columns = array_keys($rows[0]);
                    $colList = implode('`, `', array_map(function ($c) {
                        return $c;
                    }, $columns));
                    $colList = '`' . $colList . '`';

                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columns as $col) {
                            $values[] = dbtool_escapeValue($row[$col] ?? null);
                        }
                        $sql .= "INSERT INTO `" . $table . "` (" . $colList . ") VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                    unset($rows);
                }
            }

            $sql .= "\n";
            $result['tables']++;
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // 写入文件
        $writeResult = @file_put_contents($filepath, $sql);
        if ($writeResult === false) {
            $result['error'] = '备份文件写入失败，请检查 data/backups/ 目录权限';
            return $result;
        }

        $result['ok'] = true;
        $result['file'] = $filename;
        $result['size'] = $writeResult;
        $result['rows'] = $totalRows;

        // 记录日志
        if (class_exists('Logger')) {
            Logger::log('admin_setting', "数据库备份成功: 文件={$filename} 表={$result['tables']} 行={$totalRows} 大小=" . dbtool_formatSize($writeResult));
        }

        return $result;
    } catch (\Exception $e) {
        $result['error'] = '备份失败: ' . $e->getMessage();
        if (class_exists('Logger')) {
            Logger::log('database_error', "数据库备份异常: " . $e->getMessage());
        }
        return $result;
    }
}

/**
 * 列出所有备份文件
 * @return array 每个元素 ['filename', 'filepath', 'size', 'size_text', 'created_at', 'tables', 'rows']
 */
function dbtool_listBackups()
{
    $dir = dbtool_backupDir();
    $files = [];

    if (!is_dir($dir)) {
        return $files;
    }

    $handle = opendir($dir);
    if (!$handle) {
        return $files;
    }

    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..' || $file === 'index.html' || $file === '.htaccess') {
            continue;
        }
        // 只接受 .sql 文件
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
            continue;
        }

        $filepath = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($filepath)) {
            continue;
        }

        $size = filesize($filepath);
        $mtime = filemtime($filepath);

        // 从文件名解析信息
        $info = dbtool_parseBackupHeader($filepath);

        $files[] = [
            'filename'    => $file,
            'filepath'     => $filepath,
            'size'        => $size,
            'size_text'   => dbtool_formatSize($size),
            'created_at'  => date('Y-m-d H:i:s', $mtime),
            'tables'      => $info['tables'],
            'rows'        => $info['rows'],
            'site'         => $info['site'],
        ];
    }
    closedir($handle);

    // 按修改时间倒序（最新的在前）
    usort($files, function ($a, $b) {
        return strcmp($b['filename'], $a['filename']);
    });

    return $files;
}

/**
 * 从备份文件头部解析元信息
 * @param string $filepath
 * @return array ['tables'=>int, 'rows'=>int, 'site'=>string]
 */
function dbtool_parseBackupHeader($filepath)
{
    $info = ['tables' => 0, 'rows' => 0, 'site' => ''];

    // 只读前 2KB 获取头部信息
    $handle = @fopen($filepath, 'r');
    if (!$handle) {
        return $info;
    }

    $header = fread($handle, 2048);
    fclose($handle);

    // 解析表数量
    if (preg_match('/-- 表数量:\s*(\d+)/', $header, $m)) {
        $info['tables'] = (int)$m[1];
    }

    // 解析站点名
    if (preg_match('/-- 站点:\s*(.+)/', $header, $m)) {
        $info['site'] = trim($m[1]);
    }

    // 统计 INSERT 行数（读整个文件统计）
    $info['rows'] = dbtool_countInserts($filepath);

    return $info;
}

/**
 * 统计 SQL 文件中的 INSERT 语句数量
 * @param string $filepath
 * @return int
 */
function dbtool_countInserts($filepath)
{
    $count = 0;
    $handle = @fopen($filepath, 'r');
    if (!$handle) {
        return 0;
    }
    while (($line = fgets($handle, 65536)) !== false) {
        if (strpos($line, 'INSERT INTO') === 0) {
            $count++;
        }
    }
    fclose($handle);
    return $count;
}

/**
 * 格式化文件大小
 * @param int $bytes
 * @return string
 */
function dbtool_formatSize($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

/**
 * 校验备份文件名安全性（防路径穿越）
 * @param string $filename
 * @return bool
 */
function dbtool_validateFilename($filename)
{
    if (empty($filename)) {
        return false;
    }
    // 只允许字母数字下划线点和连字符
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.sql$/', $filename)) {
        return false;
    }
    // 不允许 .. 或路径分隔符
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }
    return true;
}

/**
 * 删除备份文件
 * @param string $filename
 * @return array ['ok'=>bool, 'error'=>string]
 */
function dbtool_delete($filename)
{
    $result = ['ok' => false, 'error' => ''];

    if (!dbtool_validateFilename($filename)) {
        $result['error'] = '文件名不合法';
        return $result;
    }

    $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($filepath)) {
        $result['error'] = '备份文件不存在';
        return $result;
    }

    if (@unlink($filepath)) {
        $result['ok'] = true;
        if (class_exists('Logger')) {
            Logger::log('admin_setting', "删除数据库备份: 文件={$filename}");
        }
    } else {
        $result['error'] = '删除失败，请检查文件权限';
    }

    return $result;
}

/**
 * 恢复数据库（从备份文件或上传的 SQL 文件）
 * @param string $filepath SQL 文件绝对路径
 * @return array ['ok'=>bool, 'error'=>string, 'statements'=>int]
 */
function dbtool_restore($filepath)
{
    $result = ['ok' => false, 'error' => '', 'statements' => 0];

    if (!file_exists($filepath)) {
        $result['error'] = '文件不存在';
        return $result;
    }

    $size = filesize($filepath);
    if ($size <= 0) {
        $result['error'] = '文件为空';
        return $result;
    }

    try {
        $pdo = Database::getInstance();

        // 读取文件内容
        $sqlContent = file_get_contents($filepath);
        if ($sqlContent === false) {
            $result['error'] = '读取文件失败';
            return $result;
        }

        // 分割 SQL 语句
        $statements = dbtool_splitSql($sqlContent);
        $count = 0;
        $errors = [];

        // 关闭外键检查
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("SET NAMES utf8mb4");

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) {
                continue;
            }
            // 跳过纯注释行
            if (strpos($stmt, '--') === 0) {
                continue;
            }

            try {
                $pdo->exec($stmt);
                $count++;
            } catch (\PDOException $e) {
                // 记录错误但继续执行
                $errors[] = $e->getMessage();
            }
        }

        // 重新开启外键检查
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        $result['ok'] = true;
        $result['statements'] = $count;

        if (!empty($errors)) {
            $result['error'] = '部分语句执行失败(' . count($errors) . '条): ' . mb_substr(implode('; ', array_slice($errors, 0, 3)), 0, 200);
        }

        if (class_exists('Logger')) {
            Logger::log('admin_setting', "数据库恢复成功: 文件=" . basename($filepath) . " 语句={$count}" . (!empty($errors) ? " 错误数=" . count($errors) : ''));
        }

        return $result;
    } catch (\Exception $e) {
        $result['error'] = '恢复失败: ' . $e->getMessage();
        if (class_exists('Logger')) {
            Logger::log('database_error', "数据库恢复异常: " . $e->getMessage());
        }
        return $result;
    }
}

/**
 * 将 SQL 内容分割为独立语句
 * 处理多行语句、字符串中的分号、注释等
 * @param string $content
 * @return array
 */
function dbtool_splitSql($content)
{
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $inComment = false;
    $length = strlen($content);

    for ($i = 0; $i < $length; $i++) {
        $char = $content[$i];
        $nextChar = ($i + 1 < $length) ? $content[$i + 1] : '';

        // 处理注释 -- 行注释
        if (!$inString && $char === '-' && $nextChar === '-') {
            // 跳到行尾
            while ($i < $length && $content[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // 处理 /* */ 块注释
        if (!$inString && $char === '/' && $nextChar === '*') {
            $i += 2;
            while ($i < $length) {
                if ($content[$i] === '*' && ($i + 1 < $length) && $content[$i + 1] === '/') {
                    $i += 2;
                    break;
                }
                $i++;
            }
            continue;
        }

        // 处理 # 行注释
        if (!$inString && $char === '#') {
            while ($i < $length && $content[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // 处理字符串
        if (!$inString && ($char === "'" || $char === '"')) {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
            continue;
        }
        if ($inString && $char === $stringChar) {
            // 检查是否为转义（双引号转义）
            $backslashes = 0;
            $j = $i - 1;
            while ($j >= 0 && $content[$j] === '\\') {
                $backslashes++;
                $j--;
            }
            if ($backslashes % 2 === 0) {
                $inString = false;
            }
            $current .= $char;
            continue;
        }

        // 分号结尾（不在字符串中）
        if (!$inString && $char === ';') {
            $current .= ';';
            $statements[] = $current;
            $current = '';
            continue;
        }

        $current .= $char;
    }

    // 处理最后未以分号结尾的语句
    $current = trim($current);
    if (!empty($current)) {
        $statements[] = $current;
    }

    return $statements;
}

/**
 * 处理上传的 SQL 文件
 * @param array $file $_FILES 数组元素
 * @return array ['ok'=>bool, 'filepath'=>string, 'error'=>string, 'filename'=>string]
 */
function dbtool_handleUpload($file)
{
    $result = ['ok' => false, 'filepath' => '', 'error' => '', 'filename' => ''];

    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = '文件上传失败，错误码: ' . $file['error'];
        return $result;
    }

    // 检查文件大小（限制 50MB）
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $result['error'] = '文件过大，最大支持 50MB';
        return $result;
    }

    // 检查文件扩展名
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        $result['error'] = '只支持 .sql 文件';
        return $result;
    }

    // 生成安全文件名
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $originalName = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $originalName);
    $filename = 'import_' . $originalName . '_' . date('Ymd_His') . '.sql';
    $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['error'] = '文件保存失败';
        return $result;
    }

    $result['ok'] = true;
    $result['filepath'] = $filepath;
    $result['filename'] = $filename;

    return $result;
}

/**
 * 下载备份文件
 * @param string $filename
 */
function dbtool_download($filename)
{
    if (!dbtool_validateFilename($filename)) {
        http_response_code(403);
        die('文件名不合法');
    }

    $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        die('文件不存在');
    }

    // 设置下载头
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    // 输出文件
    readfile($filepath);
    exit;
}

// ============================================================
//  本插件不注册 admin_sidebar 钩子：
//  后台入口由 admin/plugin.php 自动检测 admin.php 文件提供，
//  插件列表页的“管理”按钮即可进入，无需插入侧边导航。
// ============================================================

