<?php
/**
 * 补录已删除的空缺 ID 到回收队列
 * 上传后访问 /deleted_ids.php 执行一次，执行完删除此文件
 * 
 * 安全：需要管理员登录态，防止未授权访问
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

// 安全：要求管理员登录态
Security::initSession();
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo "[ERROR] 需要管理员登录后才能执行此操作\n";
    echo "[HINT] 请先登录后台，再访问此页面\n";
    exit;
}

// 安全：CSRF 校验（通过 GET 参数 token）
$csrfToken = $_GET['token'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo "[ERROR] CSRF 校验失败，请从后台管理页面执行此操作\n";
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $sitesTbl = DB_PREFIX . 'sites';
    $delTbl = DB_PREFIX . 'deleted_ids';

    // 1) 建表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$delTbl}` (
        id INT PRIMARY KEY,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] 表 {$delTbl} 已就绪\n";

    // 2) 找最大 ID
    $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM `{$sitesTbl}`")->fetchColumn();
    if ($maxId === 0) {
        echo "[INFO] 站点表为空，无需补录\n";
        exit;
    }
    echo "[INFO] 当前最大ID: {$maxId}\n";

    // 3) 找出所有空缺段（包括连续多个空缺）
    $stmt = $pdo->query("
        SELECT a.id + 1 AS gap_start
        FROM `{$sitesTbl}` a
        LEFT JOIN `{$sitesTbl}` b ON b.id = a.id + 1
        WHERE b.id IS NULL AND a.id < {$maxId}
        ORDER BY a.id
    ");
    $gaps = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($gaps)) {
        echo "[INFO] 没有空缺ID需要补录\n";
        exit;
    }

    echo "[INFO] 找到 " . count($gaps) . " 个空缺段\n";

    // 4) 逐段扫描并补录
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO `{$delTbl}` (id) VALUES (?)");
    $total = 0;

    foreach ($gaps as $start) {
        // 向后扫描找到连续空缺的结束位置
        $end = $start;
        while ($end < $maxId) {
            $check = $pdo->query("SELECT 1 FROM `{$sitesTbl}` WHERE id = " . ($end + 1))->fetch();
            if ($check) break;
            $end++;
        }

        // 批量插入这段所有空缺 ID
        for ($i = $start; $i <= $end; $i++) {
            $insertStmt->execute([$i]);
            if ($insertStmt->rowCount() > 0) $total++;
        }
    }

    echo "[DONE] 补录完成，共 {$total} 个空缺ID已写入回收队列\n";
    echo "[TIP] 请删除本文件（fix_deleted_ids.php）\n";

} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
