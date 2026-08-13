<?php
/**
 * 插件后台管理页面统一分发器
 * 通过 ?p=插件名 参数分发到对应插件的 admin.php
 *
 * 示例：/admin/plugin.php?p=article  → 加载 plugins/article/admin.php
 *       /admin/plugin.php?p=wormhole → 加载 plugins/wormhole/admin.php
 */
require_once __DIR__ . '/bootstrap.php';

$pluginName = $_GET['p'] ?? '';

// 校验插件名
if (empty($pluginName) || !preg_match('/^[a-z0-9\-]+$/', $pluginName)) {
    redirect('/admin/plugins.php?err=' . urlencode('无效的插件名称'));
}

// 校验插件是否已启用
if (!Plugin::isEnabled($pluginName)) {
    redirect('/admin/plugins.php?err=' . urlencode('插件未启用或不存在'));
}

// 校验插件是否有后台管理页面
$adminFile = __DIR__ . '/../plugins/' . $pluginName . '/admin.php';
// 安全：realpath 校验确保路径不逃出 plugins 目录
$realPath = realpath($adminFile);
$pluginsDir = realpath(__DIR__ . '/../plugins');
if (!$realPath || !$pluginsDir || strpos($realPath, $pluginsDir) !== 0) {
    redirect('/admin/plugins.php?err=' . urlencode('插件路径非法'));
}
if (!file_exists($adminFile)) {
    redirect('/admin/plugins.php?err=' . urlencode('该插件没有后台管理页面'));
}

$info = Plugin::getInfo($pluginName);
$pageTitle = $info['title'] ?? '插件管理';
$currentPage = $pluginName;

adminHeader($pageTitle);
require_once $adminFile;
adminFooter();
