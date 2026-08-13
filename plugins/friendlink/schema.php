<?php
/**
 * 友情链接插件 - 数据库声明
 * 启用插件时自动创建 friendlinks 表并写入默认配置
 */

return [
    // 独立表
    'tables' => [
        'friendlinks' => "CREATE TABLE IF NOT EXISTS `{prefix}friendlinks` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL COMMENT '友链名称',
            url VARCHAR(500) NOT NULL COMMENT '友链链接',
            css_class VARCHAR(200) DEFAULT '' COMMENT '自定义CSS类名(填写则输出,不填则不输出)',
            icon VARCHAR(500) DEFAULT '' COMMENT '图标URL或Tabler图标类名(填写则显示,不填则不显示)',
            sort_order INT DEFAULT 0 COMMENT '排序(越小越靠前)',
            status TINYINT DEFAULT 1 COMMENT '状态(1=显示,0=隐藏)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='友情链接表';",
    ],

    // 向已有表添加字段（无）
    'columns' => [],

    // 默认配置项
    'config' => [
        'plugin_friendlink_title'       => '友情链接',
        'plugin_friendlink_target'      => '_blank',
        'plugin_friendlink_max_display' => '50',
    ],
];
