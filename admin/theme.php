<?php
/**
 * 主题后台设置页统一分发器
 * 通过 ?name=主题名 分发到对应主题的 admin.php（templates/{主题名}/admin.php）
 *
 * 示例：/admin/theme.php?name=default → 加载 templates/default/admin.php
 *
 * 规则与插件管理一致：
 *   - 只有「当前正在使用的主题」允许进入设置页（未启用/未切换先切换）
 *   - 主题目录下存在 admin.php 才视为自带后台设置页
 *   - admin.php 由本分发器包裹 adminHeader()/adminFooter()，文件内直接输出内容
 *
 * 主题配置存储：settings 表，key 前缀 theme_{主题名}_（见 core/Theme.php config/setConfig）
 */
require_once __DIR__ . '/bootstrap.php';

$themeName = $_GET['name'] ?? '';

// 校验主题名（字母/数字/横线/下划线）
if (empty($themeName) || !preg_match('/^[a-zA-Z0-9\-_]+$/', $themeName)) {
    redirect('/admin/themes.php?err=' . urlencode('无效的主题名称'));
}

// 仅允许配置当前正在使用的主题
if (Theme::current() !== $themeName || !Theme::exists($themeName)) {
    redirect('/admin/themes.php?err=' . urlencode('该主题未在使用中，请先切换到该主题后再进行设置'));
}

// 校验主题是否有后台设置页
$adminFile = __DIR__ . '/../templates/' . $themeName . '/admin.php';
// 安全：realpath 校验确保路径不逃出 templates 目录
$realPath = realpath($adminFile);
$templatesDir = realpath(__DIR__ . '/../templates');
if (!$realPath || !$templatesDir || strpos($realPath, $templatesDir) !== 0) {
    redirect('/admin/themes.php?err=' . urlencode('主题路径非法'));
}
if (!file_exists($adminFile)) {
    redirect('/admin/themes.php?err=' . urlencode('该主题没有后台设置页'));
}

$info = Theme::getInfo($themeName);
$pageTitle = ($info['title'] ?? $themeName) . ' - 主题设置';
$currentPage = 'themes';

adminHeader($pageTitle);
require_once $adminFile;
adminFooter();
