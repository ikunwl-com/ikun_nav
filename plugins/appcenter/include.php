<?php
/**
 * 应用中心插件 - 主文件（仅启用时加载）
 *
 * 功能：
 *   1. 后台侧边栏注册「应用中心」入口（admin_sidebar 钩子）
 *   2. 真正的管理页面由 admin/plugin.php?p=appcenter 分发到 admin.php
 *
 * 目录结构：
 *   plugin.json    元数据
 *   include.php    本文件（钩子注册）
 *   lib.php        共享函数库（目录拉取/安全下载/解压/安装升级）
 *   api.php        AJAX 接口（list / refresh / install / save_config）
 *   admin.php      后台商店页面（admin/plugin.php?p=appcenter 载入）
 *
 * 安全说明：
 *   - 所有状态变更走 api.php，需登录 + CSRF 双重校验
 *   - 下载地址仅允许服务器同域（或配置白名单），且禁止内网地址
 *   - ZIP 解压逐条校验路径，杜绝路径穿越与符号链接逃逸
 *   - 安装前自动备份、失败自动回滚，升级不动数据库表
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 钩子注册：仅在插件启用时执行 ==========
if (Plugin::isEnabled('appcenter')) {
    // 引入共享函数库：安装来源标记（官方/第三方/自定义）需供「插件管理 / 主题管理」页面读取
    require_once __DIR__ . '/lib.php';

    // 后台侧边栏：应用中心入口
    Plugin::registerHook('admin_sidebar', function () {
        $cls = ($GLOBALS['currentPage'] ?? '') === 'appcenter' ? 'active' : '';
        echo '<a href="/admin/plugin.php?p=appcenter" class="nav-item ' . $cls . '">'
           . '<i class="ti ti-apps"></i><span>应用中心</span></a>';
    });
}
