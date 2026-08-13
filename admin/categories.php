<?php
/**
 * 后台分类管理
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'categories';

$catModel = new CategoryModel();

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// ========== POST 操作 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/categories.php?msg=csrf');
    }

    $postAction = $_POST['action'] ?? '';

    switch ($postAction) {
        case 'save':
            $id = Security::int($_POST['id'] ?? 0);
            $name = Security::cleanString($_POST['name'] ?? '', 50);
            $slug = Security::validateSlug($_POST['slug'] ?? '');
            $icon = Security::cleanString($_POST['icon'] ?? '', 50);
            $description = Security::cleanString($_POST['description'] ?? '', 200);
            $sortOrder = Security::int($_POST['sort_order'] ?? 0);
            $status = Security::enum($_POST['status'] ?? 'active', ['active', 'hidden']);

            if (empty($name) || empty($slug)) {
                redirect('/admin/categories.php?action=edit&id=' . $id . '&msg=required');
            }

            // 检查slug唯一性
            $existing = $catModel->getBySlug($slug);
            if ($existing && (int)$existing['id'] !== $id) {
                redirect('/admin/categories.php?action=edit&id=' . $id . '&msg=slug_exists');
            }

            // 图标只允许 ti-xxx 格式
            if ($icon && !preg_match('/^[a-z0-9\-]+$/', $icon)) {
                $icon = 'world';
            }

            // 转换为数据库字段名
            $data = [
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon ?: 'world',
                'seo_desc' => $description,
                'sort_order' => $sortOrder,
                'is_show' => $status === 'active' ? 1 : 0,
            ];

            $adminId = $_SESSION['admin_id'] ?? '未知';
            $ip = Security::getClientIP();
            if ($id > 0) {
                $catModel->update($id, $data);
                Logger::log('admin_category', "编辑分类 admin_id={$adminId} IP={$ip} cat_id={$id} name={$name} slug={$slug}");
                redirect('/admin/categories.php?msg=updated');
            } else {
                $newId = $catModel->create($data);
                Logger::log('admin_category', "创建分类 admin_id={$adminId} IP={$ip} cat_id={$newId} name={$name} slug={$slug}");
                redirect('/admin/categories.php?msg=created');
            }
            break;

        case 'delete':
            $id = Security::int($_POST['id'] ?? 0);
            if ($id > 0) {
                $adminId = $_SESSION['admin_id'] ?? '未知';
                $ip = Security::getClientIP();
                // 检查是否有关联站点
                $count = (int)Database::scalar(
                    "SELECT COUNT(*) FROM " . table('sites') . " WHERE category_id = ?",
                    [$id]
                );
                if ($count > 0) {
                    Logger::log('admin_category', "删除分类失败 admin_id={$adminId} IP={$ip} cat_id={$id} 原因=分类下还有{$count}个站点");
                    redirect('/admin/categories.php?msg=has_sites');
                }
                $catModel->delete($id);
                Logger::log('admin_category', "删除分类 admin_id={$adminId} IP={$ip} cat_id={$id}");
            }
            redirect('/admin/categories.php?msg=deleted');
            break;

        case 'sort':
            // 批量更新排序
            $adminId = $_SESSION['admin_id'] ?? '未知';
            $ip = Security::getClientIP();
            $orders = $_POST['sort_orders'] ?? [];
            // 安全：限制批量操作数量
            if (is_array($orders) && count($orders) > 200) {
                $orders = array_slice($orders, 0, 200, true);
            }
            $count = 0;
            foreach ($orders as $catId => $order) {
                $catId = (int)$catId;
                $order = (int)$order;
                if ($catId > 0) {
                    Database::execute(
                        "UPDATE " . table('categories') . " SET sort_order = ? WHERE id = ?",
                        [$order, $catId]
                    );
                    $count++;
                }
            }
            Logger::log('admin_category', "保存排序 admin_id={$adminId} IP={$ip} 更新了{$count}个分类");
            redirect('/admin/categories.php?msg=sorted');
            break;
    }
}

// ========== GET 页面 ==========
switch ($action) {
    case 'add':
    case 'edit':
        $id = Security::int($_GET['id'] ?? 0);
        $cat = $id > 0 ? $catModel->getById($id) : null;
        showEditForm($cat, $msg);
        break;
    default:
        showList($catModel, $msg);
        break;
}

function showList($catModel, $msg) {
    $categories = $catModel->getAll();
    adminHeader('分类管理');
    if ($msg) { adminAlert(getCatMsg($msg), in_array($msg, ['error','csrf','required','slug_exists','has_sites']) ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">分类列表 (<?= count($categories) ?>)</span>
    <a href="/admin/categories.php?action=add" class="btn btn-primary"><i class="ti ti-plus"></i> 添加分类</a>
  </div>

  <table class="data-table">
    <thead>
      <tr><th>排序</th><th>图标</th><th>名称</th><th>Slug</th><th>站点数</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php if (empty($categories)): ?>
      <tr><td colspan="7" class="text-center p-24 text-muted">暂无分类</td></tr>
      <?php else: foreach ($categories as $c): ?>
      <tr>
        <td><input type="number" value="<?= (int)$c['sort_order'] ?>" class="input-sort sort-input"  data-id="<?= (int)$c['id'] ?>"></td>
        <td><i class="ti ti-<?= Security::eAttr($c['icon']) ?> text-c58d" ></i></td>
        <td class="fw-600"><?= Security::e($c['name']) ?></td>
        <td><code><?= Security::e($c['slug']) ?></code></td>
        <td><span class="badge badge-info"><?= (int)($c['site_count'] ?? 0) ?></span></td>
        <td>
          <?php if ((int)$c['is_show'] === 1): ?>
          <span class="badge badge-success">显示</span>
          <?php else: ?>
          <span class="badge badge-secondary">隐藏</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="/admin/categories.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-secondary"><i class="ti ti-edit"></i></a>
          <button type="button" class="btn btn-sm btn-danger" onclick="deleteCat(<?= (int)$c['id'] ?>, '<?= Security::eAttr($c['name']) ?>')"><i class="ti ti-trash"></i></button>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if (!empty($categories)): ?>
  <div class="mt-16 text-right">
    <button type="button" class="btn btn-primary" onclick="saveSort()"><i class="ti ti-arrows-sort"></i> 保存排序</button>
  </div>
  <?php endif; ?>
</div>

<form id="deleteForm" method="POST" class="d-none">
  <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<form id="sortForm" method="POST" class="d-none">
  <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
  <input type="hidden" name="action" value="sort">
  <div id="sortInputs"></div>
</form>

<script>
function deleteCat(id, name) {
  if (confirm('确定删除分类「' + name + '」？')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}
function saveSort() {
  const inputs = document.querySelectorAll('.sort-input');
  const container = document.getElementById('sortInputs');
  container.innerHTML = '';
  inputs.forEach(input => {
    const id = input.dataset.id;
    const val = input.value;
    container.innerHTML += '<input type="hidden" name="sort_orders[' + id + ']" value="' + val + '">';
  });
  document.getElementById('sortForm').submit();
}
</script>

<?php
    adminFooter();
}

function showEditForm($cat, $msg) {
    $isEdit = $cat !== null;
    adminHeader($isEdit ? '编辑分类' : '添加分类');
    if ($msg) { adminAlert(getCatMsg($msg), in_array($msg, ['error','csrf','required','slug_exists']) ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $isEdit ? '编辑分类' : '添加分类' ?></span>
    <a href="/admin/categories.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> 返回列表</a>
  </div>

  <form method="POST" action="/admin/categories.php">
    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$cat['id'] : 0 ?>">

    <div class="form-row">
      <div class="form-group">
        <label>分类名称 <span class="text-danger">*</span></label>
        <input type="text" class="form-input" name="name" value="<?= Security::eAttr($cat['name'] ?? '') ?>" maxlength="50" required>
      </div>
      <div class="form-group">
        <label>Slug（URL标识） <span class="text-danger">*</span></label>
        <input type="text" class="form-input" name="slug" value="<?= Security::eAttr($cat['slug'] ?? '') ?>" maxlength="50" placeholder="如 ai、tools、video" required pattern="[a-z0-9\-]+">
        <div class="form-help">只能使用小写字母、数字和连字符</div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Tabler图标名</label>
        <input type="text" class="form-input" name="icon" value="<?= Security::eAttr($cat['icon'] ?? 'world') ?>" maxlength="50" placeholder="如 compass、world、video">
        <div class="form-help">访问 <a href="https://tabler.io/icons" target="_blank">tabler.io/icons</a> 查看图标</div>
      </div>
      <div class="form-group">
        <label>排序</label>
        <input type="number" class="form-input" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>" min="0">
        <div class="form-help">数字越小越靠前</div>
      </div>
    </div>

    <div class="form-group">
      <label>分类描述</label>
      <textarea class="form-textarea" name="description" maxlength="200"><?= Security::e($cat['seo_desc'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>状态</label>
      <select class="form-select" name="status">
        <option value="active" <?= (!$isEdit || (int)$cat['is_show'] === 1) ? 'selected' : '' ?>>显示</option>
        <option value="hidden" <?= ($isEdit && (int)$cat['is_show'] === 0) ? 'selected' : '' ?>>隐藏</option>
      </select>
    </div>

    <div class="d-flex justify-end gap-12">
      <a href="/admin/categories.php" class="btn btn-secondary">取消</a>
      <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存</button>
    </div>
  </form>
</div>

<?php
    adminFooter();
}

function getCatMsg($key) {
    $msgs = [
        'created' => '分类添加成功',
        'updated' => '分类更新成功',
        'deleted' => '分类已删除',
        'sorted' => '排序已保存',
        'csrf' => 'CSRF验证失败，请重试',
        'required' => '请填写必填字段',
        'slug_exists' => 'Slug已被使用，请更换',
        'has_sites' => '该分类下还有站点，无法删除',
        'error' => '操作失败',
    ];
    return $msgs[$key] ?? '';
}
