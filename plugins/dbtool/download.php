<?php
/**
 * 数据库备份插件 - 文件下载处理器
 * 独立入口，直接访问 /plugins/dbtool/download.php?file=xxx.sql
 * 用于流式下载备份文件到浏览器
 */

// 引入核心引导（启动 session、加载插件、加载核心类）
$coreBootstrap = dirname(__DIR__, 2) . '/core/bootstrap.php';
if (!file_exists($coreBootstrap)) {
    http_response_code(500);
    die('系统引导文件缺失');
}
require_once $coreBootstrap;

// 验证管理员登录状态
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('未登录或登录已过期');
}

// 加载插件核心函数
$includeFile = __DIR__ . '/include.php';
if (!file_exists($includeFile)) {
    http_response_code(500);
    die('插件文件缺失');
}
require_once $includeFile;

// 获取文件名
$filename = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($filename)) {
    http_response_code(400);
    die('缺少文件名参数');
}

// 执行下载（函数内部会校验文件名安全性和文件存在性）
dbtool_download($filename);
