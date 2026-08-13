<?php
/**
 * 友链自动收录插件 - 数据库声明
 * 启用插件时自动创建 blacklist 表（如不存在）并写入默认配置
 *
 * 注意：blacklist 表由 wormhole 和 auto-link 两个插件共享，
 * 卸载时 Plugin::uninstall 会智能检查，仅当两个插件都卸载时才删表。
 */

return [
    // 独立表（与 wormhole 插件共享）
    'tables' => [
        'blacklist' => "CREATE TABLE IF NOT EXISTS `{prefix}blacklist` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            type ENUM('ip','domain') NOT NULL COMMENT '屏蔽类型',
            value VARCHAR(200) NOT NULL COMMENT '屏蔽值（IP或域名）',
            remark VARCHAR(200) DEFAULT '' COMMENT '备注说明',
            created_by INT DEFAULT 0 COMMENT '创建者管理员ID',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status TINYINT DEFAULT 1 COMMENT '状态(1=启用,0=禁用)',
            UNIQUE KEY uk_type_value (type, value),
            INDEX idx_type (type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ],

    // 向已有表添加字段（无）
    'columns' => [],

    // 默认配置项
    'config' => [
        'autolink_enable'           => '0',
        'autolink_need_review'      => '1',
        'autolink_default_category' => '0',
        'autolink_banned_words'     => '',
    ],
];
