<?php
/**
 * 示例插件 - 主文件
 * 仅在启用时加载；注册 before_footer 钩子，在前台页脚输出一行提示，
 * 用于验证应用中心的「一键安装 / 升级 / 自动启用」流程是否生效。
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

if (Plugin::isEnabled('demo-plugin')) {
    Plugin::registerHook('before_footer', 'demo_plugin_footer_note', 30);
}

function demo_plugin_footer_note(): void
{
    $ver = Plugin::getInfo('demo-plugin');
    $v = isset($ver['version']) ? $ver['version'] : '1.0.0';
    echo '<div style="text-align:center;font-size:12px;color:#9ca3af;padding:8px 0;">'
       . '示例插件 v' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8')
       . ' 运行中 —— 应用中心安装/升级验证通过</div>';
}
