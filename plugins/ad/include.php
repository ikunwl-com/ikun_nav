<?php
/**
 * 广告管理插件 - 主文件
 * 提供广告位渲染函数和前台钩子
 *
 * 配置项：
 *   plugin_ad_site_list_before  - 首页列表前广告
 *   plugin_ad_site_list_after   - 首页列表后广告
 *   plugin_ad_sidebar_top       - 侧边栏顶部广告
 *   plugin_ad_sidebar_bottom    - 侧边栏底部广告
 *   plugin_ad_before_content    - 站点详情内容前广告
 *   plugin_ad_after_content     - 站点详情内容后广告
 *
 * 前台钩子：
 *   site_list_before, site_list_after, sidebar_top, sidebar_bottom,
 *   before_content, after_content
 */

// 安全检查：阻止直接访问
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 输出广告 HTML
 */
function ad_render(string $position): void
{
    $code = Plugin::config('ad', $position, '');
    if (empty($code)) {
        return;
    }
    // 允许管理员配置 HTML，但经过 cleanHtml 过滤
    $html = Security::cleanHtml($code, 10000);
    if ($html) {
        echo '<div class="plugin-ad plugin-ad-' . htmlspecialchars($position) . '">' . "\n";
        echo $html;
        echo "\n</div>\n";
    }
}

// ========== 前台广告位钩子 ==========
Plugin::registerHook('site_list_before', function () {
    ad_render('site_list_before');
});

Plugin::registerHook('site_list_after', function () {
    ad_render('site_list_after');
});

Plugin::registerHook('sidebar_top', function () {
    ad_render('sidebar_top');
});

Plugin::registerHook('sidebar_bottom', function () {
    ad_render('sidebar_bottom');
});

Plugin::registerHook('before_content', function () {
    ad_render('before_content');
});

Plugin::registerHook('after_content', function () {
    ad_render('after_content');
});
