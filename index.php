<?php
/**
 * 懒人导航 - 前台统一入口
 * 同时作为 PHP 内置服务器的路由脚本
 */

// PHP 内置服务器：如果请求的是真实存在的文件（非 .php 入口），直接返回
if (php_sapi_name() === 'cli-server') {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // 安全：防止路径遍历，拒绝包含 .. 的请求
    if (strpos($requestUri, '..') !== false) {
        http_response_code(400);
        exit('Bad Request');
    }
    
    $filePath = __DIR__ . $requestUri;
    
    // API 请求直接转发到 api/index.php
    if (strpos($requestUri, '/api/') === 0) {
        require __DIR__ . '/api/index.php';
        exit;
    }
    
    // sitemap.xml / robots.txt 等固定路由文件不直接返回，交给 Route 处理
    $routeFiles = ['sitemap.xml', 'robots.txt'];
    $basename = basename($requestUri);
    if (in_array($basename, $routeFiles, true) || preg_match('/^sitemap-\d+\.xml$/', $basename)) {
        // 交给后面的 Route::dispatch() 处理
    } elseif ($requestUri !== '/' && is_file($filePath) && !preg_match('/\.(php)$/i', $requestUri)) {
        return false;
    } elseif ($requestUri !== '/' && is_file($filePath) && preg_match('/\.(php)$/i', $requestUri)) {
        // .php 文件直接由服务器处理（如 /admin/login.php）
        return false;
    }
}

try {
    require_once __DIR__ . '/core/bootstrap.php';
    Route::dispatch();
} catch (Throwable $e) {
    // bootstrap 已定义 APP_DEBUG，此处兜底处理 bootstrap 加载前就出错的情况
    if (!defined('APP_DEBUG')) {
        define('APP_DEBUG', false);
    }

    // bootstrap 已设置过异常处理器，调用它来显示
    $handler = set_exception_handler(null);
    if ($handler) {
        $handler($e);
    } else {
        // 兜底：bootstrap 加载前就出错
        http_response_code(500);
        if (APP_DEBUG) {
            echo '<div style="background:#fff3f3;border:2px solid #f44336;padding:20px;margin:20px;font-family:monospace;white-space:pre-wrap;word-break:break-all;">';
            echo '<h3 style="color:#c00;margin-top:0">错误信息</h3>';
            echo '<p style="color:#c00;font-weight:bold">' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
            echo '<h4 style="color:#333;margin-bottom:5px">文件位置</h4>';
            echo '<p>' . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
            echo '<h4 style="color:#333;margin-bottom:5px">堆栈追踪</h4>';
            echo '<pre style="background:#f8f8f8;padding:10px;border:1px solid #ddd;overflow:auto">';
            echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            echo '</pre></div>';
        } else {
            echo '服务器内部错误，请稍后重试';
        }
        exit(1);
    }
}
