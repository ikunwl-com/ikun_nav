<?php
/**
 * 广告管理插件 - 数据库声明
 * 启用插件时写入默认广告位配置（均为空，管理员在后台填充）
 */

return [
    'tables'  => [],
    'columns' => [],

    // 默认配置项：6 个广告位，默认为空
    'config' => [
        'plugin_ad_site_list_before' => '',
        'plugin_ad_site_list_after'  => '',
        'plugin_ad_sidebar_top'      => '',
        'plugin_ad_sidebar_bottom'   => '',
        'plugin_ad_before_content'   => '',
        'plugin_ad_after_content'    => '',
    ],
];
