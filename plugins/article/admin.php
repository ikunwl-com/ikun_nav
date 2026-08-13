<?php
/**
 * 文章发布插件 - 后台管理页面
 * 默认显示文章列表，点击"发文章"进入发布/编辑表单
 *
 * 由 admin/plugin.php?p=article 分发进入此文件
 */

// 安全检查（防止直接浏览器访问此文件）
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

$articleModel = new ArticleModel();
$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugin.php?p=article&msg=csrf');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'title'   => $_POST['title'] ?? '',
                'slug'    => $_POST['slug'] ?? '',
                'content' => Security::cleanHtml($_POST['content'] ?? '', 0),
                'excerpt' => $_POST['excerpt'] ?? '',
                'author'  => $_POST['author'] ?? '',
                'category'=> $_POST['category'] ?? '',
                'tags'    => $_POST['tags'] ?? '',
                'status'  => $_POST['status'] ?? 'draft',
            ];

            if ($action === 'create') {
                $id = $articleModel->create($data);
                $msg = $id > 0 ? "文章已创建（ID={$id}）" : '创建失败';
                $msgType = $id > 0 ? 'success' : 'error';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $ok = $articleModel->update($id, $data);
                $msg = $ok ? '文章已更新' : '更新失败或无变化';
                $msgType = $ok ? 'success' : 'error';
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $ok = $articleModel->delete($id);
            $msg = $ok ? '文章已删除' : '删除失败';
            $msgType = $ok ? 'success' : 'error';
            break;
    }

    redirect('/admin/plugin.php?p=article&ok=' . urlencode($msg));
}

// ========== GET 消息显示 ==========
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'csrf') {
    $msg = 'CSRF验证失败';
    $msgType = 'error';
}

// ========== 视图模式判断 ==========
$viewAction = $_GET['action'] ?? '';

// 设置页面单独分发
if ($viewAction === 'settings') {
    $settingsFile = __DIR__ . '/settings.php';
    if (file_exists($settingsFile)) {
        require_once $settingsFile;
        return;
    }
}

$isFormView = in_array($viewAction, ['new', 'edit'], true);

// 获取编辑对象
$editArticle = null;
if ($viewAction === 'edit' && isset($_GET['id'])) {
    $editArticle = $articleModel->getById((int)$_GET['id']);
}

// ========== 列表分页数据（仅列表视图）==========
$page = 1;
$perPage = 10;
$total = 0;
$totalPages = 0;
$articles = [];

if (!$isFormView) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)Plugin::config('article', 'per_page', '10');
    $articles = $articleModel->getList($page, $perPage, 'all');
    $total = $articleModel->count('all');
    $totalPages = (int)ceil($total / max(1, $perPage));
}

if ($msg) { adminAlert($msg, $msgType); }
?>

<?php if ($isFormView): // ========== 发布/编辑表单视图 ========== ?>

<div class="card">
    <div class="card-header flex-between-center">
        <span class="card-title"><?= $editArticle ? '编辑文章' : '发布新文章' ?></span>
        <a href="/admin/plugin.php?p=article" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> 返回列表</a>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="<?= $editArticle ? 'update' : 'create' ?>">
        <?php if ($editArticle): ?>
        <input type="hidden" name="id" value="<?= (int)$editArticle['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>标题 <span style="color:#ef4444">*</span></label>
            <input type="text" class="form-input" name="title" value="<?= Security::eAttr($editArticle['title'] ?? '') ?>" required maxlength="200">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>URL别名（slug）</label>
                <input type="text" class="form-input" name="slug" value="<?= Security::eAttr($editArticle['slug'] ?? '') ?>" placeholder="留空自动生成">
            </div>
            <div class="form-group">
                <label>作者</label>
                <input type="text" class="form-input" name="author" value="<?= Security::eAttr($editArticle['author'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>分类</label>
                <?php
                $catModel = new CategoryModel();
                $navCategories = $catModel->getAll();
                // 只显示被设为文章分类的分类
                $articleCatIdsStr = Plugin::config('article', 'category_ids', '');
                $articleCatIds = [];
                if ($articleCatIdsStr) {
                    foreach (explode(',', $articleCatIdsStr) as $cid) {
                        $cid = (int)trim($cid);
                        if ($cid > 0) $articleCatIds[] = $cid;
                    }
                }
                $selectedCat = $editArticle['category'] ?? '';
                ?>
                <select class="form-input" name="category">
                    <option value="">请选择分类</option>
                    <?php foreach ($navCategories as $cat): ?>
                    <?php if (empty($articleCatIds) || in_array((int)$cat['id'], $articleCatIds)): ?>
                    <option value="<?= Security::eAttr($cat['name']) ?>" <?= $selectedCat === $cat['name'] ? 'selected' : '' ?>>
                        <?= Security::e($cat['name']) ?>
                    </option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">从已配置的文章分类中选择<?= empty($articleCatIds) ? '（尚未配置分类，请先到设置页勾选）' : '' ?></div>
            </div>
            <div class="form-group">
                <label>标签（逗号分隔）</label>
                <input type="text" class="form-input" name="tags" value="<?= Security::eAttr($editArticle['tags'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>摘要</label>
            <textarea class="form-textarea" name="excerpt" rows="2" maxlength="500"><?= Security::e($editArticle['excerpt'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>内容（支持 HTML）</label>
            <?php Plugin::hook('article_editor_before'); ?>
            <textarea id="article-content" class="form-textarea" name="content" rows="15" style="font-family:monospace;" required><?= Security::e($editArticle['content'] ?? '') ?></textarea>
            <?php Plugin::hook('article_editor_after'); ?>
        </div>

        <div class="form-group">
            <label>状态</label>
            <select class="form-input" name="status">
                <option value="draft"     <?= ($editArticle['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>草稿</option>
                <option value="published" <?= ($editArticle['status'] ?? '') === 'published' ? 'selected' : '' ?>>发布</option>
                <option value="pending"   <?= ($editArticle['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>待审核</option>
            </select>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> <?= $editArticle ? '保存修改' : '发布文章' ?></button>
        </div>
    </form>
</div>

<?php else: // ========== 文章列表视图 ========== ?>

<div class="card">
    <div class="card-header flex-between-center">
        <span class="card-title">文章列表（共 <?= $total ?> 篇）</span>
        <div class="flex-center-gap-8">
            <a href="/admin/plugin.php?p=article&action=settings" class="btn btn-secondary"><i class="ti ti-settings"></i> 设置</a>
            <a href="/admin/plugin.php?p=article&action=new" class="btn btn-primary"><i class="ti ti-plus"></i> 发文章</a>
        </div>
    </div>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th style="padding:10px;border:1px solid #e9ecef;">ID</th>
                <th style="padding:10px;border:1px solid #e9ecef;">标题</th>
                <th style="padding:10px;border:1px solid #e9ecef;">状态</th>
                <th style="padding:10px;border:1px solid #e9ecef;">浏览</th>
                <th style="padding:10px;border:1px solid #e9ecef;">创建时间</th>
                <th style="padding:10px;border:1px solid #e9ecef;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($articles)): ?>
            <tr><td colspan="6" style="padding:20px;text-align:center;border:1px solid #e9ecef;color:#999;">暂无文章</td></tr>
            <?php else: foreach ($articles as $art): ?>
            <tr>
                <td style="padding:8px 10px;border:1px solid #e9ecef;"><?= (int)$art['id'] ?></td>
                <td style="padding:8px 10px;border:1px solid #e9ecef;">
                    <a href="/admin/plugin.php?p=article&action=edit&id=<?= (int)$art['id'] ?>"><?= Security::e($art['title']) ?></a>
                </td>
                <td style="padding:8px 10px;border:1px solid #e9ecef;">
                    <?php
                    $statusColors = ['published' => '#16a34a', 'draft' => '#d97706', 'pending' => '#2563eb'];
                    $statusNames  = ['published' => '已发布', 'draft' => '草稿', 'pending' => '待审核'];
                    $st = $art['status'] ?? 'draft';
                    ?>
                    <span style="color:<?= $statusColors[$st] ?? '#999' ?>;font-weight:600;"><?= $statusNames[$st] ?? $st ?></span>
                </td>
                <td style="padding:8px 10px;border:1px solid #e9ecef;"><?= (int)$art['views'] ?></td>
                <td style="padding:8px 10px;border:1px solid #e9ecef;"><?= formatDate($art['created_at']) ?></td>
                <td style="padding:8px 10px;border:1px solid #e9ecef;white-space:nowrap;">
                    <a href="/admin/plugin.php?p=article&action=edit&id=<?= (int)$art['id'] ?>" class="btn btn-sm"><i class="ti ti-edit"></i> 编辑</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除文章「<?= Security::eAttr($art['title']) ?>」？')">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="color:#ef4444;"><i class="ti ti-trash"></i> 删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="flex-center-gap-8" style="padding:12px;border-top:1px solid #e9ecef;">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">上一页</a>
        <?php endif; ?>
        <span style="color:#666;font-size:13px;">第 <?= $page ?> / <?= $totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
