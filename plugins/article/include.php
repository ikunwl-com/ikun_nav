<?php
/**
 * 文章发布插件
 * 开启后前台显示"发文章"入口，后台管理文章列表，前台渲染文章页
 *
 * 数据库表：{prefix}articles
 * 配置项：
 *   plugin_article_per_page - 每页显示文章数（默认10）
 *   plugin_article_enable_submit - 是否允许前台投稿（默认0）
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 文章模型
 */
class ArticleModel
{
    /**
     * 获取文章列表（分页）
     */
    public function getList(int $page = 1, int $perPage = 10, string $status = 'published'): array
    {
        $tbl = Database::table('articles');
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, title, slug, excerpt, author, category, tags, status, views, created_at, updated_at
                FROM {$tbl}";
        $params = [];
        if ($status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        return Database::query($sql, $params);
    }

    /**
     * 获取单篇文章（按 ID）
     */
    public function getById(int $id): ?array
    {
        $tbl = Database::table('articles');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE id = ?", [$id]);
    }

    /**
     * 获取单篇文章（按 slug）
     */
    public function getBySlug(string $slug): ?array
    {
        $tbl = Database::table('articles');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE slug = ? AND status = 'published'", [$slug]);
    }

    /**
     * 统计文章数
     */
    public function count(string $status = 'published'): int
    {
        $tbl = Database::table('articles');
        $sql = "SELECT COUNT(*) as cnt FROM {$tbl}";
        $params = [];
        if ($status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $row = Database::queryOne($sql, $params);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * 创建文章
     */
    public function create(array $data): int
    {
        $tbl = Database::table('articles');
        $slug = $data['slug'] ?? '';
        if (empty($slug)) {
            $slug = $this->generateSlug($data['title'] ?? 'article');
        }
        $sql = "INSERT INTO {$tbl} (title, slug, content, excerpt, author, category, tags, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        return Database::insert($sql, [
            Security::cleanString($data['title'] ?? '', 200),
            Security::cleanString($slug, 200),
            $data['content'] ?? '',
            Security::cleanString($data['excerpt'] ?? '', 500),
            Security::cleanString($data['author'] ?? '', 100),
            Security::cleanString($data['category'] ?? '', 100),
            Security::cleanString($data['tags'] ?? '', 500),
            Security::enum($data['status'] ?? 'draft', ['published', 'draft', 'pending'], 'draft'),
        ]);
    }

    /**
     * 更新文章
     */
    public function update(int $id, array $data): bool
    {
        $tbl = Database::table('articles');
        $sets = [];
        $params = [];
        $fields = ['title', 'slug', 'content', 'excerpt', 'author', 'category', 'tags', 'status'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $sets[] = "{$field} = ?";
                if ($field === 'content') {
                    $params[] = $data[$field];
                } elseif ($field === 'status') {
                    $params[] = Security::enum($data[$field], ['published', 'draft', 'pending'], 'draft');
                } else {
                    $params[] = Security::cleanString($data[$field], $field === 'content' ? 0 : 500);
                }
            }
        }
        if (empty($sets)) {
            return false;
        }
        $sets[] = "updated_at = NOW()";
        $params[] = $id;
        return Database::execute("UPDATE {$tbl} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }

    /**
     * 删除文章
     */
    public function delete(int $id): bool
    {
        $tbl = Database::table('articles');
        return Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]) > 0;
    }

    /**
     * 增加浏览量
     */
    public function incrementViews(int $id): void
    {
        $tbl = Database::table('articles');
        Database::execute("UPDATE {$tbl} SET views = views + 1 WHERE id = ?", [$id]);
    }

    /**
     * 生成 slug
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $title));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        if (empty($slug)) {
            $slug = 'article-' . time();
        }
        // 确保唯一
        $tbl = Database::table('articles');
        $base = $slug;
        $i = 1;
        while (Database::queryOne("SELECT id FROM {$tbl} WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}

// ========== 钩子注册：仅在插件启用时注册 ==========
if (Plugin::isEnabled('article')) {
    // 后台侧边栏钩子：注入文章管理入口
    Plugin::registerHook('admin_sidebar', function () {
        $cls = ($GLOBALS['currentPage'] ?? '') === 'article' ? 'active' : '';
        echo '<a href="/admin/plugin.php?p=article" class="nav-item ' . $cls . '"><i class="ti ti-file-text"></i><span>文章管理</span></a>';
    });

    // 前台侧边栏钩子：显示文章入口（与分类导航风格一致，class可配置）
    Plugin::registerHook('sidebar_bottom', function () {
        $articleUrl = Rewrite::url('article_list');
        $articleModel = new ArticleModel();
        $articleCount = $articleModel->count('published');
        $btnClass = Plugin::config('article', 'btn_class', 'sidebar-item');
        echo '<a href="' . Theme::eAttr($articleUrl) . '" class="' . Security::eAttr($btnClass) . '">';
        echo '<i class="ti ti-article"></i>';
        echo '<span>文章专栏</span>';
        echo '<span class="count">' . $articleCount . '</span>';
        echo '</a>';
    });

    // 前台注入自定义 CSS
    Plugin::registerHook('before_footer', function () {
        $customCss = Plugin::config('article', 'custom_css', '');
        if (!empty($customCss)) {
            echo '<style>' . "\n";
            echo '/* 文章插件自定义 CSS */' . "\n";
            echo $customCss . "\n";
            echo '</style>' . "\n";
        }
    });
}
