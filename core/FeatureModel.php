<?php
/**
 * 推荐管理业务逻辑层
 */

class FeatureModel
{
    /**
     * 获取某分类的推荐站点列表
     */
    public function getCategoryFeatured(int $categoryId): array
    {
        $tblSites = Database::table('sites');
        $tblFeat = Database::table('site_features');

        $sql = "SELECT s.*, sf.feature_order, sf.is_excluded, sf.id AS feature_id
                FROM {$tblFeat} sf
                INNER JOIN {$tblSites} s ON s.id = sf.site_id
                WHERE sf.category_id = ?
                ORDER BY sf.feature_order ASC, s.id ASC";
        return Database::query($sql, [$categoryId]);
    }

    /**
     * 添加推荐
     */
    public function add(int $siteId, int $categoryId): int
    {
        $tbl = Database::table('site_features');

        // 获取当前最大排序值
        $maxOrder = (int)Database::scalar(
            "SELECT COALESCE(MAX(feature_order), -1) FROM {$tbl} WHERE category_id = ?",
            [$categoryId]
        );

        return Database::insert(
            "INSERT INTO {$tbl} (site_id, category_id, feature_order) VALUES (?, ?, ?)",
            [$siteId, $categoryId, $maxOrder + 1]
        );
    }

    /**
     * 移除推荐
     */
    public function remove(int $siteId, int $categoryId): int
    {
        $tbl = Database::table('site_features');
        return Database::execute(
            "DELETE FROM {$tbl} WHERE site_id = ? AND category_id = ?",
            [$siteId, $categoryId]
        );
    }

    /**
     * 更新推荐排序
     */
    public function updateOrder(int $featureId, int $order): int
    {
        $tbl = Database::table('site_features');
        return Database::execute(
            "UPDATE {$tbl} SET feature_order = ? WHERE id = ?",
            [$order, $featureId]
        );
    }

    /**
     * 置顶推荐（设为最小排序值）
     */
    public function pinToTop(int $siteId, int $categoryId): void
    {
        $tbl = Database::table('site_features');

        // 获取当前最小排序值
        $minOrder = (int)Database::scalar(
            "SELECT COALESCE(MIN(feature_order), 0) FROM {$tbl} WHERE category_id = ?",
            [$categoryId]
        );

        Database::execute(
            "UPDATE {$tbl} SET feature_order = ? WHERE site_id = ? AND category_id = ?",
            [$minOrder - 1, $siteId, $categoryId]
        );
    }

    /**
     * 获取全局推荐池（所有推荐站点聚合）
     */
    public function getGlobalPool(): array
    {
        $tblSites = Database::table('sites');
        $tblFeat = Database::table('site_features');
        $tblCat = Database::table('categories');

        $sql = "SELECT s.*, sf.feature_order, c.name AS category_name, c.slug AS category_slug
                FROM {$tblFeat} sf
                INNER JOIN {$tblSites} s ON s.id = sf.site_id
                INNER JOIN {$tblCat} c ON c.id = sf.category_id
                ORDER BY (s.br_pc + s.br_mobile) DESC";
        return Database::query($sql);
    }

    /**
     * 设置站点全局推荐标记
     */
    public function setGlobalFeatured(int $siteId, bool $featured): int
    {
        $tbl = Database::table('sites');
        return Database::execute(
            "UPDATE {$tbl} SET is_featured = ? WHERE id = ?",
            [$featured ? 1 : 0, $siteId]
        );
    }
}
