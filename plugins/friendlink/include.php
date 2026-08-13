<?php
/**
 * 友情链接插件 - 主文件
 *
 * 功能：
 *   1. 前台底部展示友链（before_footer 钩子，输出 HTML）
 *   2. 前台底部注入弹窗 CSS+JS（after_footer 钩子）
 *   3. 后台侧边栏注入「友情链接」管理入口
 *
 * 配置项：
 *   plugin_friendlink_title       - 友链区块标题（默认"友情链接"）
 *   plugin_friendlink_target      - 链接打开方式（_blank / _self）
 *   plugin_friendlink_max_display - 前台最多显示数量（默认50）
 *
 * 数据库表：{prefix}friendlinks
 *
 * 钩子：
 *   before_footer  - 前台底部友链区块渲染
 *   after_footer   - 前台弹窗样式与脚本注入
 *   admin_sidebar  - 后台侧边栏管理导航
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 友情链接模型
 */
class FriendLinkModel
{
    /**
     * 获取已启用的友链列表（前台展示用）
     * @param int $limit 最大返回数量
     * @return array
     */
    public function getActiveLinks(int $limit = 50): array
    {
        $tbl = Database::table('friendlinks');
        $limit = max(1, $limit);
        return Database::query(
            "SELECT id, name, url, css_class, icon, sort_order FROM {$tbl}
             WHERE status = 1
             ORDER BY sort_order ASC, id ASC
             LIMIT {$limit}"
        );
    }

    /**
     * 获取全部友链（后台管理用）
     */
    public function getAllLinks(): array
    {
        $tbl = Database::table('friendlinks');
        return Database::query(
            "SELECT id, name, url, css_class, icon, sort_order, status, created_at
             FROM {$tbl}
             ORDER BY sort_order ASC, id ASC"
        );
    }

    /**
     * 获取单条友链
     */
    public function getById(int $id): ?array
    {
        $tbl = Database::table('friendlinks');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE id = ?", [$id]);
    }

    /**
     * 创建友链
     */
    public function create(array $data): int
    {
        $tbl = Database::table('friendlinks');
        return Database::insert(
            "INSERT INTO {$tbl} (name, url, css_class, icon, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['name'] ?? '',
                $data['url'] ?? '',
                $data['css_class'] ?? '',
                $data['icon'] ?? '',
                (int)($data['sort_order'] ?? 0),
                isset($data['status']) ? (int)$data['status'] : 1,
            ]
        );
    }

    /**
     * 更新友链
     */
    public function update(int $id, array $data): bool
    {
        $tbl = Database::table('friendlinks');
        return Database::execute(
            "UPDATE {$tbl} SET name = ?, url = ?, css_class = ?, icon = ?, sort_order = ?, status = ?
             WHERE id = ?",
            [
                $data['name'] ?? '',
                $data['url'] ?? '',
                $data['css_class'] ?? '',
                $data['icon'] ?? '',
                (int)($data['sort_order'] ?? 0),
                isset($data['status']) ? (int)$data['status'] : 1,
                $id,
            ]
        ) > 0;
    }

    /**
     * 删除友链
     */
    public function delete(int $id): bool
    {
        $tbl = Database::table('friendlinks');
        return Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]) > 0;
    }

    /**
     * 统计数量
     */
    public function count(): int
    {
        $tbl = Database::table('friendlinks');
        $row = Database::queryOne("SELECT COUNT(*) as cnt FROM {$tbl}");
        return (int)($row['cnt'] ?? 0);
    }
}

// ========== 钩子注册：仅在插件启用时注册 ==========
if (Plugin::isEnabled('friendlink')) {

    // 前台底部：渲染友链区块
    Plugin::registerHook('before_footer', function () {
        $model = new FriendLinkModel();
        $maxDisplay = (int)Plugin::config('friendlink', 'max_display', '50');
        $links = $model->getActiveLinks($maxDisplay);

        if (empty($links)) {
            return;
        }

        $title = Plugin::config('friendlink', 'title', '友情链接');
        $target = Plugin::config('friendlink', 'target', '_blank');
        ?>
        <!-- 友情链接区块 -->
        <div class="friendlink-section">
            <div class="friendlink-title"><?= Security::e($title) ?></div>
            <div class="friendlink-list">
                <?php foreach ($links as $link): 
                    // 构建 class 属性：填写了才输出
                    $cssClass = trim($link['css_class'] ?? '');
                    $classAttr = $cssClass !== '' ? ' class="' . Security::eAttr($cssClass) . '"' : '';

                    // 构建 icon：填写了才显示
                    $icon = trim($link['icon'] ?? '');
                    $iconHtml = '';
                    if ($icon !== '') {
                        // 判断是 URL（http/https/相对路径）还是 Tabler 图标类名
                    if (preg_match('#^(https?:)?/#i', $icon) || preg_match('/\.(png|jpg|jpeg|gif|svg|webp|ico)$/i', $icon)) {
                            // 图片 URL
                            $iconHtml = '<img src="' . Security::eAttr($icon) . '" alt="' . Security::eAttr($link['name']) . '" class="friendlink-icon">';
                        } else {
                            // Tabler 图标类名（如 ti ti-home）
                            $iconCls = strpos($icon, 'ti') === 0 ? $icon : 'ti ' . $icon;
                            $iconHtml = '<i class="' . Security::eAttr($iconCls) . '"></i>';
                        }
                    }
                ?>
                <a href="<?= Security::eAttr($link['url']) ?>" target="<?= Security::eAttr($target) ?>" rel="nofollow noopener"<?= $classAttr ?>>
                    <?= $iconHtml ?><?= Security::e($link['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }, 10);

    // 前台底部：注入友链样式
    Plugin::registerHook('after_footer', function () {
        ?>
        <style>
        .friendlink-section {
            max-width: 1200px;
            margin: 0 auto 16px;
            padding: 12px 20px;
            text-align: center;
        }
        .friendlink-section .friendlink-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .friendlink-section .friendlink-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            justify-content: center;
            align-items: center;
        }
        .friendlink-section .friendlink-list a {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            color: #6b7280;
            text-decoration: none;
            transition: color .2s;
        }
        .friendlink-section .friendlink-list a:hover {
            color: #3b82f6;
        }
        .friendlink-section .friendlink-list .friendlink-icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }
        .friendlink-section .friendlink-list i[class^="ti"],
        .friendlink-section .friendlink-list i[class*=" ti"] {
            font-size: 15px;
        }
        </style>
        <?php
    }, 10);

    // 后台侧边栏钩子：注入友情链接管理入口
    Plugin::registerHook('admin_sidebar', function () {
        $cls = ($GLOBALS['currentPage'] ?? '') === 'friendlink' ? 'active' : '';
        echo '<a href="/admin/plugin.php?p=friendlink" class="nav-item ' . $cls . '">'
           . '<i class="ti ti-link"></i><span>友情链接</span></a>';
    });
}
