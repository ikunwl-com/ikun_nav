<?php
/**
 * 伪静态设置插件 - 主文件
 * 伪静态路由解析由核心 Rewrite.php 和 Route.php 处理
 * 设置面板在 main.php 中
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}
