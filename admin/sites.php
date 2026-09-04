<?php
/**
 * 后台站点管理
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'sites';

$siteModel = new SiteModel();
$catModel = new CategoryModel();

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// ========== POST 操作 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/sites.php?msg=csrf');
    }

    $postAction = $_POST['action'] ?? '';

    switch ($postAction) {
        case 'save':
            // 新增/编辑站点
            $id = Security::int($_POST['id'] ?? 0);
            $name = Security::cleanString($_POST['name'] ?? '', 100);
            $url = Security::cleanString($_POST['url'] ?? '', 500);
            $categoryId = Security::int($_POST['category_id'] ?? 0);
            $description = Security::cleanString($_POST['description'] ?? '', 200);
            $tags = Security::cleanTags($_POST['tags'] ?? '');
            $status = Security::enum($_POST['status'] ?? 'pending', ['pending', 'published', 'rejected', 'offline']);
            $brPc = max(0, min(10, Security::int($_POST['br_pc'] ?? 0)));
            $brMobile = max(0, min(10, Security::int($_POST['br_mobile'] ?? 0)));
            $br360 = max(0, min(10, Security::int($_POST['br_360'] ?? 0)));
            $brShenma = max(0, min(10, Security::int($_POST['br_shenma'] ?? 0)));

            // URL 格式校验
            [$urlValid, $cleanUrl, $urlDomain] = Security::validateUrl($url);
            if (!$urlValid) {
                redirect('/admin/sites.php?action=edit&id=' . $id . '&msg=invalid_url');
            }

            // URL 规范化
            $url = $cleanUrl;

            if (empty($name) || empty($url) || $categoryId <= 0) {
                redirect('/admin/sites.php?action=edit&id=' . $id . '&msg=required');
            }

            $data = [
                'name' => $name,
                'url' => $url,
                'category_id' => $categoryId,
                'description' => $description,
                'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'br_pc' => $brPc,
                'br_mobile' => $brMobile,
                'br_360' => $br360,
                'br_shenma' => $brShenma,
            ];

            $adminId = $_SESSION['admin_id'] ?? '未知';
            $ip = Security::getClientIP();
            if ($id > 0) {
                // 获取修改前的状态，用于检测 pending→published 的变更
                $oldSite = Database::queryOne("SELECT status, submit_email FROM " . table('sites') . " WHERE id = ?", [$id]);
                $siteModel->update($id, $data);
                // 如果状态从 pending 变为 published，触发审核通过通知
                if ($oldSite && isset($oldSite['status']) && $oldSite['status'] === 'pending' && $status === 'published') {
                    Plugin::hook('site_approved', [['id' => $id, 'submit_email' => isset($oldSite['submit_email']) ? $oldSite['submit_email'] : '']]);
                }
                Logger::log('admin_site', "编辑站点成功 admin_id={$adminId} IP={$ip} site_id={$id} name={$name} url={$url}");
                redirect('/admin/sites.php?msg=updated');
            } else {
                $newId = $siteModel->create($data);
                Logger::log('admin_site', "创建站点成功 admin_id={$adminId} IP={$ip} site_id={$newId} name={$name} url={$url}");
                redirect('/admin/sites.php?msg=created');
            }
            break;

        case 'delete':
            $id = Security::int($_POST['id'] ?? 0);
            if ($id > 0) {
                $adminId = $_SESSION['admin_id'] ?? '未知';
                $ip = Security::getClientIP();
                $siteModel->delete($id);
                Logger::log('admin_site', "删除站点 admin_id={$adminId} IP={$ip} site_id={$id}");
            }
            redirect('/admin/sites.php?msg=deleted');
            break;

        case 'batch':
            $ids = $_POST['ids'] ?? [];
            $batchAction = Security::enum($_POST['batch_action'] ?? '', ['publish', 'offline', 'delete', 'set_featured', 'unset_featured']);
            // 安全：限制批量操作数量，防止 DoS
            if (is_array($ids) && count($ids) > 500) {
                $ids = array_slice($ids, 0, 500);
            }
            if (!empty($ids) && $batchAction) {
                $idList = array_filter(array_map('intval', $ids), function($v) { return $v > 0; });
                if (!empty($idList)) {
                    $adminId = $_SESSION['admin_id'] ?? '未知';
                    $ip = Security::getClientIP();
                    $placeholders = implode(',', array_fill(0, count($idList), '?'));
                    $count = count($idList);
                    switch ($batchAction) {
                        case 'publish':
                            Database::execute("UPDATE " . table('sites') . " SET status='published' WHERE id IN ($placeholders)", $idList);
                            Logger::log('admin_site', "批量发布 admin_id={$adminId} IP={$ip} count={$count} ids=" . implode(',', $idList));
                            break;
                        case 'offline':
                            Database::execute("UPDATE " . table('sites') . " SET status='offline' WHERE id IN ($placeholders)", $idList);
                            Logger::log('admin_site', "批量下线 admin_id={$adminId} IP={$ip} count={$count} ids=" . implode(',', $idList));
                            break;
                        case 'set_featured':
                            Database::execute("UPDATE " . table('sites') . " SET is_featured=1 WHERE id IN ($placeholders)", $idList);
                            Logger::log('admin_site', "批量设为推荐 admin_id={$adminId} IP={$ip} count={$count} ids=" . implode(',', $idList));
                            break;
                        case 'unset_featured':
                            Database::execute("UPDATE " . table('sites') . " SET is_featured=0 WHERE id IN ($placeholders)", $idList);
                            Logger::log('admin_site', "批量取消推荐 admin_id={$adminId} IP={$ip} count={$count} ids=" . implode(',', $idList));
                            break;
                        case 'delete':
                            $siteModel = new SiteModel();
                            foreach ($idList as $delId) {
                                $siteModel->delete($delId);
                            }
                            Logger::log('admin_site', "批量删除 admin_id={$adminId} IP={$ip} count={$count} ids=" . implode(',', $idList));
                            break;
                    }
                }
            }
            redirect('/admin/sites.php?msg=batch');
            break;
    }
}

// ========== GET 操作 ==========
switch ($action) {
    case 'add':
    case 'edit':
        // 编辑/新增表单
        $id = Security::int($_GET['id'] ?? 0);
        $site = $id > 0 ? $siteModel->getSite($id) : null;
        $categories = $catModel->getAll();
        showEditForm($site, $categories, $catModel, $msg);
        break;

    default:
        // 列表页
        showList($siteModel, $catModel, $msg);
        break;
}

/**
 * 列表页
 */
function showList($siteModel, $catModel, $msg) {
    $page = max(1, Security::int($_GET['page'] ?? 1));
    $perPage = 20;
    $keyword = Security::cleanString($_GET['q'] ?? '', 100);
    $catFilter = Security::int($_GET['cat'] ?? 0);
    $statusFilter = Security::enum($_GET['status'] ?? '', ['pending', 'published', 'rejected', 'offline']) ?: '';

    $where = [];
    $params = [];

    if ($keyword) {
        $where[] = "(s.name LIKE ? OR s.url LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    if ($catFilter > 0) {
        $where[] = "s.category_id = ?";
        $params[] = $catFilter;
    }
    if ($statusFilter) {
        $where[] = "s.status = ?";
        $params[] = $statusFilter;
    }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $total = (int)Database::scalar("SELECT COUNT(*) FROM " . table('sites') . " s $whereClause", $params);
    $totalPages = max(1, ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $limit  = (int)$perPage;
    $offset = (int)$offset;

    $sites = Database::query(
        "SELECT s.*, c.name as cat_name FROM " . table('sites') . " s LEFT JOIN " . table('categories') . " c ON s.category_id=c.id $whereClause ORDER BY s.created_at DESC LIMIT {$limit} OFFSET {$offset}",
        $params
    );

    $categories = $catModel->getAll();

    adminHeader('站点管理');
    if ($msg) { adminAlert(getMsg($msg), $msg === 'error' || $msg === 'csrf' || $msg === 'invalid_url' ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">站点列表 (<?= $total ?>)</span>
    <a href="/admin/sites.php?action=add" class="btn btn-primary"><i class="ti ti-plus"></i> 添加站点</a>
  </div>

  <!-- 搜索栏 -->
  <form method="GET" class="search-bar">
    <input type="hidden" name="action" value="list">
    <input type="text" class="form-input" name="q" value="<?= Security::eAttr($keyword) ?>" placeholder="搜索站点名或URL...">
    <select class="form-select" name="cat">
      <option value="0">全部分类</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= Security::e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
      <option value="">全部状态</option>
      <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>已发布</option>
      <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>待审核</option>
      <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>已拒绝</option>
      <option value="offline" <?= $statusFilter === 'offline' ? 'selected' : '' ?>>已下线</option>
    </select>
    <button type="submit" class="btn btn-primary"><i class="ti ti-search"></i> 搜索</button>
  </form>

  <form method="POST" id="batchForm" onsubmit="return confirmBatch()">
    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="batch">
    <table class="data-table">
      <thead>
        <tr>
          <th width="30"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
          <th>站点名称</th>
          <th>分类</th>
          <th>权重</th>
          <th>浏览/点击</th>
          <th>状态</th>
          <th>提交时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($sites)): ?>
        <tr><td colspan="8" class="text-center p-24 text-muted">暂无数据</td></tr>
        <?php else: foreach ($sites as $s): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$s['id'] ?>" class="row-check"></td>
          <td>
            <div class="fw-600"><?= Security::e($s['name']) ?></div>
            <div class="text-sm-dim"><?= Security::e(getDisplayDomain($s['url'])) ?></div>
          </td>
          <td><?= Security::e($s['cat_name'] ?? '-') ?></td>
          <td><span class="badge badge-info">BR<?= (int)$s['br_pc'] ?>/<?= (int)$s['br_mobile'] ?></span></td>
          <td class="text-sm-dim-2"><?= formatNumber((int)$s['views']) ?> / <?= formatNumber((int)$s['clicks']) ?></td>
          <td>
            <?php
            $statusMap = ['published' => ['已发布','badge-success'], 'pending' => ['待审核','badge-warning'], 'rejected' => ['已拒绝','badge-danger'], 'offline' => ['已下线','badge-secondary']];
            $st = $statusMap[$s['status']] ?? [$s['status'],'badge-secondary'];
            ?>
            <span class="badge <?= $st[1] ?>"><?= $st[0] ?></span>
          </td>
          <td class="text-sm-dim"><?= Security::e(formatDate($s['created_at'])) ?></td>
          <td class="actions">
            <a href="/admin/sites.php?action=edit&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-secondary" title="编辑"><i class="ti ti-edit"></i></a>
            <a href="<?= Rewrite::url('site', ['id' => (int)$s['id']]) ?>" target="_blank" class="btn btn-sm btn-secondary" title="查看"><i class="ti ti-eye"></i></a>
            <button type="button" class="btn btn-sm btn-danger" onclick="deleteSite(<?= (int)$s['id'] ?>, '<?= Security::eAttr($s['name']) ?>')" title="删除"><i class="ti ti-trash"></i></button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <!-- 批量操作 -->
    <?php if (!empty($sites)): ?>
    <div class="batch-actions">
      <select name="batch_action" class="form-select w-auto" >
        <option value="">批量操作...</option>
        <option value="publish">发布</option>
        <option value="offline">下线</option>
        <option value="set_featured">设为推荐</option>
        <option value="unset_featured">取消推荐</option>
        <option value="delete">删除</option>
      </select>
      <button type="submit" class="btn btn-warning"><i class="ti ti-checks"></i> 应用</button>
    </div>
    <?php endif; ?>
  </form>

  <!-- 分页 -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $queryParams = array_filter(['q' => $keyword, 'cat' => $catFilter ?: null, 'status' => $statusFilter ?: null]);
    echo renderAdminPagination($page, $totalPages, '/admin/sites.php', $queryParams);
    ?>
  </div>
  <?php endif; ?>
</div>

<!-- 删除确认 -->
<form id="deleteForm" method="POST" class="d-none">
  <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<script>
function toggleAll(el) { document.querySelectorAll('.row-check').forEach(c => c.checked = el.checked); }
function confirmBatch() { return confirm('确定执行批量操作？'); }
function deleteSite(id, name) {
  if (confirm('确定删除站点「' + name + '」？此操作不可恢复！')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}
</script>

<?php
    adminFooter();
}

/**
 * 编辑/新增表单
 */
function showEditForm($site, $categories, $catModel, $msg) {
    $isEdit = $site !== null;
    adminHeader($isEdit ? '编辑站点' : '添加站点');
    if ($msg) { adminAlert(getMsg($msg), in_array($msg, ['error','csrf','invalid_url','required']) ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $isEdit ? '编辑站点' : '添加站点' ?></span>
    <a href="/admin/sites.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> 返回列表</a>
  </div>

  <form method="POST" action="/admin/sites.php">
    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$site['id'] : 0 ?>">

    <div class="form-row">
      <div class="form-group">
        <label>站点名称 <span class="text-danger">*</span></label>
        <input type="text" class="form-input" name="name" value="<?= Security::eAttr($site['name'] ?? '') ?>" maxlength="100" required>
      </div>
      <div class="form-group">
        <label>站点URL <span class="text-danger">*</span></label>
        <input type="text" class="form-input" name="url" value="<?= Security::eAttr($site['url'] ?? '') ?>" placeholder="https://example.com" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>分类 <span class="text-danger">*</span></label>
        <select class="form-select" name="category_id" required>
          <option value="">请选择分类</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($isEdit && (int)$site['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= Security::e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>状态</label>
        <select class="form-select" name="status">
          <?php
          // 后台新增站点默认「已发布」，不再默认进入待审核队列（编辑时保持原状态）
          $siteDefaultStatus = $isEdit ? ($site['status'] ?? 'pending') : 'published';
          foreach (['published'=>'已发布','pending'=>'待审核','rejected'=>'已拒绝','offline'=>'已下线'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $siteDefaultStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>标签（逗号分隔）</label>
      <input type="text" class="form-input" name="tags" value="<?= Security::eAttr(tagsToKeywords(parseTags($site['tags'] ?? '[]'))) ?>" placeholder="AI,工具,免费">
    </div>

    <div class="form-group">
      <label>站点简介</label>
      <textarea class="form-textarea" name="description" maxlength="200"><?= Security::e($site['description'] ?? '') ?></textarea>
    </div>

    <div class="bg-mb-p-ef52">
      <div class="flex-center-mb-bec8">
        <h3 class="text-5289"><i class="ti ti-chart-bar"></i> 权重信息 (0-10)</h3>
        <button type="button" id="btnUpdateMeta" class="btn btn-primary text-p-0e00" >
          <i class="ti ti-refresh"></i> 一键更新TDK+权重
        </button>
      </div>
      <div class="grid-4">
        <div class="form-group m-0" >
          <label>百度PC</label>
          <input type="number" class="form-input" name="br_pc" value="<?= (int)($site['br_pc'] ?? 0) ?>" min="0" max="10">
        </div>
        <div class="form-group m-0" >
          <label>百度移动</label>
          <input type="number" class="form-input" name="br_mobile" value="<?= (int)($site['br_mobile'] ?? 0) ?>" min="0" max="10">
        </div>
        <div class="form-group m-0" >
          <label>360</label>
          <input type="number" class="form-input" name="br_360" value="<?= (int)($site['br_360'] ?? 0) ?>" min="0" max="10">
        </div>
        <div class="form-group m-0" >
          <label>神马</label>
          <input type="number" class="form-input" name="br_shenma" value="<?= (int)($site['br_shenma'] ?? 0) ?>" min="0" max="10">
        </div>
      </div>
    </div>

    <div class="d-flex justify-end gap-12">
      <a href="/admin/sites.php" class="btn btn-secondary">取消</a>
      <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存</button>
    </div>
  </form>
</div>

<script>
(function() {
  var btn = document.getElementById('btnUpdateMeta');
  if (!btn) return;

  btn.addEventListener('click', function() {
    var urlInput = document.querySelector('input[name="url"]');
    if (!urlInput || !urlInput.value.trim()) {
      alert('请先填写站点URL');
      return;
    }
    var siteUrl = urlInput.value.trim();

    // 防止重复点击
    if (btn.disabled) return;
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader ti-spin"></i> 更新中...';

    // 结果提示区
    var resultDiv = document.getElementById('metaUpdateResult');
    if (!resultDiv) {
      resultDiv = document.createElement('div');
      resultDiv.id = 'metaUpdateResult';
      resultDiv.style.cssText = 'margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;display:none';
      btn.closest('div').appendChild(resultDiv);
    }

    // 并行调用 TDK 和 权重 API
    var csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    var tdkPromise = fetch('/api/?endpoint=fetch-tdk', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ url: siteUrl })
    }).then(function(r) { return r.json(); }).catch(function() { return { success: false, message: 'TDK请求失败' }; });

    var rankPromise = fetch('/api/rank.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ url: siteUrl })
    }).then(function(r) { return r.json(); }).catch(function() { return { success: false, message: '权重请求失败' }; });

    Promise.all([tdkPromise, rankPromise]).then(function(results) {
      var tdkRes = results[0];
      var rankRes = results[1];
      var messages = [];
      var hasUpdate = false;

      // 填充 TDK（title/keywords 不再手填，自动取 name/tags，这里只更新描述）
      if (tdkRes.success) {
        var descTextarea = document.querySelector('textarea[name="description"]');
        var nameInput = document.querySelector('input[name="name"]');
        var tagsInput = document.querySelector('input[name="tags"]');

        if (tdkRes.title && nameInput) {
          nameInput.value = tdkRes.title;
          hasUpdate = true;
        }
        if (tdkRes.description && descTextarea) {
          descTextarea.value = tdkRes.description;
          hasUpdate = true;
        }
        if (tdkRes.keywords && tagsInput) {
          tagsInput.value = tdkRes.keywords;
          hasUpdate = true;
        }
        messages.push('TDK: 已获取');
      } else {
        messages.push('TDK: ' + (tdkRes.message || '获取失败'));
      }

      // 填充权重
      if (rankRes.success) {
        var brFields = ['br_pc', 'br_mobile', 'br_360', 'br_shenma'];
        for (var i = 0; i < brFields.length; i++) {
          var input = document.querySelector('input[name="' + brFields[i] + '"]');
          if (input) {
            input.value = rankRes[brFields[i]] !== undefined ? rankRes[brFields[i]] : 0;
          }
        }
        hasUpdate = true;
        messages.push('权重: PC=' + rankRes.br_pc + ' 移动=' + rankRes.br_mobile + ' 360=' + rankRes.br_360 + ' 神马=' + rankRes.br_shenma);
      } else {
        messages.push('权重: ' + (rankRes.message || '获取失败'));
      }

      // 显示结果
      resultDiv.style.display = 'block';
      if (hasUpdate) {
        resultDiv.style.background = '#d4edda';
        resultDiv.style.color = '#155724';
        resultDiv.innerHTML = '<i class="ti ti-check"></i> ' + messages.join(' | ') + '（请确认后点击保存）';
      } else {
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
        resultDiv.innerHTML = '<i class="ti ti-alert-circle"></i> ' + messages.join(' | ');
      }

      btn.disabled = false;
      btn.innerHTML = originalHtml;
    });
  });
})();
</script>

<?php
    adminFooter();
}

function getMsg($key) {
    $msgs = [
        'created' => '站点添加成功',
        'updated' => '站点更新成功',
        'deleted' => '站点已删除',
        'batch' => '批量操作完成',
        'csrf' => 'CSRF验证失败，请重试',
        'invalid_url' => 'URL格式不正确',
        'required' => '请填写必填字段',
        'error' => '操作失败',
    ];
    return $msgs[$key] ?? '';
}

function renderAdminPagination($current, $total, $baseUrl, $params = []) {
    $html = '';
    $queryStr = !empty($params) ? '&' . http_build_query($params) : '';

    if ($current > 1) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($current - 1) . $queryStr . '"><i class="ti ti-chevron-left"></i></a> ';
    }
    for ($i = max(1, $current - 2); $i <= min($total, $current + 2); $i++) {
        if ($i == $current) {
            $html .= '<span class="current">' . $i . '</span> ';
        } else {
            $html .= '<a href="' . $baseUrl . '?page=' . $i . $queryStr . '">' . $i . '</a> ';
        }
    }
    if ($current < $total) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($current + 1) . $queryStr . '"><i class="ti ti-chevron-right"></i></a>';
    }
    return $html;
}
