<?php
/**
 * 虫洞联盟插件 - 数据库声明
 * 启用插件时自动创建 blacklist 表、向 sites 表添加虫洞字段并写入默认配置
 *
 * 注意：blacklist 表由 wormhole 和 auto-link 两个插件共享，
 * 卸载时 Plugin::uninstall 会智能检查，仅当两个插件都卸载时才删表。
 */

return [
    // 独立表（与 auto-link 插件共享）
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

    // 向 sites 表添加虫洞联盟相关字段
    'columns' => [
        'sites' => [
            'wormhole_status'        => "ENUM('none','manual','auto','pending','broken') DEFAULT 'none' COMMENT '虫洞联盟状态'",
            'wormhole_joined_at'     => "TIMESTAMP NULL COMMENT '加入联盟时间'",
            'wormhole_last_check'    => "TIMESTAMP NULL COMMENT '上次检测时间'",
            'wormhole_check_fail'    => "INT DEFAULT 0 COMMENT '连续检测失败次数'",
            'wormhole_source_domain' => "VARCHAR(200) DEFAULT '' COMMENT '检测来源域名'",
            'wormhole_quality_score' => "DECIMAL(5,2) DEFAULT 0.00 COMMENT '虫洞质量评分(0-100)'",
            'wormhole_click_in'      => "INT DEFAULT 0 COMMENT '虫洞点入次数(回流)'",
            'wormhole_click_out'     => "INT DEFAULT 0 COMMENT '虫洞点出次数(送出)'",
            'wormhole_last_content_update' => "TIMESTAMP NULL COMMENT '站点内容上次更新时间'",
            'wormhole_quality_updated_at'  => "TIMESTAMP NULL COMMENT '质量评分上次更新时间'",
        ],
    ],

    // 默认配置项
    'config' => [
        'wormhole_enable'              => '1',
        'wormhole_need_review'         => '0',
        'wormhole_fallback_category'   => '1',
        'plugin_wormhole_rate_limit'   => '1',
        'block_all_ip'                 => '0',
    ],
];
