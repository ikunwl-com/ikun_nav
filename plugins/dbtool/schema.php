<?php
/**
 * 数据库备份插件 - 数据库声明
 * 本插件不创建独立数据表，备份文件存储在 data/backups/ 目录
 * 仅声明默认配置项
 */

return [
    // 无独立表
    'tables' => [],

    // 不向已有表添加字段
    'columns' => [],

    // 默认配置项
    'config' => [
        'plugin_dbtool_enabled' => '0',
    ],
];
