<?php
/**
 * 分类业务逻辑层
 */

class CategoryModel
{
    /**
     * 获取全部分类（按排序，含站点计数）
     */
    public function getAll(): array
    {
        $tblCat = Database::table('categories');
        $tblSite = Database::table('sites');
        return Database::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$tblSite} s WHERE s.category_id = c.id AND s.status = 'published') AS site_count
             FROM {$tblCat} c
             ORDER BY c.sort_order ASC, c.id ASC"
        );
    }

    /**
     * 获取侧边栏显示的分类（含站点计数）
     */
    public function getSidebarCategories(): array
    {
        $tblCat = Database::table('categories');
        $tblSite = Database::table('sites');

        $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM {$tblSite} s WHERE s.category_id = c.id AND s.status = 'published') AS site_count
                FROM {$tblCat} c
                WHERE c.is_show = 1
                ORDER BY c.sort_order ASC, c.id ASC";
        return Database::query($sql);
    }

    /**
     * 按 slug 获取分类
     */
    public function getBySlug(string $slug): ?array
    {
        $tbl = Database::table('categories');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE slug = ?", [$slug]);
    }

    /**
     * 按 ID 获取分类
     */
    public function getById(int $id): ?array
    {
        $tbl = Database::table('categories');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE id = ?", [$id]);
    }

    /**
     * 按 ID 获取分类名称
     */
    public function getNameById(int $id): string
    {
        $tbl = Database::table('categories');
        $row = Database::queryOne("SELECT name FROM {$tbl} WHERE id = ?", [$id]);
        return $row ? $row['name'] : '';
    }

    /**
     * 创建分类
     */
    public function create(array $data): int
    {
        $tbl = Database::table('categories');
        $sql = "INSERT INTO {$tbl} (name, slug, icon, sort_order, show_count, is_show, seo_title, seo_desc, fill_sort)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return Database::insert($sql, [
            $data['name'],
            $data['slug'],
            $data['icon'] ?? 'category',
            $data['sort_order'] ?? 0,
            $data['show_count'] ?? 12,
            $data['is_show'] ?? 1,
            $data['seo_title'] ?? '',
            $data['seo_desc'] ?? '',
            $data['fill_sort'] ?? 'newest',
        ]);
    }

    /**
     * 更新分类
     */
    public function update(int $id, array $data): int
    {
        $tbl = Database::table('categories');
        $fields = [];
        $params = [];

        $allowed = ['name', 'slug', 'icon', 'sort_order', 'show_count', 'is_show', 'seo_title', 'seo_desc', 'fill_sort'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return 0;

        $params[] = $id;
        return Database::execute("UPDATE {$tbl} SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    }

    /**
     * 删除分类
     */
    public function delete(int $id): int
    {
        $tbl = Database::table('categories');
        return Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]);
    }

    /**
     * 获取分类总数
     */
    public function count(): int
    {
        $tbl = Database::table('categories');
        return (int)Database::scalar("SELECT COUNT(*) FROM {$tbl}");
    }

    /**
     * 智能匹配分类：根据文本内容匹配最合适的分类
     * @return int|null 匹配到的分类ID，无匹配返回null
     */
    public function matchCategoryByKeywords(string $text): ?int
    {
        $text = strtolower($text);
        $cats = $this->getAll();

        $keywordMap = [
            'music'     => ['音乐', '歌', '曲', 'playlist', 'mp3', 'soundcloud', 'spotify', 'netease', '网易云'],
            'video'     => ['视频', '电影', '剧集', 'tv', 'movie', 'film', 'youtube', 'bilibili', '爱奇艺', '优酷', '腾讯视频', '奈飞', 'netflix'],
            'novel'     => ['小说', '阅读', '书', 'book', '起点', '晋江', '番茄小说', 'qidian'],
            'comic'     => ['漫画', 'comic', 'manhua', '快看'],
            'anime'     => ['动漫', '动画', 'anime', '番剧', 'bangumi', '新番', 'bilibili动画', 'acfun'],
            'wallpaper' => ['壁纸', 'wallpaper', 'background', '桌面', '高清', '4k壁纸'],
            'game'      => ['游戏', 'game', 'gaming', 'steam', 'taptap', '4399', 'gamer', '攻略'],
            'tools'     => ['工具', 'tools', 'utility', 'converter', '计算器', '翻译', '在线工具'],
            'ai'        => ['ai', '人工智能', 'chatgpt', 'gpt', 'claude', '文心', '通义', 'gemini', 'llm', '大模型'],
            'work'      => ['办公', '工作', 'ppt', '模板', '简历', '设计', '素材', '图库', 'figma'],
            'fun'       => ['娱乐', 'fun', '搞笑', '段子', 'meme', 'gif', '表情包', '解压', '摸鱼'],
        ];

        $bestId = null;
        $bestScore = 0;

        foreach ($cats as $cat) {
            $score = 0;
            $catName = strtolower($cat['name'] ?? '');
            $catSlug = strtolower($cat['slug'] ?? '');

            if (strpos($text, $catName) !== false) $score += 10;
            if (strpos($text, $catSlug) !== false) $score += 8;

            foreach ($keywordMap as $key => $words) {
                if (strpos($catName, $key) !== false || strpos($catSlug, $key) !== false) {
                    foreach ($words as $word) {
                        if (strpos($text, strtolower($word)) !== false) {
                            $score += 5;
                        }
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int)$cat['id'];
            }
        }

        return $bestScore >= 5 ? $bestId : null;
    }
}
