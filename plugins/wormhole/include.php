<?php
/**
 * 虫洞联盟插件 - 主文件
 * 提供侧边栏入口、联盟统计显示
 *
 * 配置项：
 *   plugin_wormhole_rate_limit - 虫洞联盟自动加入频率限制（次/小时，默认1）
 *
 * 前台钩子：
 *   sidebar_bottom  - 侧边栏底部显示虫洞入口（含联盟站点数量）
 * 后台钩子：
 *   admin_sidebar   - 在后台侧边栏注入"虫洞联盟"管理导航
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 钩子注册：仅在插件启用时注册 ==========
if (Plugin::isEnabled('wormhole')) {
    // 后台侧边栏钩子：注入虫洞联盟管理入口
    Plugin::registerHook('admin_sidebar', function () {
        $cls = ($GLOBALS['currentPage'] ?? '') === 'wormhole' ? 'active' : '';
        echo '<a href="/admin/plugin.php?p=wormhole" class="nav-item ' . $cls . '"><i class="ti ti-world-question"></i><span>虫洞联盟</span></a>';
    });

    // 前台侧边栏钩子
    Plugin::registerHook('sidebar_bottom', 'plugin_wormhole_sidebar', 10);
}

/**
 * 侧边栏虫洞入口
 */
function plugin_wormhole_sidebar(): void
{
    $wormholeModel = new WormholeModel();
    $stats = $wormholeModel->getStats();
    $memberCount = (int)($stats['total_count'] ?? 0);

    // 虫洞入口 URL
    // 虫洞联盟固定路径 /wormhole/（支持伪静态/服务器重写，兼容动态模式）
    $wormholeUrl = '/wormhole/';
    ?>
    <!-- 虫洞联盟入口 -->
    <div class="wormhole-entry">
      <a href="<?= Theme::eAttr($wormholeUrl) ?>" class="sidebar-item wormhole-link" title="点击探索全联盟随机站点">
        <i class="ti ti-world"></i>
        <span>🌀 虫洞联盟</span>
        <span class="wormhole-tag"><?= $memberCount > 0 ? $memberCount . '站' : '传送' ?></span>
      </a>
    </div>
    <?php
}
