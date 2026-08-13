<?php
/**
 * 网站地图插件 - 数据库声明
 * 启用插件时写入默认 Sitemap 缓存配置
 */

return [
    'tables'  => [],
    'columns' => [],

    // 默认配置项：缓存有效期（秒），默认 6 小时
    'config' => [
        'sitemap_cache_ttl' => '21600',
    ],
];
