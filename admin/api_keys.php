<?php
/**
 * 后台 API Key 管理
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/ApiKeyModel.php';
$currentPage = 'api_keys';

$apiKeyModel = new ApiKeyModel();
$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/api_keys.php?msg=csrf');
    }

    $adminId = $_SESSION['admin_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name = Security::cleanString($_POST['name'] ?? '', 100);
            $rateLimitPerMinute = max(1, min(10000, (int)($_POST['rate_limit_per_minute'] ?? 60)));
            $rateLimitPerHour = max(1, min(100000, (int)($_POST['rate_limit_per_hour'] ?? 1000)));
            $rateLimitPerDay = max(1, min(1000000, (int)($_POST['rate_limit_per_day'] ?? 10000)));

            if (empty($name)) {
                $msg = 'required';
                $msgType = 'error';
                break;
            }

            $id = $apiKeyModel->create([
                'name' => $name,
                'rate_limit_per_minute' => $rateLimitPerMinute,
                'rate_limit_per_hour' => $rateLimitPerHour,
                'rate_limit_per_day' => $rateLimitPerDay,
                'created_by' => $adminId,
            ]);

            if ($id) {
                $msg = 'created';
                Logger::log('admin_api_key', "创建API Key id={$id} name={$name} admin_id={$adminId}");
            } else {
                $msg = 'create_failed';
                $msgType = 'error';
            }
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = Security::cleanString($_POST['name'] ?? '', 100);
            $status = isset($_POST['status']) ? 1 : 0;
            $rateLimitPerMinute = max(1, min(10000, (int)($_POST['rate_limit_per_minute'] ?? 60)));
            $rateLimitPerHour = max(1, min(100000, (int)($_POST['rate_limit_per_hour'] ?? 1000)));
            $rateLimitPerDay = max(1, min(1000000, (int)($_POST['rate_limit_per_day'] ?? 10000)));

            if (!$id || empty($name)) {
                $msg = 'invalid';
                $msgType = 'error';
                break;
            }

            $result = $apiKeyModel->update($id, [
                'name' => $name,
                'status' => $status,
                'rate_limit_per_minute' => $rateLimitPerMinute,
                'rate_limit_per_hour' => $rateLimitPerHour,
                'rate_limit_per_day' => $rateLimitPerDay,
            ]);

            if ($result) {
                $msg = 'updated';
                Logger::log('admin_api_key', "更新API Key id={$id} name={$name} admin_id={$adminId}");
            } else {
                $msg = 'update_failed';
                $msgType = 'error';
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                $msg = 'invalid';
                $msgType = 'error';
                break;
            }

            $result = $apiKeyModel->delete($id);
            if ($result) {
                $msg = 'deleted';
                Logger::log('admin_api_key', "删除API Key id={$id} admin_id={$adminId}");
            } else {
                $msg = 'delete_failed';
                $msgType = 'error';
            }
            break;

        case 'cleanup':
            $count = $apiKeyModel->cleanOldRateLimits();
            $msg = 'cleaned';
            Logger::log('admin_api_key', "清理过期限流记录 count={$count} admin_id={$adminId}");
            break;
    }
}

// ========== 获取列表 ==========
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$list = $apiKeyModel->getAll($page, $pageSize);
$total = $apiKeyModel->countAll();
$totalPages = ceil($total / $pageSize);

// ========== 消息映射 ==========
function getMsg($msg) {
    $map = [
        'csrf' => 'CSRF 校验失败，请刷新页面重试',
        'required' => '请填写必填字段',
        'invalid' => '参数错误',
        'created' => 'API Key 创建成功',
        'create_failed' => '创建失败',
        'updated' => '更新成功',
        'update_failed' => '更新失败',
        'deleted' => '删除成功',
        'delete_failed' => '删除失败',
        'cleaned' => '清理完成',
    ];
    return $map[$msg] ?? $msg;
}

adminHeader('API 密钥管理');

if ($msg) { adminAlert(getMsg($msg), $msgType === 'error' ? 'error' : 'success'); }
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">API Key 列表 (共 <?= $total ?> 个)</span>
    <div class="card-actions">
      <button class="btn btn-primary" onclick="showCreateModal()">
        <i class="ti ti-plus"></i> 创建 API Key
      </button>
      <form method="post" style="display:inline;" onsubmit="return confirm('确定要清理过期的限流记录吗？');">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="cleanup">
        <button type="submit" class="btn btn-secondary">
          <i class="ti ti-trash"></i> 清理过期限流
        </button>
      </form>
    </div>
  </div>

  <div class="card-body">
    <?php if (empty($list)): ?>
    <div class="empty-state">
      <i class="ti ti-key"></i>
      <p>暂无 API Key，点击右上角按钮创建</p>
    </div>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>名称</th>
          <th>API Key</th>
          <th>状态</th>
          <th>调用限制（分/时/天）</th>
          <th>总调用次数</th>
          <th>最后调用</th>
          <th>创建时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $item): ?>
        <tr>
          <td><?= $item['id'] ?></td>
          <td class="fw-600"><?= Security::e($item['name']) ?></td>
          <td>
            <code class="api-key-text"><?= Security::e(substr($item['api_key'], 0, 16)) ?>...</code>
            <button class="btn btn-sm btn-secondary" onclick="copyText('<?= Security::eAttr($item['api_key']) ?>')" title="复制完整 Key">
              <i class="ti ti-copy"></i>
            </button>
          </td>
          <td>
            <?php if ($item['status']): ?>
            <span class="badge badge-success">启用</span>
            <?php else: ?>
            <span class="badge badge-secondary">禁用</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="text-muted">
              <?= $item['rate_limit_per_minute'] ?> /
              <?= $item['rate_limit_per_hour'] ?> /
              <?= $item['rate_limit_per_day'] ?>
            </span>
          </td>
          <td><?= number_format($item['call_count']) ?></td>
          <td>
            <?php if ($item['last_call_at']): ?>
            <?= date('Y-m-d H:i', strtotime($item['last_call_at'])) ?>
            <?php else: ?>
            <span class="text-muted">从未调用</span>
            <?php endif; ?>
          </td>
          <td><?= date('Y-m-d H:i', strtotime($item['created_at'])) ?></td>
          <td class="actions">
            <button class="btn btn-sm btn-secondary" onclick='showEditModal(<?= json_encode($item, JSON_UNESCAPED_UNICODE) ?>)'>
              <i class="ti ti-edit"></i>
            </button>
            <form method="post" style="display:inline;" onsubmit="return confirm('确定要删除这个 API Key 吗？删除后无法恢复。');">
              <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
        <span class="page-item active"><?= $i ?></span>
        <?php else: ?>
        <a href="?page=<?= $i ?>" class="page-item"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 使用说明 -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-book"></i> API 使用说明</span>
  </div>
  <div class="card-body">
    <h4 style="margin-top:0;">鉴权方式</h4>
    <p>在请求头中添加 <code>X-API-Key</code>，或在 URL 参数中添加 <code>api_key</code>。</p>
    <pre>curl -H "X-API-Key: your_api_key" https://your-domain.com/api/open/sites</pre>

    <h4>可用接口</h4>
    <ul>
      <li><code>GET /api/open/sites</code> - 获取站点列表（支持 category、page、limit、sort 参数）</li>
      <li><code>GET /api/open/site?id={id}</code> - 获取站点详情</li>
      <li><code>GET /api/open/rank?type=views</code> - 获取排行榜（支持 views、clicks、br_pc、br_mobile、newest 类型）</li>
      <li><code>GET /api/open/categories</code> - 获取分类列表</li>
      <li><code>GET /api/open/search?q=keyword</code> - 搜索站点</li>
      <li><code>GET /api/open/stats</code> - 获取站点统计信息</li>
    </ul>

    <h4>限流响应头</h4>
    <ul>
      <li><code>X-RateLimit-Limit</code> - 当前周期限制次数</li>
      <li><code>X-RateLimit-Remaining</code> - 当前周期剩余次数</li>
      <li><code>X-RateLimit-Reset</code> - 限流重置时间戳</li>
    </ul>

    <h4>错误码说明</h4>
    <ul>
      <li><code>40101</code> - 缺少 API Key</li>
      <li><code>40102</code> - API Key 无效或已过期</li>
      <li><code>42901</code> - 调用频率超出限制</li>
      <li><code>40401</code> - 资源不存在</li>
      <li><code>40001</code> - 参数错误</li>
    </ul>
  </div>
</div>

<!-- 创建/编辑模态框 -->
<div id="modal" class="modal" style="display:none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">创建 API Key</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form method="post" id="modalForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" id="modalAction" value="create">
        <input type="hidden" name="id" id="modalId" value="">

        <div class="form-group">
          <label>密钥名称 <span class="text-danger">*</span></label>
          <input type="text" name="name" id="modalName" class="form-input" required placeholder="请输入密钥名称，用于标识用途">
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="status" id="modalStatus" checked>
            <span>启用</span>
          </label>
        </div>

        <h4 style="margin-top:20px; margin-bottom:12px;">调用频率限制</h4>

        <div class="form-row">
          <div class="form-group">
            <label>每分钟限制</label>
            <input type="number" name="rate_limit_per_minute" id="modalRateMin" class="form-input" value="60" min="1" max="10000">
          </div>
          <div class="form-group">
            <label>每小时限制</label>
            <input type="number" name="rate_limit_per_hour" id="modalRateHour" class="form-input" value="1000" min="1" max="100000">
          </div>
          <div class="form-group">
            <label>每天限制</label>
            <input type="number" name="rate_limit_per_day" id="modalRateDay" class="form-input" value="10000" min="1" max="1000000">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
        <button type="submit" class="btn btn-primary">确认</button>
      </div>
    </form>
  </div>
</div>

<style>
.api-key-text {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-family: monospace;
}
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: white;
    border-radius: 8px;
    width: 500px;
    max-width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
    font-size: 16px;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    line-height: 1;
}
.modal-body {
    padding: 20px;
}
.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #eee;
    text-align: right;
}
.form-row {
    display: flex;
    gap: 16px;
}
.form-row .form-group {
    flex: 1;
}
pre {
    background: #f5f5f5;
    padding: 12px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 13px;
}
code {
    background: #f5f5f5;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 13px;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.checkbox-label input {
    margin: 0;
}
.card-actions {
    display: flex;
    gap: 8px;
}
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
}
.text-danger {
    color: #e74c3c;
}
</style>

<script>
function showCreateModal() {
    document.getElementById('modalTitle').textContent = '创建 API Key';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalId').value = '';
    document.getElementById('modalName').value = '';
    document.getElementById('modalStatus').checked = true;
    document.getElementById('modalRateMin').value = 60;
    document.getElementById('modalRateHour').value = 1000;
    document.getElementById('modalRateDay').value = 10000;
    document.getElementById('modal').style.display = 'flex';
}

function showEditModal(item) {
    document.getElementById('modalTitle').textContent = '编辑 API Key';
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalId').value = item.id;
    document.getElementById('modalName').value = item.name;
    document.getElementById('modalStatus').checked = item.status == 1;
    document.getElementById('modalRateMin').value = item.rate_limit_per_minute;
    document.getElementById('modalRateHour').value = item.rate_limit_per_hour;
    document.getElementById('modalRateDay').value = item.rate_limit_per_day;
    document.getElementById('modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('已复制到剪贴板');
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('已复制到剪贴板');
    } catch (e) {
        alert('复制失败，请手动复制');
    }
    document.body.removeChild(textarea);
}

// 点击模态框外部关闭
document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// ESC 键关闭模态框
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php adminFooter(); ?>
