<?php
/**
 * 后台 - 程序更新页面
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'update';

// 安全：仅管理员可访问
if (empty($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

// 获取当前版本与备份列表
$currentVersion = Updater::currentVersion();
$backups = Updater::listBackups();

adminHeader('程序更新', '<link rel="stylesheet" href="/assets/css/update.css">');
?>

<!-- 版本信息卡片 -->
<div class="card">
    <?php $currentUrl = getCurrentSiteUrl(); $siteUrl = setting('site_url'); ?>
    每次更新后请自行访问<span class="btn btn-sm btn-danger"><?= Security::eAttr($siteUrl ?: $currentUrl) ?>/install/upgrade_v2.php</span>或 <a href="<?= Security::eAttr($siteUrl ?: $currentUrl) ?>/install/upgrade_v2.php" target="_blank" class="btn btn-sm btn-danger">点我检测</a> ，用于检测现有表是否最新和最全以及清理多余表和字段。
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-refresh"></i> 程序更新</span>
    <span class="badge badge-info">当前版本 <?= Security::e($currentVersion) ?></span>
  </div>

  <div class="update-body">
    <!-- 检测区域 -->
    <div id="checkSection" class="update-section">
      <p class="update-desc">点击检测按钮，系统将连接更新服务器查询是否有新版本。</p>
      <button id="btnCheck" class="btn btn-primary" onclick="checkUpdate()">
        <i class="ti ti-search"></i> 检测新版本
      </button>
      <div id="checkResult" class="update-result hidden"></div>
    </div>

    <!-- 下载区域（检测后显示） -->
    <div id="downloadSection" class="update-section hidden">
      <h3>发现新版本 <span id="newVersion" class="text-primary"></span></h3>
      <div id="changelogBox" class="changelog-box hidden">
        <h4>更新日志</h4>
        <pre id="changelogText"></pre>
      </div>
      <button id="btnDownload" class="btn btn-primary" onclick="startDownload()">
        <i class="ti ti-download"></i> 下载更新包
      </button>
      <div id="downloadProgress" class="progress-bar hidden">
        <div class="progress-fill w-9c33" id="progressFill" ></div>
        <span class="progress-text" id="progressText">0%</span>
      </div>
      <div id="downloadResult" class="update-result hidden"></div>
    </div>

    <!-- 安装区域（下载后显示） -->
    <div id="installSection" class="update-section hidden">
      <div class="alert alert-warning">
        <i class="ti ti-alert-triangle"></i>
        <strong>重要提示：</strong>安装更新前系统会自动备份当前版本。如果更新失败，可使用下方备份列表回滚。
      </div>
      <button id="btnInstall" class="btn btn-success" onclick="startInstall()">
        <i class="ti ti-player-play"></i> 立即安装更新
      </button>
      <div id="installResult" class="update-result hidden"></div>
    </div>
  </div>
</div>

<!-- 备份管理 -->
<div class="card mt-20">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-archive"></i> 备份管理</span>
    <span class="text-muted">保留最近 5 个备份，旧备份自动清理</span>
  </div>
  <?php if (empty($backups)): ?>
  <div class="empty-state">
    <div class="icon"><i class="ti ti-archive-off"></i></div>
    <p>暂无备份记录</p>
  </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>备份时间</th><th>版本</th><th>大小</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($backups as $b): ?>
      <tr>
        <td><?= Security::e($b['time']) ?></td>
        <td><span class="badge badge-info"><?= Security::e($b['version']) ?></span></td>
        <td><?= formatNumber($b['size']) ?> bytes</td>
        <td class="actions">
          <button class="btn btn-sm btn-warning" onclick="rollback('<?= Security::e($b['dir']) ?>')">
            <i class="ti ti-arrow-back-up"></i> 回滚
          </button>
          <button class="btn btn-sm btn-danger" onclick="deleteBackup('<?= Security::e($b['dir']) ?>')">
            <i class="ti ti-trash"></i> 删除
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script>
const csrfToken = document.getElementById('csrfToken').value;
let downloadUrl = '';
let packageFile = '';

// 检测新版本
async function checkUpdate() {
  const btn = document.getElementById('btnCheck');
  const result = document.getElementById('checkResult');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> 检测中...';
  result.classList.add('hidden');

  try {
    const res = await fetch('/admin/update_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=check&csrf_token=' + encodeURIComponent(csrfToken)
    });
    const data = await res.json();

    if (data.error) {
      result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> ' + esc(data.error) + '</div>';
      result.classList.remove('hidden');
    } else if (data.has_update) {
      result.innerHTML = '<div class="alert alert-success"><i class="ti ti-circle-check"></i> 发现新版本：' + esc(data.version) + '</div>';
      result.classList.remove('hidden');

      document.getElementById('newVersion').textContent = data.version;
      downloadUrl = data.download_url || '';

      if (data.changelog) {
        document.getElementById('changelogText').textContent = data.changelog;
        document.getElementById('changelogBox').classList.remove('hidden');
      }

      // 显示下载区域
      document.getElementById('downloadSection').classList.remove('hidden');
    } else {
      result.innerHTML = '<div class="alert alert-success"><i class="ti ti-circle-check"></i> 当前已是最新版本（' + esc(data.version) + '）</div>';
      result.classList.remove('hidden');
    }
  } catch (e) {
    result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 请求失败：' + esc(e.message) + '</div>';
    result.classList.remove('hidden');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-search"></i> 检测新版本';
}

// 下载更新包
async function startDownload() {
  const btn = document.getElementById('btnDownload');
  const progress = document.getElementById('downloadProgress');
  const fill = document.getElementById('progressFill');
  const text = document.getElementById('progressText');
  const result = document.getElementById('downloadResult');

  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> 下载中...';
  progress.classList.remove('hidden');
  result.classList.add('hidden');

  try {
    const res = await fetch('/admin/update_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=download&url=' + encodeURIComponent(downloadUrl) + '&csrf_token=' + encodeURIComponent(csrfToken)
    });
    const data = await res.json();

    if (data.success) {
      packageFile = data.file;
      fill.style.width = '100%';
      text.textContent = '100%';
      result.innerHTML = '<div class="alert alert-success"><i class="ti ti-circle-check"></i> 下载完成：' + esc(data.file) + '</div>';
      result.classList.remove('hidden');
      // 显示安装区域
      document.getElementById('installSection').classList.remove('hidden');
    } else {
      result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> ' + esc(data.message || '下载失败') + '</div>';
      result.classList.remove('hidden');
    }
  } catch (e) {
    result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 请求失败：' + esc(e.message) + '</div>';
    result.classList.remove('hidden');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-download"></i> 下载更新包';
}

// 安装更新
async function startInstall() {
  if (!confirm('确定要安装更新吗？系统将自动备份当前版本。')) {
    return;
  }
  const btn = document.getElementById('btnInstall');
  const result = document.getElementById('installResult');

  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> 安装中...';
  result.classList.add('hidden');

  try {
    const res = await fetch('/admin/update_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=install&file=' + encodeURIComponent(packageFile) + '&version=' + encodeURIComponent(document.getElementById('newVersion').textContent.trim()) + '&csrf_token=' + encodeURIComponent(csrfToken)
    });
    const data = await res.json();

    if (data.success) {
      result.innerHTML = '<div class="alert alert-success"><i class="ti ti-circle-check"></i> ' + esc(data.message) + '<br>新版本：' + esc(data.new_version || '未知') + '</div>';
      result.classList.remove('hidden');
      // 刷新页面以加载新版本
      setTimeout(() => location.reload(), 2000);
    } else {
      result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> ' + esc(data.message || '安装失败') + '</div>';
      result.classList.remove('hidden');
    }
  } catch (e) {
    result.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 请求失败：' + esc(e.message) + '</div>';
    result.classList.remove('hidden');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-player-play"></i> 立即安装更新';
}

// 回滚备份
async function rollback(backupDir) {
  if (!confirm('确定要回滚到该备份版本吗？当前数据将被替换。')) {
    return;
  }
  const btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i>';

  try {
    const res = await fetch('/admin/update_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=rollback&backup=' + encodeURIComponent(backupDir) + '&csrf_token=' + encodeURIComponent(csrfToken)
    });
    const data = await res.json();
    alert(data.message || (data.success ? '回滚成功' : '回滚失败'));
    if (data.success) {
      location.reload();
    }
  } catch (e) {
    alert('请求失败：' + e.message);
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-arrow-back-up"></i> 回滚';
}

// 删除备份
async function deleteBackup(backupDir) {
  if (!confirm('确定要删除该备份吗？删除后无法恢复。')) {
    return;
  }
  const btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i>';

  try {
    const res = await fetch('/admin/update_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=delete_backup&backup=' + encodeURIComponent(backupDir) + '&csrf_token=' + encodeURIComponent(csrfToken)
    });
    const data = await res.json();
    alert(data.message || (data.success ? '删除成功' : '删除失败'));
    if (data.success) {
      location.reload();
    }
  } catch (e) {
    alert('请求失败：' + e.message);
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-trash"></i> 删除';
}

function esc(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
</script>

<?php adminFooter(); ?>
