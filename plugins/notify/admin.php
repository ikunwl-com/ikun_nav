<?php
/**
 * 邮箱通知插件 - 管理页面
 * 通过 /admin/plugin.php?p=notify 访问
 * 本文件由分发器加载，adminHeader()/adminFooter() 已由分发器处理
 *
 * 功能：
 *   1. 通知发送日志列表（分页、筛选）
 *   2. SMTP 测试发送
 *   3. 清空日志
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// admin.php 由 admin/plugin.php 单独加载，不会自动 include include.php
// 需要手动加载核心函数定义（notify_email_template / notify_send / notify_config 等）
$includeFile = __DIR__ . '/include.php';
if (file_exists($includeFile)) {
    require_once $includeFile;
}

// ========== POST 处理 ==========
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        redirect('/admin/plugin.php?p=notify&err=' . urlencode('CSRF验证失败'));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'test_send') {
        try {
            // SMTP 测试发送
            $testEmail = trim(isset($_POST['test_email']) ? $_POST['test_email'] : '');
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $msg = '测试收件人邮箱格式不正确';
                $msgType = 'error';
            } else {
                $subject = '【' . setting('site_name', '懒人导航') . '】SMTP 测试邮件';
                $content = '<p>这是一封 SMTP 测试邮件。</p>';
                $content .= '<p>如果你收到了这封邮件，说明邮箱通知插件的 SMTP 配置是正确的。</p>';
                $content .= '<p style="color:#999;font-size:12px;margin-top:24px;">发送时间：' . date('Y-m-d H:i:s') . '</p>';

                // 确保 notify_email_template 函数可用
                if (!function_exists('notify_email_template')) {
                    $msg = '通知插件核心函数未加载，请检查插件是否已正确启用。';
                    $msgType = 'error';
                } else {
                    $body = notify_email_template('SMTP 测试邮件', $content);

                    // 临时覆盖收件人
                    $origRecipient = function_exists('notify_config') ? notify_config('recipient', '') : '';
                    Plugin::setConfig('notify', 'recipient', $testEmail);

                    $ok = notify_send('test', $subject, $body);

                    // 恢复原收件人
                    Plugin::setConfig('notify', 'recipient', $origRecipient);

                    if ($ok) {
                        $msg = "测试邮件已发送至 {$testEmail}，请查收";
                    } else {
                        $msg = "测试邮件发送失败";
                        $msgType = 'error';
                    }
                }
            }
        } catch (\Exception $e) {
            $msg = '测试发送异常: ' . $e->getMessage();
            $msgType = 'error';
            if (class_exists('Logger')) {
                Logger::log('plugin_error', "notify 插件测试发送异常: " . $e->getMessage());
            }
        }
    } elseif ($action === 'clear_logs') {
        try {
            Database::execute("DELETE FROM " . table('notify_logs'));
            Logger::log('admin_setting', "清空邮箱通知日志 admin_id=" . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : '?'));
            $msg = '通知日志已清空';
        } catch (\Exception $e) {
            $msg = '清空日志失败: ' . $e->getMessage();
            $msgType = 'error';
        }
    } elseif ($action === 'delete_log') {
        $logId = (int)(isset($_POST['log_id']) ? $_POST['log_id'] : 0);
        if ($logId > 0) {
            try {
                Database::execute("DELETE FROM " . table('notify_logs') . " WHERE id = ?", [$logId]);
                $msg = '日志已删除';
            } catch (\Exception $e) {
                $msg = '删除失败: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

// ========== GET 消息 ==========
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
    $msgType = 'success';
} elseif (isset($_GET['err'])) {
    $msg = Security::cleanString($_GET['err']);
    $msgType = 'error';
}

// ========== 查询日志列表 ==========
$page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 筛选
$filterType = isset($_GET['ftype']) ? $_GET['ftype'] : '';
$filterStatus = isset($_GET['fstatus']) ? $_GET['fstatus'] : '';

$where = [];
$params = [];
if ($filterType !== '') {
    $where[] = 'type = ?';
    $params[] = $filterType;
}
if ($filterStatus !== '' && $filterStatus !== 'all') {
    $where[] = 'status = ?';
    $params[] = (int)$filterStatus;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// 快速获取配置
$n_cfg = function ($k, $d = '') {
    return (string) Plugin::config('notify', $k, $d);
};

// 总数
try {
    $total = (int)Database::queryOne("SELECT COUNT(*) as cnt FROM " . table('notify_logs') . $whereSql, $params)['cnt'];
} catch (\Exception $e) {
    $total = 0;
}
$totalPages = max(1, (int)ceil($total / $perPage));

// 查询
try {
    $logs = Database::query(
        "SELECT * FROM " . table('notify_logs') . $whereSql . " ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
} catch (\Exception $e) {
    $logs = [];
}

// 类型映射
$typeLabels = [
    'submitted' => '站点提交',
    'feedback'  => '用户反馈',
    'approved'  => '审核通过',
    'rejected'  => '审核拒绝',
    'test'      => '测试发送',
];
?>

<?php if ($msg): ?>
  <?php adminAlert($msg, $msgType); ?>
<?php endif; ?>

<!-- SMTP 快速状态 + 测试发送 -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <span class="card-title">SMTP 状态 & 测试</span>
    <a href="/admin/settings.php?tab=notify" class="btn btn-sm"><i class="ti ti-settings"></i> SMTP 配置</a>
  </div>
  <div style="padding:16px 20px;">
    <div style="display:flex;gap:30px;flex-wrap:wrap;margin-bottom:16px;font-size:14px;">
      <div>
        <span style="color:#999;">SMTP 服务器：</span>
        <span style="font-weight:600;"><?= $n_cfg('smtp_host') ? Security::e($n_cfg('smtp_host') . ':' . $n_cfg('smtp_port')) : '<span style="color:#e74c3c;">未配置</span>' ?></span>
      </div>
      <div>
        <span style="color:#999;">发件人：</span>
        <span style="font-weight:600;"><?= $n_cfg('from_email') ? Security::e($n_cfg('from_email')) : '<span style="color:#e74c3c;">未配置</span>' ?></span>
      </div>
      <div>
        <span style="color:#999;">收件人：</span>
        <span style="font-weight:600;"><?= $n_cfg('recipient') ? Security::e($n_cfg('recipient')) : '<span style="color:#e74c3c;">未配置</span>' ?></span>
      </div>
    </div>

    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '') ?>">
      <input type="hidden" name="action" value="test_send">
      <div class="form-group" style="margin:0;flex:1;min-width:250px;">
        <label style="font-size:12px;color:#999;">测试收件人邮箱</label>
        <input type="email" class="form-input" name="test_email" placeholder="输入测试收件人邮箱" value="<?= Security::eAttr($n_cfg('recipient', '')) ?>" style="margin-top:4px;">
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> 发送测试邮件</button>
    </form>
  </div>
</div>

<!-- 日志列表 -->
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <span class="card-title">通知发送日志 <span class="text-xs-dim-2">（共 <?= $total ?> 条）</span></span>
    <?php if ($total > 0): ?>
    <form method="POST" style="display:inline;" onsubmit="return confirm('确定清空所有通知日志？此操作不可恢复。')">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '') ?>">
      <input type="hidden" name="action" value="clear_logs">
      <button type="submit" class="btn btn-sm" style="color:#e74c3c;"><i class="ti ti-trash"></i> 清空日志</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- 筛选 -->
  <div style="padding:12px 20px;border-bottom:1px solid #f0f0f0;">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="p" value="notify">
      <select name="ftype" class="form-input" style="width:auto;">
        <option value="">全部类型</option>
        <option value="submitted" <?= $filterType === 'submitted' ? 'selected' : '' ?>>站点提交</option>
        <option value="feedback" <?= $filterType === 'feedback' ? 'selected' : '' ?>>用户反馈</option>
        <option value="approved" <?= $filterType === 'approved' ? 'selected' : '' ?>>审核通过</option>
        <option value="rejected" <?= $filterType === 'rejected' ? 'selected' : '' ?>>审核拒绝</option>
        <option value="test" <?= $filterType === 'test' ? 'selected' : '' ?>>测试发送</option>
      </select>
      <select name="fstatus" class="form-input" style="width:auto;">
        <option value="all">全部状态</option>
        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>成功</option>
        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>失败</option>
      </select>
      <button type="submit" class="btn btn-sm"><i class="ti ti-filter"></i> 筛选</button>
      <?php if ($filterType || ($filterStatus !== '' && $filterStatus !== 'all')): ?>
        <a href="/admin/plugin.php?p=notify" class="btn btn-sm">重置</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($logs)): ?>
    <div style="padding:40px;text-align:center;color:#999;font-size:14px;">
      <i class="ti ti-mailbox" style="font-size:36px;display:block;margin-bottom:10px;"></i>
      暂无通知日志
    </div>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="table" style="width:100%;font-size:13px;">
        <thead>
          <tr>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">ID</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">类型</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">收件人</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">主题</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">状态</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">时间</th>
            <th style="padding:10px 12px;text-align:left;white-space:nowrap;">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td style="padding:8px 12px;color:#999;"><?= (int)$log['id'] ?></td>
              <td style="padding:8px 12px;">
                <span class="badge" style="background:#eef;color:#667eea;padding:2px 8px;border-radius:4px;font-size:12px;">
                  <?= Security::e(isset($typeLabels[$log['type']]) ? $typeLabels[$log['type']] : $log['type']) ?>
                </span>
              </td>
              <td style="padding:8px 12px;color:#555;font-size:12px;"><?= Security::e($log['recipient']) ?></td>
              <td style="padding:8px 12px;color:#333;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= Security::eAttr($log['subject']) ?>">
                <?= Security::e($log['subject']) ?>
              </td>
              <td style="padding:8px 12px;">
                <?php if ((int)$log['status'] === 1): ?>
                  <span style="color:#27ae60;font-weight:600;"><i class="ti ti-circle-check"></i> 成功</span>
                <?php else: ?>
                  <span style="color:#e74c3c;font-weight:600;"><i class="ti ti-circle-x"></i> 失败</span>
                  <?php if ($log['error']): ?>
                    <div style="color:#999;font-size:11px;margin-top:2px;" title="<?= Security::eAttr($log['error']) ?>"><?= Security::e(mb_substr($log['error'], 0, 60)) ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="padding:8px 12px;color:#999;font-size:12px;white-space:nowrap;"><?= Security::e($log['created_at']) ?></td>
              <td style="padding:8px 12px;white-space:nowrap;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('删除此条日志？')">
                  <input type="hidden" name="csrf_token" value="<?= Security::eAttr(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '') ?>">
                  <input type="hidden" name="action" value="delete_log">
                  <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="color:#e74c3c;padding:2px 8px;" title="删除"><i class="ti ti-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:12px 20px;display:flex;justify-content:center;gap:4px;">
      <?php
      $baseQs = 'p=notify';
      if ($filterType) $baseQs .= '&ftype=' . urlencode($filterType);
      if ($filterStatus !== '' && $filterStatus !== 'all') $baseQs .= '&fstatus=' . urlencode($filterStatus);
      ?>
      <?php if ($page > 1): ?>
        <a href="/admin/plugin.php?<?= $baseQs ?>&page=<?= $page - 1 ?>" class="btn btn-sm">&laquo; 上一页</a>
      <?php endif; ?>
      <span style="padding:4px 12px;line-height:28px;font-size:13px;color:#555;"><?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/admin/plugin.php?<?= $baseQs ?>&page=<?= $page + 1 ?>" class="btn btn-sm">下一页 &raquo;</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
