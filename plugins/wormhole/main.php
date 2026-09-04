<?php
/**
 * 虫洞联盟插件 - 设置面板（已弃用）
 *
 * 虫洞联盟的所有后台管理功能（包括设置项）已合并到 admin.php 独立管理页面。
 * 侧边栏通过 include.php 的 admin_sidebar 钩子自动注册。
 *
 * 不再需要 admin_settings_nav / admin_settings_tabs 钩子嵌入基础设置页。
 */

if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

// 本文件保留为空，避免 schema.php 引用时出错。
// 如需恢复基础设置页嵌入，请重新在此注册 admin_settings_nav 和 admin_settings_tabs 钩子。
