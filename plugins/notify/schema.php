<?php
/**
 * 邮箱通知插件 - 数据库声明
 * 启用插件时创建 notify_logs 表并写入默认 SMTP 配置
 */

return [
    // 独立表
    'tables' => [
        'notify_logs' => "CREATE TABLE IF NOT EXISTS `{prefix}notify_logs` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type`       VARCHAR(30)  NOT NULL DEFAULT '' COMMENT '通知类型(submitted/feedback/approved/rejected/test)',
            `recipient`  VARCHAR(200) NOT NULL DEFAULT '' COMMENT '收件人邮箱',
            `subject`    VARCHAR(300) NOT NULL DEFAULT '' COMMENT '邮件主题',
            `body`       TEXT         NULL COMMENT '邮件正文(截断存储)',
            `status`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0=失败 1=成功',
            `error`      VARCHAR(500) NULL DEFAULT NULL COMMENT '失败原因',
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_type` (`type`),
            KEY `idx_status` (`status`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],

    // 向已有表添加字段
    'columns' => [
        'sites' => [
            'submit_email' => "VARCHAR(100) NOT NULL DEFAULT '' COMMENT '提交者邮箱（审核结果通知用）'"
        ],
    ],

    // 默认配置项
    'config' => [
        'plugin_notify_enabled'           => '0',
        // SMTP 配置
        'plugin_notify_smtp_host'         => '',
        'plugin_notify_smtp_port'         => '465',
        'plugin_notify_smtp_user'         => '',
        'plugin_notify_smtp_pass'         => '',
        'plugin_notify_smtp_secure'       => 'ssl',  // ssl / tls / none
        'plugin_notify_from_email'        => '',
        'plugin_notify_from_name'         => '懒人导航',
        // 收件人（管理员邮箱，多个用英文逗号分隔）
        'plugin_notify_recipient'         => '',
        // 通知开关
        'plugin_notify_on_submit'         => '1',
        'plugin_notify_on_feedback'       => '1',
        'plugin_notify_on_approve'        => '1',
        'plugin_notify_on_reject'         => '1',
    ],
];
