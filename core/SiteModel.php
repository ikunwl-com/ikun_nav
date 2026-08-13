<?php
/**
 * 站点业务逻辑层
 * 处理站点 CRUD、分类内容渲染、推荐管理等核心业务
 */

class SiteModel
{
    /**
     * 获取全局推荐站点（首页热门推荐）
     * 从 site_features 表读取推荐，按权重降序取前 N 条
     */
    public function getGlobalFeatured(int $limit = 6): array
    {
        $tblSites = Database::table('sites');
        $tblFeat  = Database::table('site_features');
        $tblCats  = Database::table('categories');

        $sql = "SELECT s.*, c.slug AS category_slug
                FROM {$tblFeat} sf
                INNER JOIN {$tblSites} s ON s.id = sf.site_id
                LEFT JOIN {$tblCats} c ON s.category_id = c.id
                WHERE s.status = 'published'
                  AND sf.is_excluded = 0
                ORDER BY sf.feature_order ASC, (s.br_pc + s.br_mobile) DESC
                LIMIT ?";
        return Database::query($sql, [$limit]);
    }

    /**
     * 获取分类下展示站点（推荐优先 + 不足补位 + 超过截断）
     * @param int $categoryId 分类 ID
     * @param int $limit 展示上限
     * @param string $fillSort 补位排序方式：newest/views/br
     * @return array 站点列表
     */
    public function getCategorySites(int $categoryId, int $limit = 12, string $fillSort = 'newest'): array
    {
        $tblSites = Database::table('sites');
        $tblFeat  = Database::table('site_features');

        // 1. 获取该分类的推荐站点
        $sql = "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
                INNER JOIN {$tblFeat} sf ON s.id = sf.site_id
                LEFT JOIN " . Database::table('categories') . " c ON s.category_id = c.id
                WHERE sf.category_id = ? AND s.status = 'published'
                ORDER BY sf.feature_order ASC";
        $featured = Database::query($sql, [$categoryId]);

        // 2. 不足补位
        $needed = $limit - count($featured);
        if ($needed > 0) {
            $featuredIds = array_column($featured, 'id');
            $placeholders = $featuredIds ? implode(',', array_fill(0, count($featuredIds), '?')) : '0';

            switch ($fillSort) {
                case 'views': $orderColumn = 'views'; break;
                case 'br':    $orderColumn = '(br_pc + br_mobile)'; break;
                default:      $orderColumn = 'created_at'; break;
            }

            $sql = "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
                    LEFT JOIN " . Database::table('categories') . " c ON s.category_id = c.id
                    WHERE s.category_id = ? AND s.status = 'published'
                    AND s.id NOT IN ({$placeholders})
                    ORDER BY {$orderColumn} DESC
                    LIMIT ?";

            $params = array_merge([$categoryId], $featuredIds, [$needed]);
            $fill = Database::query($sql, $params);
            $featured = array_merge($featured, $fill);
        }

        // 3. 超过截断
        return array_slice($featured, 0, $limit);
    }

    /**
     * 获取分类下展示站点（纯排序，不按推荐置顶）
     */
    public function getSitesByCategory(int $categoryId, int $page = 1, int $perPage = 24, string $sort = 'newest'): array
    {
        $tbl = Database::table('sites');
        $offset = ($page - 1) * $perPage;

        // 根据 sort 参数确定排序字段
        switch ($sort) {
            case 'br':
                $orderColumn = 's.is_featured DESC, (COALESCE(s.br_pc, 0) + COALESCE(s.br_mobile, 0)) DESC, s.created_at DESC';
                break;
            case 'views':
                $orderColumn = 's.views DESC, s.created_at DESC';
                break;
            case 'clicks':
                $orderColumn = 's.clicks DESC, s.created_at DESC';
                break;
            case 'time':
                $orderColumn = 's.created_at DESC';
                break;
            case 'newest':
            default:
                $orderColumn = 's.created_at DESC';
                $sort = 'newest';
                break;
        }

        $sql = "SELECT s.*, c.slug AS category_slug FROM {$tbl} s
                LEFT JOIN " . Database::table('categories') . " c ON s.category_id = c.id
                WHERE s.category_id = ? AND s.status = 'published'
                ORDER BY {$orderColumn}
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        return Database::query($sql, [$categoryId]);
    }

    /**
     * 获取站点总数（按分类）
     */
    public function countByCategory(int $categoryId): int
    {
        $tbl = Database::table('sites');
        return (int)Database::scalar(
            "SELECT COUNT(*) FROM {$tbl} WHERE category_id = ? AND status = 'published'",
            [$categoryId]
        );
    }

    /**
     * 获取所有已发布站点（分页）
     */
    public function getAllPublished(int $page = 1, int $perPage = 20, string $sort = 'created_at'): array
    {
        $tbl = Database::table('sites');
        $offset = ($page - 1) * $perPage;

        switch ($sort) {
            case 'views':  $orderColumn = 'views DESC'; break;
            case 'clicks': $orderColumn = 'clicks DESC'; break;
            case 'name':   $orderColumn = 'name ASC'; break;
            default:       $orderColumn = 'created_at DESC'; break;
        }

        $limit  = (int)$perPage;
        $offset = (int)$offset;
        $sql = "SELECT * FROM {$tbl}
                WHERE status = 'published'
                ORDER BY {$orderColumn}
                LIMIT {$limit} OFFSET {$offset}";
        return Database::query($sql, []);
    }

    /**
     * 获取所有已发布且未加入虫洞的站点
     */
    public function getAllPublishedNotInWormhole(): array
    {
        $tblSites = Database::table('sites');
        $tblCats  = Database::table('categories');
        $sql = "SELECT s.id, s.name, s.url, s.br_pc, s.br_mobile, s.br_360, s.br_shenma, c.name AS category_name
                FROM {$tblSites} s
                LEFT JOIN {$tblCats} c ON s.category_id = c.id
                WHERE s.status = 'published'
                AND s.wormhole_status = 'none'
                ORDER BY s.created_at DESC";
        return Database::query($sql);
    }

    /**
     * 增加浏览量
     */
    /**
     * 增加浏览量（点入：用户浏览详情页）
     * 同步更新 views（兼容旧统计）、clicks_in（排行榜用）和日统计表
     */
    public function incrementViews(int $id): void
    {
        $tbl = Database::table('sites');
        Database::execute("UPDATE {$tbl} SET views = COALESCE(views, 0) + 1, clicks_in = COALESCE(clicks_in, 0) + 1 WHERE id = ?", [$id]);
        // 同时写入日统计表
        $this->recordDailyStats($id, 'views');
    }

    /**
     * 增加点击量（点出：用户点击"访问网站"按钮跳转到目标站）
     * 同步更新 clicks（兼容旧统计）、clicks_out（排行榜用）和日统计表
     */
    public function incrementClicks(int $id): void
    {
        $tbl = Database::table('sites');
        Database::execute("UPDATE {$tbl} SET clicks = COALESCE(clicks, 0) + 1, clicks_out = COALESCE(clicks_out, 0) + 1 WHERE id = ?", [$id]);
        // 同时写入日统计表
        $this->recordDailyStats($id, 'clicks');
    }

    /**
     * 记录站点日统计（浏览量/点击量）
     * 使用 INSERT ... ON DUPLICATE KEY UPDATE 实现按天累加
     * @param int $siteId 站点ID
     * @param string $type 统计类型：views | clicks
     * @param int $count 增加数量（默认1）
     */
    public function recordDailyStats(int $siteId, string $type, int $count = 1): void
    {
        $tbl = Database::table('site_daily_stats');
        $today = date('Y-m-d');

        if ($type === 'views') {
            $sql = "INSERT INTO {$tbl} (site_id, stat_date, views, clicks)
                    VALUES (?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE views = views + ?";
        } else {
            $sql = "INSERT INTO {$tbl} (site_id, stat_date, views, clicks)
                    VALUES (?, ?, 0, ?)
                    ON DUPLICATE KEY UPDATE clicks = clicks + ?";
        }

        Database::execute($sql, [$siteId, $today, $count, $count]);

        // 每天只清理一次（用文件标记记录上次清理日期，避免每次写入都执行 DELETE）
        $this->cleanupDailyStatsIfNeeded(7);
    }

    /**
     * 每天只执行一次清理（基于文件标记，避免高频 DELETE）
     * 保留 7 天数据用于详情页趋势表
     */
    private function cleanupDailyStatsIfNeeded(int $keepDays = 7): void
    {
        $flagFile = sys_get_temp_dir() . '/nav_stats_cleanup_' . md5(__DIR__) . '.txt';
        $today = date('Y-m-d');
        $lastCleanup = @file_get_contents($flagFile);
        if ($lastCleanup === $today) {
            return; // 今天已经清理过
        }
        $this->cleanupDailyStats($keepDays);
        @file_put_contents($flagFile, $today, LOCK_EX);
    }

    /**
     * 清理过期的日统计数据
     * @param int $keepDays 保留天数（默认90天）
     */
    public function cleanupDailyStats(int $keepDays = 90): void
    {
        $tbl = Database::table('site_daily_stats');
        $cutoff = date('Y-m-d', strtotime("-{$keepDays} days"));
        Database::execute("DELETE FROM {$tbl} WHERE stat_date < ?", [$cutoff]);
    }

    /**
     * 获取站点最近 N 天的日统计数据（用于趋势图）
     * @param int $siteId 站点ID
     * @param int $days 天数（默认7天）
     * @return array 按日期升序排列的数组 [['date'=>'08-01','views'=>5,'clicks'=>2], ...]
     */
    public function getDailyStats(int $siteId, int $days = 7): array
    {
        $tbl = Database::table('site_daily_stats');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // 查询最近 N 天的真实数据
        $rows = Database::query(
            "SELECT stat_date, views, clicks FROM {$tbl}
             WHERE site_id = ? AND stat_date >= ?
             ORDER BY stat_date ASC",
            [$siteId, $startDate]
        );

        // 构建日期映射
        $dataMap = [];
        foreach ($rows as $row) {
            $dateKey = date('n-j', strtotime($row['stat_date']));
            $dataMap[$dateKey] = [
                'views'  => (int)$row['views'],
                'clicks' => (int)$row['clicks'],
            ];
        }

        // 补全缺失日期（返回0）
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('n-j', strtotime("-{$i} days"));
            $result[] = [
                'date'   => $d,
                'views'  => $dataMap[$d]['views'] ?? 0,
                'clicks' => $dataMap[$d]['clicks'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * 批量增加浏览量（用于首页/分类页展示统计，避免 N+1）
     * 同步更新 views 和 clicks_in
     */
    public function incrementViewsBatch(array $ids): void
    {
        $ids = array_unique(array_map('intval', $ids));
        $ids = array_filter($ids, function($v){ return $v > 0; });
        if (empty($ids)) return;

        $tbl = Database::table('sites');
        // 使用 CASE WHEN 批量更新 views 和 clicks_in，避免 N+1
        $viewCases = [];
        $inCases   = [];
        foreach ($ids as $id) {
            $viewCases[] = "WHEN {$id} THEN COALESCE(views, 0) + 1";
            $inCases[]   = "WHEN {$id} THEN COALESCE(clicks_in, 0) + 1";
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$tbl} SET views = CASE id " . implode(' ', $viewCases) . " ELSE views END, clicks_in = CASE id " . implode(' ', $inCases) . " ELSE clicks_in END WHERE id IN ({$placeholders})";
        Database::execute($sql, array_values($ids));
    }

    /**
     * 搜索站点（前台）
     * 优先使用 FULLTEXT 索引（MATCH AGAINST），不可用时回退到 LIKE
     * 支持多字段匹配：name、description、url、tags
     * @param string $keyword 关键词
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @return array 站点列表
     */
    public function searchPaged(string $keyword, int $page = 1, int $perPage = 12): array
    {
        $tblSites = Database::table('sites');
        $tblCats  = Database::table('categories');
        $offset   = ($page - 1) * $perPage;
        $limit    = (int)$perPage;
        $offset   = (int)$offset;

        // 尝试 FULLTEXT 搜索
        if (self::supportsFulltext()) {
            try {
                $sql = "SELECT s.*, c.slug AS category_slug,
                            MATCH(s.name, s.description, s.url, s.tags) AGAINST (? IN BOOLEAN MODE) AS relevance_score
                        FROM {$tblSites} s
                        LEFT JOIN {$tblCats} c ON s.category_id = c.id
                        WHERE s.status = 'published'
                          AND MATCH(s.name, s.description, s.url, s.tags) AGAINST (? IN BOOLEAN MODE)
                        ORDER BY relevance_score DESC, s.created_at DESC
                        LIMIT {$limit} OFFSET {$offset}";
                // BOOLEAN MODE 关键词需要处理：空格转 +，保留短语
                $ftKeyword = $this->prepareFulltextKeyword($keyword);
                return Database::query($sql, [$ftKeyword, $ftKeyword]);
            } catch (PDOException $e) {
                // FULLTEXT 不可用，标记并回退到 LIKE
                self::$fulltextSupported = false;
                Logger::log('search_fallback', "[搜索回退LIKE] FULLTEXT不可用: {$e->getMessage()}");
            }
        }

        // LIKE 回退方案
        $kw = '%' . $keyword . '%';
        $sql = "SELECT s.*, c.slug AS category_slug,
                    (CASE
                        WHEN s.name LIKE ? THEN 6
                        WHEN s.tags LIKE ? THEN 3
                        WHEN s.description LIKE ? THEN 2
                        WHEN s.url LIKE ? THEN 1
                        ELSE 0
                    END) AS relevance_score
                FROM {$tblSites} s
                LEFT JOIN {$tblCats} c ON s.category_id = c.id
                WHERE s.status = 'published'
                  AND (s.name LIKE ? OR s.description LIKE ?
                       OR s.url LIKE ? OR s.tags LIKE ?)
                ORDER BY relevance_score DESC, s.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $params = array_fill(0, 8, $kw);
        return Database::query($sql, $params);
    }

    /**
     * 搜索站点总数（前台）
     * 优先 FULLTEXT，回退 LIKE
     */
    public function searchCount(string $keyword): int
    {
        $tbl = Database::table('sites');

        if (self::supportsFulltext()) {
            try {
                $ftKeyword = $this->prepareFulltextKeyword($keyword);
                return (int)Database::scalar(
                    "SELECT COUNT(*) FROM {$tbl}
                     WHERE status = 'published'
                       AND MATCH(name, description, url, tags) AGAINST (? IN BOOLEAN MODE)",
                    [$ftKeyword]
                );
            } catch (PDOException $e) {
                self::$fulltextSupported = false;
            }
        }

        $kw  = '%' . $keyword . '%';
        return (int)Database::scalar(
            "SELECT COUNT(*) FROM {$tbl}
             WHERE status = 'published'
               AND (name LIKE ? OR description LIKE ?
                    OR url LIKE ? OR tags LIKE ?)",
            array_fill(0, 4, $kw)
        );
    }

    /**
     * 检测是否支持 FULLTEXT 索引（静态缓存检测结果）
     */
    private static ?bool $fulltextSupported = null;

    private static function supportsFulltext(): bool
    {
        if (self::$fulltextSupported !== null) {
            return self::$fulltextSupported;
        }
        // 尝试一条轻量查询检测 ft_search 索引是否存在
        try {
            $tbl = Database::table('sites');
            $row = Database::queryOne(
                "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = 'ft_search'",
                [$tbl]
            );
            self::$fulltextSupported = !empty($row['cnt']);
        } catch (Throwable $e) {
            self::$fulltextSupported = false;
        }
        return self::$fulltextSupported;
    }

    /**
     * 准备 FULLTEXT BOOLEAN MODE 关键词
     * - 空格分隔的词转为 +word（必须包含）
     * - 保留引号短语
     */
    private function prepareFulltextKeyword(string $keyword): string
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '';
        }
        // 如果包含引号，视为短语，直接返回
        if (strpos($keyword, '"') !== false) {
            return $keyword;
        }
        // 空格分隔的词，每个词前加 +
        $words = preg_split('/\s+/u', $keyword);
        $words = array_filter($words, function($w) { return $w !== ''; });
        return implode(' ', array_map(function($w) {
            // 避免特殊字符导致语法错误
            $w = preg_replace('/[+\-<>~*()"]/', '', $w);
            return $w ? '+' . $w . '*' : '';
        }, $words));
    }

    /**
     * 获取同分类推荐（站点详情页底部）
     */
    public function getRelatedSites(int $categoryId, int $excludeId, int $limit = 3): array
    {
        $tblSites = Database::table('sites');
        $tblCats = Database::table('categories');
        $sql = "SELECT s.*, c.slug AS category_slug
                FROM {$tblSites} s
                LEFT JOIN {$tblCats} c ON s.category_id = c.id
                WHERE s.category_id = ? AND s.status = 'published' AND s.id != ?
                ORDER BY (s.br_pc + s.br_mobile) DESC
                LIMIT ?";
        return Database::query($sql, [$categoryId, $excludeId, $limit]);
    }

    /**
     * 获取单个站点详情
     */
    public function getSite(int $id): ?array
    {
        $tblSites = Database::table('sites');
        $tblCats  = Database::table('categories');
        $sql = "SELECT s.*, c.slug AS category_alias, c.name AS category_name
                FROM {$tblSites} s
                LEFT JOIN {$tblCats} c ON s.category_id = c.id
                WHERE s.id = ? LIMIT 1";
        $rows = Database::query($sql, [$id]);
        return $rows[0] ?? null;
    }

    /**
     * 获取全部站点（后台管理）
     */
    public function getAllSites(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $tbl = Database::table('sites');
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = 'category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(name LIKE ? OR url LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if (isset($filters['featured'])) {
            $where[] = 'is_featured = ?';
            $params[] = $filters['featured'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $limit  = (int)$perPage;
        $offset = (int)$offset;
        $sql = "SELECT * FROM {$tbl} {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

        return Database::query($sql, $params);
    }

    /**
     * 统计站点总数（后台）
     */
    public function countAll(array $filters = []): int
    {
        $tbl = Database::table('sites');
        $where = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = 'category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(name LIKE ? OR url LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return (int)Database::scalar("SELECT COUNT(*) FROM {$tbl} {$whereClause}", $params);
    }

    /**
     * 创建站点
     * 优先复用已删除的空缺 ID，没有空缺时再自增
     */
    public function create(array $data): int
    {
        $tbl = Database::table('sites');
        $tblDel = Database::table('deleted_ids');

        // 1) 优先从回收队列取最小的空缺 ID
        $gapId = (int)Database::scalar(
            "SELECT id FROM {$tblDel} ORDER BY id ASC LIMIT 1"
        );

        if ($gapId > 0) {
            // 从队列移除，复用这个 ID
            Database::execute("DELETE FROM {$tblDel} WHERE id = ?", [$gapId]);
            $sql = "INSERT INTO {$tbl}
                    (id, name, url, category_id, description,
                     br_pc, br_mobile, br_360, br_shenma, tags,
                     is_featured, sort_order, status, submit_ip)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            Database::execute($sql, [
                $gapId,
                $data['name'],
                $data['url'],
                $data['category_id'],
                $data['description'] ?? '',
                $data['br_pc'] ?? 0,
                $data['br_mobile'] ?? 0,
                $data['br_360'] ?? 0,
                $data['br_shenma'] ?? 0,
                $data['tags'] ?? '[]',
                $data['is_featured'] ?? 0,
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'pending',
                $data['submit_ip'] ?? '',
            ]);
            self::clearRankingCache();
            return $gapId;
        }

        // 2) 没有可复用的空缺 ID，正常自增插入
        $sql = "INSERT INTO {$tbl}
                (name, url, category_id, description,
                 br_pc, br_mobile, br_360, br_shenma, tags,
                 is_featured, sort_order, status, submit_ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $newId = Database::insert($sql, [
            $data['name'],
            $data['url'],
            $data['category_id'],
            $data['description'] ?? '',
            $data['br_pc'] ?? 0,
            $data['br_mobile'] ?? 0,
            $data['br_360'] ?? 0,
            $data['br_shenma'] ?? 0,
            $data['tags'] ?? '[]',
            $data['is_featured'] ?? 0,
            $data['sort_order'] ?? 0,
            $data['status'] ?? 'pending',
            $data['submit_ip'] ?? '',
        ]);
        self::clearRankingCache();
        return $newId;
    }

    /**
     * 更新站点
     */
    public function update(int $id, array $data): int
    {
        $tbl = Database::table('sites');
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'url', 'category_id', 'description',
            'br_pc', 'br_mobile', 'br_360', 'br_shenma', 'tags',
            'is_featured', 'sort_order', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return 0;
        }

        $params[] = $id;
        $setClause = implode(', ', $fields);
        $result = Database::execute("UPDATE {$tbl} SET {$setClause} WHERE id = ?", $params);
        self::clearRankingCache();
        return $result;
    }

    /**
     * 删除站点（并记录空缺ID到回收队列）
     */
    public function delete(int $id): int
    {
        $tbl = Database::table('sites');
        $tblFeat = Database::table('site_features');
        $tblDel = Database::table('deleted_ids');

        // 先删除关联数据
        Database::execute("DELETE FROM {$tblFeat} WHERE site_id = ?", [$id]);
        
        // 记录空缺 ID 到回收队列（忽略重复）
        Database::execute("INSERT IGNORE INTO {$tblDel} (id) VALUES (?)", [$id]);

        // 删除站点
        $result = Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]);
        self::clearRankingCache();
        return $result;
    }

    /**
     * 获取待审核站点
     */
    public function getPendingSites(int $limit = 20): array
    {
        $tbl = Database::table('sites');
        return Database::query(
            "SELECT * FROM {$tbl} WHERE status = 'pending' ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    /**
     * 获取统计数据（合并为单条查询，减少数据库往返）
     */
    public function getStats(): array
    {
        $tbl = Database::table('sites');
        $tblFeat = Database::table('site_features');

        // 单条查询获取总数、已发布、待审核、总浏览、总点击
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    COALESCE(SUM(views), 0) AS total_views,
                    COALESCE(SUM(clicks), 0) AS total_clicks
                FROM {$tbl}";
        $row = Database::queryOne($sql) ?? [];

        // 推荐数单独查询（涉及 JOIN）
        $featured = (int)Database::scalar(
            "SELECT COUNT(*) FROM {$tblFeat} sf INNER JOIN {$tbl} s ON s.id = sf.site_id WHERE s.status = 'published'"
        );

        return [
            'total'        => (int)($row['total'] ?? 0),
            'published'    => (int)($row['published'] ?? 0),
            'pending'      => (int)($row['pending'] ?? 0),
            'total_views'  => (int)($row['total_views'] ?? 0),
            'total_clicks' => (int)($row['total_clicks'] ?? 0),
            'featured'     => $featured,
        ];
    }

    /**
     * 获取点击排行
     */
    public function getTopClicked(int $limit = 10): array
    {
        $tbl = Database::table('sites');
        return Database::query(
            "SELECT * FROM {$tbl} WHERE status = 'published' ORDER BY clicks DESC LIMIT ?",
            [$limit]
        );
    }

    // ==================== 评分系统 ====================

    /**
     * 提交评分（1-5星）
     * 同一IP对同一站点只能评分一次
     * @return array ['success'=>bool, 'message'=>string, 'avg'=>float, 'count'=>int]
     */
    public function submitRating(int $siteId, int $rating, string $ip): array
    {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => '评分需在 1~5 星之间'];
        }

        $tbl = Database::table('site_ratings');
        $siteTbl = Database::table('sites');

        // 检查站点是否存在且已发布
        $exists = Database::scalar(
            "SELECT COUNT(*) FROM {$siteTbl} WHERE id = ? AND status = 'published'",
            [$siteId]
        );
        if (!$exists) {
            return ['success' => false, 'message' => '站点不存在或未上线'];
        }

        // 同一IP只能评一次
        $already = Database::scalar(
            "SELECT COUNT(*) FROM {$tbl} WHERE site_id = ? AND ip = ?",
            [$siteId, $ip]
        );
        if ($already) {
            return ['success' => false, 'message' => '您已评分过该站点'];
        }

        Database::execute(
            "INSERT INTO {$tbl} (site_id, rating, ip) VALUES (?, ?, ?)",
            [$siteId, $rating, $ip]
        );

        $stats = $this->getRatingStats($siteId);
        return array_merge(['success' => true, 'message' => '评分成功'], $stats);
    }

    /**
     * 获取站点评分统计
     * @return array ['avg'=>float, 'count'=>int]
     */
    public function getRatingStats(int $siteId): array
    {
        $tbl = Database::table('site_ratings');
        $avg = Database::scalar(
            "SELECT COALESCE(AVG(rating), 0) FROM {$tbl} WHERE site_id = ?",
            [$siteId]
        );
        $count = Database::scalar(
            "SELECT COUNT(*) FROM {$tbl} WHERE site_id = ?",
            [$siteId]
        );
        return ['avg' => round((float)$avg, 1), 'count' => (int)$count];
    }

    // ==================== 反馈系统 ====================

    /**
     * 提交站点反馈
     * @param int $siteId 站点ID
     * @param string $type 反馈类型：url_change/broken/error/other
     * @param string $content 反馈内容
     * @param string $email 提交者邮箱（可选）
     * @param string $ip 提交者IP
     * @return bool
     */
    public function submitFeedback(int $siteId, string $type, string $content, string $email, string $ip): bool
    {
        $tbl = Database::table('site_feedback');
        $validTypes = ['url_change', 'broken', 'error', 'other'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'other';
        }
        if (empty($content)) {
            return false;
        }

        return Database::execute(
            "INSERT INTO {$tbl} (site_id, type, content, email, ip) VALUES (?, ?, ?, ?, ?)",
            [$siteId, $type, $content, $email, $ip]
        ) > 0;
    }

    /**
     * 获取站点的反馈列表
     * @return array 反馈列表
     */
    public function getFeedbackBySite(int $siteId, int $limit = 10): array
    {
        $tbl = Database::table('site_feedback');
        return Database::query(
            "SELECT * FROM {$tbl} WHERE site_id = ? ORDER BY created_at DESC LIMIT ?",
            [$siteId, $limit]
        );
    }

    /**
     * 获取全部待处理反馈（后台用）
     */
    public function getPendingFeedback(int $page = 1, int $perPage = 20): array
    {
        $tbl = Database::table('site_feedback');
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;
        $limit   = (int)$perPage;
        $offset  = (int)$offset;
        return Database::query(
            "SELECT f.*, s.name AS site_name FROM {$tbl} f
             LEFT JOIN " . Database::table('sites') . " s ON f.site_id = s.id
             WHERE f.status = 'pending'
             ORDER BY f.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            []
        );
    }

    /**
     * 获取首页排行榜数据（带文件缓存，TTL 5分钟）
     * 后台增删改站点时调用 clearRankingCache() 主动清缓存
     * @param int $limit 每个榜单显示数量
     * @return array ['newest'=>[], 'hottest'=>[], 'mostClicksOut'=>[], 'mostClicksIn'=>[]]
     */
    public function getRanking(int $limit = 15): array
    {
        $cacheFile = sys_get_temp_dir() . '/nav_ranking_' . $limit . '_' . md5(__DIR__) . '.json';
        $ttl = 300; // 5分钟软过期

        // 尝试读取缓存
        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content) {
                $cached = json_decode($content, true);
                if ($cached && isset($cached['_expire']) && $cached['_expire'] > time()) {
                    unset($cached['_expire']);
                    return $cached;
                }
            }
        }

        // 缓存未命中，查询数据库
        $tblSites = Database::table('sites');
        $tblCats  = Database::table('categories');

        $newest = Database::query(
            "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
             LEFT JOIN {$tblCats} c ON s.category_id = c.id
             WHERE s.status = 'published'
             ORDER BY s.created_at DESC LIMIT ?",
            [$limit]
        );

        $hottest = Database::query(
            "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
             LEFT JOIN {$tblCats} c ON s.category_id = c.id
             WHERE s.status = 'published'
             ORDER BY s.views DESC, s.created_at DESC LIMIT ?",
            [$limit]
        );

        $mostClicksOut = Database::query(
            "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
             LEFT JOIN {$tblCats} c ON s.category_id = c.id
             WHERE s.status = 'published'
             ORDER BY s.clicks_out DESC, s.created_at DESC LIMIT ?",
            [$limit]
        );

        $mostClicksIn = Database::query(
            "SELECT s.*, c.slug AS category_slug FROM {$tblSites} s
             LEFT JOIN {$tblCats} c ON s.category_id = c.id
             WHERE s.status = 'published'
             ORDER BY s.clicks_in DESC, s.created_at DESC LIMIT ?",
            [$limit]
        );

        $result = compact('newest', 'hottest', 'mostClicksOut', 'mostClicksIn');

        // 写入缓存
        $cacheData = $result;
        $cacheData['_expire'] = time() + $ttl;
        @file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $result;
    }

    /**
     * 清除排行榜缓存（站点增删改时调用）
     */
    public static function clearRankingCache(): void
    {
        $pattern = sys_get_temp_dir() . '/nav_ranking_*_*.json';
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
    }
}
