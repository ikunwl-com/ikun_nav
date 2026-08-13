<?php
/**
 * 后台提交审核
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'review';

$siteModel = new SiteModel();
$catModel = new CategoryModel();

$msg = $_GET['msg'] ?? '';

// ========== 操作处理（仅 POST，禁止 GET 执行写操作） ==========
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost) {
    // CSRF 校验
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        redirect('/admin/review.php?msg=csrf');
    }

    $action = $_POST['action'] ?? '';
    $batchAction = $_POST['batch_action'] ?? '';
    $id = Security::int($_POST['id'] ?? 0);

    // 单条操作
    if ($action && $id > 0) {
        switch ($action) {
            case 'approve':
                Database::execute("UPDATE " . table('sites') . " SET status='published' WHERE id=?", [$id]);
                redirect('/admin/review.php?msg=approved');
                break;
            case 'reject':
                Database::execute("UPDATE " . table('sites') . " SET status='rejected' WHERE id=?", [$id]);
                redirect('/admin/review.php?msg=rejected');
                break;
            case 'delete':
                $siteModel->delete($id);
                redirect('/admin/review.php?msg=deleted');
                break;
            case 'edit_approve':
                handleReviewEdit($catModel, $id);
                redirect('/admin/review.php?msg=approved');
                break;
        }
    }

    // 批量操作（checkbox name="ids[]" 直接传递数组）
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids) && $batchAction) {
        $idList = array_filter(array_map('intval', (array)$ids), function($v) { return $v > 0; });
        if (!empty($idList)) {
            $placeholders = implode(',', array_fill(0, count($idList), '?'));
            switch ($batchAction) {
                case 'approve':
                    Database::execute("UPDATE " . table('sites') . " SET status='published' WHERE id IN ($placeholders)", $idList);
                    break;
                case 'reject':
                    Database::execute("UPDATE " . table('sites') . " SET status='rejected' WHERE id IN ($placeholders)", $idList);
                    break;
                case 'delete':
                    $siteModel = new SiteModel();
                    foreach ($idList as $delId) {
                        $siteModel->delete($delId);
                    }
                    break;
            }
        }
        redirect('/admin/review.php?msg=batch');
    }
}

// 获取待审核站点
$page = max(1, Security::int($_GET['page'] ?? 1));
$perPage = 15;
$total = (int)Database::scalar("SELECT COUNT(*) FROM " . table('sites') . " WHERE status='pending'");
$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sites = Database::query(
    "SELECT s.*, c.name as cat_name FROM " . table('sites') . " s
     LEFT JOIN " . table('categories') . " c ON s.category_id=c.id
     WHERE s.status='pending'
     ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset");

$categories = $catModel->getAll();
$csrfToken = $_SESSION['csrf_token'] ?? '';

adminHeader('提交审核');
if ($msg) { adminAlert(getReviewMsg($msg), in_array($msg, ['error','csrf']) ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">待审核站点 (<?= (int)$total ?>)</span>
  </div>

  <?php if (empty($sites)): ?>
  <div class="empty-state"><div class="icon"><i class="ti ti-clipboard-check"></i></div><p>暂无待审核站点</p></div>
  <?php else: ?>

  <form method="POST" onsubmit="return confirm('确定执行批量操作？')">
    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">

    <table class="data-table">
      <thead>
        <tr>
          <th width="30"><input type="checkbox" onclick="var el=this;document.querySelectorAll('.row-check').forEach(function(c){c.checked=el.checked})"></th>
          <th>站点名称</th>
          <th>URL</th>
          <th>分类</th>
          <th>权重</th>
          <th>提交时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sites as $s):
          $tags = parseTags($s['tags'] ?? '[]');
        ?>
        <tr>
          <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$s['id'] ?>"></td>
          <td>
            <div class="fw-600"><?= Security::e($s['name']) ?></div>
            <?php if (!empty($s['description'])): ?>
            <div class="text-sm-subtitle"><?= Security::e(mb_substr($s['description'], 0, 60)) ?><?= mb_strlen($s['description']) > 60 ? '...' : '' ?></div>
            <?php endif; ?>
            <?php if (!empty($tags)): ?>
            <div class="flex-mt-gap-26cc">
              <?php foreach (array_slice($tags, 0, 3) as $t): ?>
              <span class="badge badge-secondary"><?= Security::e($t) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= Security::safeUrl(normalizeSiteUrl($s['url'])) ?>" target="_blank" rel="noopener" class="text-a4cf">
              <?= Security::e(getDisplayDomain($s['url'])) ?> <i class="ti ti-external-link text-bcf8" ></i>
            </a>
          </td>
          <td><?= Security::e($s['cat_name'] ?? '-') ?></td>
          <td>
            <div class="flex-gap-4-wrap">
              <span class="badge badge-info">PC<?= (int)$s['br_pc'] ?></span>
              <span class="badge badge-info">M<?= (int)$s['br_mobile'] ?></span>
            </div>
          </td>
          <td class="text-sm-dim"><?= Security::e(formatDate($s['created_at'])) ?></td>
          <td class="actions">
            <form method="POST" style="display:inline;" onsubmit="return confirm('确定通过？')">
              <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success" title="通过"><i class="ti ti-check"></i></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('确定拒绝？')">
              <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-warning" title="拒绝"><i class="ti ti-x"></i></button>
            </form>
            <a href="/admin/sites.php?action=edit&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-secondary" title="编辑"><i class="ti ti-edit"></i></a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除？')">
              <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="删除"><i class="ti ti-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="batch-actions">
      <button type="submit" name="batch_action" value="approve" class="btn btn-success"><i class="ti ti-checks"></i> 批量通过</button>
      <button type="submit" name="batch_action" value="reject" class="btn btn-warning"><i class="ti ti-x"></i> 批量拒绝</button>
      <button type="submit" name="batch_action" value="delete" class="btn btn-danger"><i class="ti ti-trash"></i> 批量删除</button>
    </div>
  </form>

  <!-- 分页 -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i == $page): ?>
      <span class="current"><?= $i ?></span>
      <?php else: ?>
      <a href="/admin/review.php?page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php
adminFooter();

function getReviewMsg($key) {
    $msgs = [
        'approved' => '审核通过，站点已发布',
        'rejected' => '已拒绝该站点',
        'deleted' => '站点已删除',
        'batch' => '批量操作完成',
        'csrf' => 'CSRF验证失败，请重试',
    ];
    return $msgs[$key] ?? '';
}

function handleReviewEdit($catModel, $id) {
    $name = Security::cleanString($_POST['name'] ?? '', 100);
    $url = Security::cleanString($_POST['url'] ?? '', 500);
    $categoryId = Security::int($_POST['category_id'] ?? 0);
    $description = Security::cleanString($_POST['description'] ?? '', 200);
    $tags = Security::cleanTags($_POST['tags'] ?? '');

    if ($name && $url && $categoryId > 0) {
        $url = normalizeSiteUrl($url);
        $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);
        Database::execute(
            "UPDATE " . table('sites') . " SET name=?, url=?, category_id=?, description=?, tags=?, status='published' WHERE id=?",
            [$name, $url, $categoryId, $description, $tagsJson, $id]
        );
    }
}
