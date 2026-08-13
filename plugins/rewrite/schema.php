<?php
/**
 * 伪静态设置插件 - 数据库声明
 * 启用插件时写入默认 URL 格式配置
 *
 * 注意：这些配置被核心 Rewrite.php 用于 URL 生成和路由解析。
 * 即使插件未启用，SettingsModel 仍提供默认值作为兜底。
 */

return [
    'tables'  => [],
    'columns' => [],

    // 默认配置项
    'config' => [
        'rewrite_mode'              => 'dynamic',
        'url_format_home'           => '/',
        'url_format_category'       => 'category/{%slug%}/',
        'url_format_category_page'  => 'category/{%slug%}/page-{%page%}/',
        'url_format_site'           => 'site/{%id%}/',
        'url_format_search'         => 'search/',
        'url_format_submit'         => 'submit/',
        'url_format_wormhole'       => 'wormhole/',
        'url_format_article_list'   => 'articles/',
        'url_format_article'        => 'article/{%id%}/',
    ],
];
