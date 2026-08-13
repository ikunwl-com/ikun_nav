<?php
/**
 * 提交网站收录插件 - 主文件
 * 提供前台提交入口按钮
 *
 * 配置项：
 *   plugin_submit_rules     - 收录规则 HTML 内容（显示在提交页顶部）
 *   plugin_submit_need_review - 是否需要审核（1=需要，0=直接发布）
 *
 * 前台钩子：
 *   search_bar_after - 搜索栏右侧显示"提交站点"按钮
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 前台搜索栏钩子 ==========
Plugin::registerHook('search_bar_after', 'plugin_submit_entry', 10);

/**
 * 首页提交站点入口按钮
 */
function plugin_submit_entry(): void
{
    // 检查提交功能是否开启，优先读取插件配置
    $pluginEnable = Plugin::config('submit', 'enable_submit', null);
    $enable = ($pluginEnable !== null)
        ? ($pluginEnable === '1')
        : (setting('enable_submit', '1') === '1');
    if (!$enable) {
        return;
    }

    $submitUrl = Rewrite::url('submit');
    ?>
    <a href="<?= Theme::eAttr($submitUrl) ?>" class="submit-btn"><i class="ti ti-plus"></i> 提交站点</a>
    <?php
}
