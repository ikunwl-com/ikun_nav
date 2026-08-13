<?php
/**
 * 文章发布插件 - 数据库声明
 * 启用插件时自动创建 articles 表并写入默认配置
 */

return [
    // 独立表
    'tables' => [
        'articles' => "CREATE TABLE IF NOT EXISTS `{prefix}articles` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL COMMENT '文章标题',
            slug VARCHAR(200) DEFAULT '' COMMENT 'URL别名',
            content MEDIUMTEXT COMMENT '文章内容（HTML）',
            excerpt VARCHAR(500) DEFAULT '' COMMENT '摘要',
            author VARCHAR(100) DEFAULT '' COMMENT '作者',
            category VARCHAR(100) DEFAULT '' COMMENT '分类',
            tags VARCHAR(500) DEFAULT '' COMMENT '标签（逗号分隔）',
            status ENUM('published','draft','pending') DEFAULT 'draft' COMMENT '状态',
            views INT DEFAULT 0 COMMENT '浏览量',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';",
    ],

    // 向已有表添加字段（无）
    'columns' => [],

    // 默认配置项
    'config' => [
        'plugin_article_per_page'       => '10',
        'plugin_article_enable_submit'  => '0',
    ],
];
