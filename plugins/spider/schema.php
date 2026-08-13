<?php
/**
 * 蜘蛛来访统计插件 - 数据库声明
 * 启用插件时自动创建 spider_visits 表并写入默认配置
 *
 * 数据保留策略：30天自动清理（由 SpiderModel::purgeOldRecords 执行）
 */

return [
    // 独立表
    'tables' => [
        'spider_visits' => "CREATE TABLE IF NOT EXISTS `{prefix}spider_visits` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            spider_type VARCHAR(30) NOT NULL COMMENT '蜘蛛类型(baidu/google/bing/sogou/360/yandex/bytespider)',
            url VARCHAR(500) NOT NULL COMMENT '访问URL路径',
            ip VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
            user_agent VARCHAR(500) DEFAULT '' COMMENT 'User-Agent',
            visited_at DATETIME NOT NULL COMMENT '访问时间',
            INDEX idx_spider_type (spider_type),
            INDEX idx_visited_at (visited_at),
            INDEX idx_type_date (spider_type, visited_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='蜘蛛来访记录';",
    ],

    // 向已有表添加字段（无）
    'columns' => [],

    // 默认配置项
    'config' => [
        'plugin_spider_retention_days' => '30',
        'plugin_spider_engines'        => 'baidu,google,bing,sogou,360,bytespider,yandex',
    ],
];
