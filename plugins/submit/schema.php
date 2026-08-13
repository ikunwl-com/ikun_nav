<?php
/**
 * 提交网站收录插件 - 数据库声明
 * 启用插件时写入默认提交配置
 */

return [
    'tables'  => [],
    'columns' => [],

    // 默认配置项
    'config' => [
        'plugin_submit_enable_submit'     => '1',
        'plugin_submit_need_review'       => '1',
        'plugin_submit_show_weight'       => '1',
        'plugin_submit_default_category'  => '0',
        'plugin_submit_require_category'  => '1',
        'plugin_submit_category_ids'      => '',   // 逗号分隔的收录分类ID列表
        'plugin_submit_rate_limit'        => '5',
        'plugin_submit_tdk_rate_limit'    => '10',
        'plugin_submit_rules'             => '',
    ],
];
