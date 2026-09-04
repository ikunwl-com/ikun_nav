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

// ========== 接口清单数据（核心 open/* + 全部插件的 api.php 声明，供使用说明展示） ==========
$openCoreGroups = [];
foreach (OpenApi::coreEndpoints() as $openDef) {
    $openCoreGroups[$openDef['group']][] = $openDef;
}
$openPluginGroups = [];
foreach (OpenApi::allPluginEndpoints() as $openDef) {
    $openPluginGroups[$openDef['group']][] = $openDef;
}
$apiSiteUrl = rtrim(setting('site_url', ''), '/');
if ($apiSiteUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $apiSiteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com');
}

/**
 * 方法徽标
 */
function openApiMethodBadge(string $method): string
{
    $cls = $method === 'GET' ? 'badge-success' : ($method === 'POST' ? 'badge-info' : 'badge-secondary');
    return '<span class="badge ' . $cls . '">' . Security::e($method) . '</span>';
}

/**
 * 根据接口声明的 example 生成 curl 调用示例
 */
function openApiCurlExample(array $def, string $siteUrl): string
{
    $method  = strtoupper($def['method'] ?? 'GET');
    $example = (string)($def['example'] ?? '');
    $path    = $example;
    $body    = '';
    $pos     = strpos($example, '  body:');
    if ($pos !== false) {
        $path = substr($example, 0, $pos);
        $body = trim(substr($example, $pos + 7));
    }
    // 去掉示例开头的 GET/POST 前缀与两端空白
    $path = preg_replace('/^(GET|POST|PUT|DELETE)\s+/i', '', $path);
    $path = trim($path, " /");

    $lines   = [];
    $lines[] = 'curl -X ' . $method . ' \\';
    $lines[] = '  -H "X-API-Key: <你的API Key>" \\';
    if ($method === 'POST') {
        $lines[] = '  -H "Content-Type: application/json" \\';
    }
    $lines[] = '  "' . $siteUrl . '/' . $path . '"';
    if ($body !== '') {
        $lines[] = "  -d '" . str_replace("'", "\\'", $body) . "'";
    }
    return implode("\n", $lines);
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
    <span class="card-title"><i class="ti ti-book"></i> API 使用说明（完整接口清单与对接文档）</span>
  </div>
  <div class="card-body">

    <div class="empty-state" style="padding:14px;text-align:left;background:#f8f9fa;border-radius:6px;margin-bottom:16px;">
      <p style="margin:0;color:#333;font-size:13px;line-height:1.8;">
        ✅ 开放 API 统一以 <code>open/</code> 开头，必须携带 API Key。除查询外，已提供
        <strong>发布（open/submit）、编辑（open/site/update）、删除（open/site/delete）以及分类的新增/编辑/删除</strong>，
        App、小程序、前台自定义提交页都可直接调用，无需 CSRF。
        <br>✅ 内置插件（文章 / 虫洞联盟 / 友情链接 / 蜘蛛来访）在对应插件<strong>启用后</strong>，
        其 <code>open/插件名/*</code> 接口会自动注册并出现在下方清单与示例中；停用后自动失效。
        <br>📄 完整对接文档（可直接发给开发方）：<code>data/docs/api-guide.md</code>（在线版文档第六章 6.1 同步更新）。
      </p>
    </div>

    <h4 style="margin-top:0;">1. 鉴权方式</h4>
    <p>请求头 <code>X-API-Key</code>，或 URL 参数 <code>api_key</code>，或 POST JSON 中的 <code>api_key</code> 字段（三选一）。</p>
    <pre>curl -H "X-API-Key: &lt;你的API Key&gt;" "<?= Security::e($apiSiteUrl) ?>/api/open/sites?page=1&amp;limit=20"</pre>

    <h4>2. 请求与响应约定</h4>
    <ul>
      <li>接口地址：<code><?= Security::e($apiSiteUrl) ?>/api/{endpoint}</code>（伪静态 /api/{endpoint} 亦可），端点形如 <code>open/sites</code></li>
      <li>GET 接口用 URL 参数；POST 接口用 JSON 请求体（<code>Content-Type: application/json</code>），编辑类接口支持部分更新（传哪些字段改哪些）</li>
      <li>成功响应：<code>{success:true, code:0, message:"ok", data:{...}}</code>；失败响应：<code>{success:false, code:错误码, message:"原因"}</code></li>
      <li>列表类接口返回分页结构 <code>data.list / data.total / data.page / data.limit / data.total_pages</code></li>
      <li>API Key 视为受信凭证，写接口可操作任意站点（等同后台能力），请只把 Key 交给可信的开发方</li>
    </ul>

    <h4>3. 限流响应头</h4>
    <ul>
      <li><code>X-RateLimit-Limit</code> - 当前周期限制次数</li>
      <li><code>X-RateLimit-Remaining</code> - 当前周期剩余次数</li>
      <li><code>X-RateLimit-Reset</code> - 限流重置时间戳</li>
    </ul>

    <h4>4. 错误码说明</h4>
    <ul>
      <li><code>40101</code> - 缺少 API Key</li>
      <li><code>40102</code> - API Key 无效或已过期</li>
      <li><code>42901</code> - 调用频率超出限制</li>
      <li><code>40001</code> - 参数错误</li>
      <li><code>40301</code> - 接口所属插件未启用（启用插件后即可调用）</li>
      <li><code>40401</code> - 资源不存在</li>
      <li><code>40901</code> - 冲突（如分类下仍有站点无法删除）</li>
    </ul>

    <h4>5. 完整接口清单</h4>
    <p class="text-muted">核心接口覆盖查询 / 发布 / 编辑 / 删除 / 分类管理；内置插件（文章、虫洞联盟、友情链接、蜘蛛来访等）的接口随插件启用状态自动出现在下方（见「插件状态」列，未启用插件在后台启用后自动生效）。</p>

    <?php foreach ($openCoreGroups as $group => $defs): ?>
    <h5 class="doc-group"><?= Security::e($group) ?></h5>
    <table class="data-table">
      <thead>
        <tr><th style="width:220px;">端点</th><th style="width:70px;">方法</th><th>说明</th><th>参数 / JSON 请求体</th></tr>
      </thead>
      <tbody>
        <?php foreach ($defs as $def): ?>
        <tr>
          <td><code><?= Security::e($def['endpoint']) ?></code></td>
          <td><?= openApiMethodBadge($def['method']) ?></td>
          <td><?= Security::e($def['desc'] ?? '') ?></td>
          <td class="text-muted" style="font-size:12px;"><?= Security::e($def['params'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endforeach; ?>

    <?php if (empty($openPluginGroups)): ?>
    <p class="text-muted">尚未发现插件接口声明（plugins/*/api.php）。启用文章 / 虫洞联盟 / 友情链接 / 蜘蛛来访等内置插件后，其接口会自动出现在此处。</p>
    <?php else: foreach ($openPluginGroups as $group => $defs): ?>
    <h5 class="doc-group"><?= Security::e($group) ?></h5>
    <table class="data-table">
      <thead>
        <tr><th style="width:220px;">端点</th><th style="width:70px;">方法</th><th>说明</th><th>参数 / JSON 请求体</th><th style="width:150px;">插件状态</th></tr>
      </thead>
      <tbody>
        <?php foreach ($defs as $def): ?>
        <tr>
          <td><code><?= Security::e($def['endpoint']) ?></code></td>
          <td><?= openApiMethodBadge($def['method']) ?></td>
          <td><?= Security::e($def['desc'] ?? '') ?></td>
          <td class="text-muted" style="font-size:12px;"><?= Security::e($def['params'] ?? '') ?></td>
          <td>
            <?php if (!empty($def['plugin_enabled'])): ?>
            <span class="badge badge-success">已启用 · <?= Security::e($def['plugin_title'] ?? $def['plugin']) ?></span>
            <?php else: ?>
            <span class="badge badge-secondary">未启用 · <?= Security::e($def['plugin_title'] ?? $def['plugin']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endforeach; endif; ?>

    <h4>6. 调用示例（curl，每个接口一条）</h4>
    <p class="text-muted">把 <code>&lt;你的API Key&gt;</code> 替换为上方列表中的完整 Key。POST 接口示例已含 JSON 请求体。</p>
    <?php
    $curlAll = [];
    foreach ($openCoreGroups as $group => $defs) { foreach ($defs as $def) { $curlAll[] = ['def' => $def, 'group' => $group]; } }
    foreach ($openPluginGroups as $group => $defs) { foreach ($defs as $def) { $curlAll[] = ['def' => $def, 'group' => $group]; } }
    foreach ($curlAll as $curlItem):
        $curlDef = $curlItem['def'];
    ?>
    <details class="apidoc">
      <summary>
        <code><?= Security::e(strtoupper($curlDef['method'])) ?></code>
        <code>/api/<?= Security::e($curlDef['endpoint']) ?></code>
        <?= Security::e($curlDef['title'] ?? '') ?>
        <?php if (isset($curlDef['plugin']) && empty($curlDef['plugin_enabled'])): ?>
        <span class="badge badge-secondary">插件未启用</span>
        <?php endif; ?>
      </summary>
      <pre><?= Security::e(openApiCurlExample($curlDef, $apiSiteUrl)) ?></pre>
    </details>
    <?php endforeach; ?>

    <h4>7. 常见对接场景</h4>
    <ul>
      <li><strong>App / 小程序读数据</strong>：<code>open/sites</code>、<code>open/site</code>、<code>open/site/related</code>、<code>open/featured</code>、<code>open/rank</code>、<code>open/search</code>、<code>open/categories</code>、<code>open/stats</code>，以及启用插件后的文章 / 虫洞 / 友链 / 蜘蛛查询接口</li>
      <li><strong>App / 小程序 / 合作方直接发布与管理</strong>：<code>open/submit</code> 发布，<code>open/site/update</code> 编辑，<code>open/site/delete</code> 删除，<code>open/category/create|update|delete</code> 维护分类，插件（文章等）发布 / 编辑 / 删除同理</li>
      <li><strong>前台提交网站</strong>：提交页直接调用 <code>open/submit</code>（无需 CSRF）；提交前可用 <code>open/site/check</code> 检测是否已收录 / 查审核状态</li>
      <li><strong>收录进度查询</strong>：<code>open/site/check?url=https://example.com</code> 返回 <code>found/status(待审核|已收录)/status_text</code></li>
    </ul>

    <h4>8. 前端 / 小程序代码示例</h4>
    <p><strong>浏览器 fetch - 查询</strong></p>
    <pre>fetch('<?= Security::e($apiSiteUrl) ?>/api/open/sites?page=1&limit=20', {
  headers: { 'X-API-Key': '&lt;你的API Key&gt;' }
}).then(r =&gt; r.json()).then(res =&gt; console.log(res));</pre>
    <p><strong>浏览器 fetch - 发布站点（前台提交页直接用）</strong></p>
    <pre>fetch('<?= Security::e($apiSiteUrl) ?>/api/open/submit', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-API-Key': '&lt;你的API Key&gt;' },
  body: JSON.stringify({
    name: '示例站',
    url: 'https://example.com',
    category_id: 1,
    description: '一句话简介',
    tags: ['工具', '效率']
  })
}).then(r =&gt; r.json()).then(res =&gt; console.log(res));</pre>
    <p><strong>微信小程序 wx.request - 查询收录状态</strong></p>
    <pre>wx.request({
  url: '<?= Security::e($apiSiteUrl) ?>/api/open/site/check',
  data: { url: 'https://example.com' },
  header: { 'X-API-Key': '&lt;你的API Key&gt;' },
  success(res) { console.log(res.data); }
});</pre>
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
.doc-group {
    margin: 18px 0 8px;
    padding-left: 8px;
    border-left: 3px solid #4e73df;
    color: #333;
    font-size: 14px;
}
details.apidoc {
    margin: 6px 0;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 12px;
    background: #fff;
}
details.apidoc summary {
    cursor: pointer;
    font-size: 13px;
    color: #333;
}
details.apidoc summary code {
    margin-right: 6px;
}
details.apidoc pre {
    margin: 10px 0 4px;
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
