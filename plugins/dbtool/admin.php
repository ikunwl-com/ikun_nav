<?php
/**
 * 数据库备份插件 - 管理页面
 * 通过 /admin/plugin.php?p=dbtool 访问
 * 本文件由分发器加载，adminHeader()/adminFooter() 已由分发器处理
 *
 * 功能：
 *   Tab 1 备份数据库：一键备份 + 备份文件列表（下载/删除）
 *   Tab 2 恢复数据库：从备份文件恢复 + 上传 SQL 文件导入
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// 加载核心函数
$includeFile = __DIR__ . '/include.php';
if (file_exists($includeFile)) {
    require_once $includeFile;
}

// ========== POST 处理 ==========
$msg = '';
$msgType = 'success';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'backup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        redirect('/admin/plugin.php?p=dbtool&err=' . urlencode('CSRF验证失败'));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'backup') {
        // 备份数据库
        $result = dbtool_backup();
        if ($result['ok']) {
            $msg = "备份成功！文件 {$result['file']}，包含 {$result['tables']} 张表、{$result['rows']} 行数据，大小 " . dbtool_formatSize($result['size']);
            $activeTab = 'backup';
        } else {
            $msg = $result['error'];
            $msgType = 'error';
            $activeTab = 'backup';
        }
    } elseif ($action === 'delete') {
        // 删除备份
        $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
        $result = dbtool_delete($filename);
        if ($result['ok']) {
            $msg = "备份文件 {$filename} 已删除";
            $activeTab = 'backup';
        } else {
            $msg = $result['error'];
            $msgType = 'error';
            $activeTab = 'backup';
        }
    } elseif ($action === 'restore') {
        // 恢复数据库
        $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
        if (!dbtool_validateFilename($filename)) {
            $msg = '文件名不合法';
            $msgType = 'error';
            $activeTab = 'restore';
        } else {
            $filepath = dbtool_backupDir() . DIRECTORY_SEPARATOR . $filename;
            $result = dbtool_restore($filepath);
            if ($result['ok']) {
                $msg = "恢复成功！执行了 {$result['statements']} 条 SQL 语句";
                if ($result['error']) {
                    $msg .= '，但有部分错误: ' . $result['error'];
                    $msgType = 'error';
                }
                $activeTab = 'restore';
            } else {
                $msg = $result['error'];
                $msgType = 'error';
                $activeTab = 'restore';
            }
        }
    } elseif ($action === 'import') {
        // 上传 SQL 文件并导入
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = '请选择要上传的 SQL 文件';
            $msgType = 'error';
            $activeTab = 'restore';
        } else {
            $uploadResult = dbtool_handleUpload($_FILES['sql_file']);
            if (!$uploadResult['ok']) {
                $msg = $uploadResult['error'];
                $msgType = 'error';
                $activeTab = 'restore';
            } else {
                // 导入
                $restoreResult = dbtool_restore($uploadResult['filepath']);
                if ($restoreResult['ok']) {
                    $msg = "导入成功！文件 {$uploadResult['filename']}，执行了 {$restoreResult['statements']} 条 SQL 语句";
                    if ($restoreResult['error']) {
                        $msg .= '，但有部分错误: ' . $restoreResult['error'];
                        $msgType = 'error';
                    }
                } else {
                    $msg = $restoreResult['error'];
                    $msgType = 'error';
                }
                $activeTab = 'restore';
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

// 获取数据库信息
$dbInfo = [
    'tables' => 0,
    'rows' => 0,
    'size' => 0,
];
try {
    $tables = dbtool_getTables();
    $dbInfo['tables'] = count($tables);
    $totalRows = 0;
    foreach ($tables as $tbl) {
        $totalRows += dbtool_getRowCount($tbl);
    }
    $dbInfo['rows'] = $totalRows;
} catch (\Exception $e) {}

// 获取备份列表
$backups = dbtool_listBackups();
$backupCount = count($backups);
$totalBackupSize = 0;
foreach ($backups as $b) {
    $totalBackupSize += $b['size'];
}

// CSRF token
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

// 下载 URL 基础路径
$downloadBase = '/plugins/dbtool/download.php';
?>

<?php if ($msg): ?>
  <?php adminAlert($msg, $msgType); ?>
<?php endif; ?>

<!-- 数据库概览卡片 -->
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
    <div style="flex:1;min-width:180px;background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:8px;background:#eef;color:#667eea;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="ti ti-database"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#999;">数据表</div>
                <div style="font-size:20px;font-weight:700;color:#333;"><?= $dbInfo['tables'] ?> 张</div>
            </div>
        </div>
    </div>
    <div style="flex:1;min-width:180px;background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:8px;background:#f0fdf4;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="ti ti-table-row"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#999;">数据行</div>
                <div style="font-size:20px;font-weight:700;color:#333;"><?= number_format($dbInfo['rows']) ?> 行</div>
            </div>
        </div>
    </div>
    <div style="flex:1;min-width:180px;background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:8px;background:#fef3f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="ti ti-file-zip"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#999;">备份文件</div>
                <div style="font-size:20px;font-weight:700;color:#333;"><?= $backupCount ?> 个</div>
            </div>
        </div>
    </div>
    <div style="flex:1;min-width:180px;background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:8px;background:#fffbeb;color:#ca8a04;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="ti ti-device-floppy"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#999;">备份占用</div>
                <div style="font-size:20px;font-weight:700;color:#333;"><?= dbtool_formatSize($totalBackupSize) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tab 切换 -->
<div class="card">
    <div style="display:flex;border-bottom:1px solid #e9ecef;">
        <button class="dbtool-tab <?= $activeTab === 'backup' ? 'active' : '' ?>" onclick="switchTab('backup')">
            <i class="ti ti-arrow-up"></i> 备份数据库
        </button>
        <button class="dbtool-tab <?= $activeTab === 'restore' ? 'active' : '' ?>" onclick="switchTab('restore')">
            <i class="ti ti-arrow-down"></i> 恢复数据库
        </button>
    </div>

    <style>
    .dbtool-tab {
        padding:12px 24px;
        border:none;
        background:none;
        cursor:pointer;
        font-size:14px;
        font-weight:500;
        color:#999;
        border-bottom:2px solid transparent;
        transition:all .2s;
        display:flex;
        align-items:center;
        gap:6px;
    }
    .dbtool-tab:hover { color:#555; }
    .dbtool-tab.active {
        color:#667eea;
        border-bottom-color:#667eea;
    }
    .dbtool-tab-content { display:none; }
    .dbtool-tab-content.active { display:block; }
    </style>

    <!-- ==================== Tab 1: 备份数据库 ==================== -->
    <div class="dbtool-tab-content <?= $activeTab === 'backup' ? 'active' : '' ?>" id="tab-backup">
        <div style="padding:20px;">
            <!-- 备份操作 -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <div>
                    <h4 style="margin:0;font-size:16px;color:#333;"><i class="ti ti-device-floppy"></i> 创建备份</h4>
                    <p style="margin:4px 0 0 0;font-size:13px;color:#999;">点击下方按钮，将当前数据库的所有表结构和数据导出为 SQL 备份文件。</p>
                </div>
                <form method="POST" onsubmit="return confirm('确定立即备份数据库？可能需要数秒，请勿关闭页面。')">
                    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary" style="padding:8px 20px;">
                        <i class="ti ti-database-export"></i> 立即备份
                    </button>
                </form>
            </div>
        </div>

        <!-- 备份列表 -->
        <div style="border-top:1px solid #e9ecef;">
            <div style="padding:12px 20px;font-size:14px;font-weight:600;color:#333;background:#f8f9fa;">
                备份文件列表 <span style="font-weight:400;color:#999;font-size:12px;">（共 <?= $backupCount ?> 个，占用 <?= dbtool_formatSize($totalBackupSize) ?>）</span>
            </div>

            <?php if (empty($backups)): ?>
                <div style="padding:60px 20px;text-align:center;color:#999;font-size:14px;">
                    <i class="ti ti-database-off" style="font-size:42px;display:block;margin-bottom:12px;"></i>
                    暂无备份文件，点击上方"立即备份"创建第一个备份
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;font-size:13px;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">文件名</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">大小</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">表</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">数据行</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">备份时间</th>
                                <th style="padding:10px 12px;text-align:center;white-space:nowrap;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $idx => $b): ?>
                            <tr <?= $idx % 2 === 1 ? 'style="background:#fafbfc;"' : '' ?>>
                                <td style="padding:8px 12px;">
                                    <i class="ti ti-file-text" style="color:#667eea;"></i>
                                    <?= Security::e($b['filename']) ?>
                                </td>
                                <td style="padding:8px 12px;color:#555;"><?= $b['size_text'] ?></td>
                                <td style="padding:8px 12px;color:#555;"><?= $b['tables'] ?> 张</td>
                                <td style="padding:8px 12px;color:#555;"><?= number_format($b['rows']) ?> 行</td>
                                <td style="padding:8px 12px;color:#999;font-size:12px;white-space:nowrap;"><?= $b['created_at'] ?></td>
                                <td style="padding:8px 12px;text-align:center;white-space:nowrap;">
                                    <a href="<?= Security::eAttr($downloadBase . '?file=' . urlencode($b['filename'])) ?>"
                                       class="btn btn-sm" style="color:#2563eb;border:1px solid #93c5fd;background:#eff6ff;padding:2px 10px;margin-right:4px;" title="下载到本地">
                                        <i class="ti ti-download"></i> 下载
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除备份文件 <?= Security::eAttr($b['filename']) ?> ？此操作不可恢复。')">
                                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="filename" value="<?= Security::eAttr($b['filename']) ?>">
                                        <button type="submit" class="btn btn-sm" style="color:#dc2626;border:1px solid #fca5a5;background:#fef2f2;padding:2px 10px;" title="删除备份">
                                            <i class="ti ti-trash"></i> 删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== Tab 2: 恢复数据库 ==================== -->
    <div class="dbtool-tab-content <?= $activeTab === 'restore' ? 'active' : '' ?>" id="tab-restore">
        <!-- 恢复警告 -->
        <div style="margin:20px;padding:12px 16px;background:#fff3cd;border:1px solid #ffeaa7;border-radius:6px;font-size:13px;color:#856404;">
            <i class="ti ti-alert-triangle"></i>
            <strong>注意：</strong>恢复操作将覆盖当前数据库中的数据。建议在恢复前先创建一次备份。恢复过程中请勿关闭页面。
        </div>

        <!-- 从备份文件恢复 -->
        <div style="padding:0 20px 20px;">
            <h4 style="margin:0 0 12px;font-size:16px;color:#333;"><i class="ti ti-history"></i> 从备份文件恢复</h4>
            <p style="margin:0 0 16px;font-size:13px;color:#999;">选择一个已有的备份文件，恢复数据库到该备份时的状态。</p>

            <?php if (empty($backups)): ?>
                <div style="padding:40px;text-align:center;color:#999;font-size:14px;border:1px dashed #e9ecef;border-radius:8px;">
                    <i class="ti ti-database-off" style="font-size:36px;display:block;margin-bottom:10px;"></i>
                    暂无备份文件可恢复
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;font-size:13px;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">文件名</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">大小</th>
                                <th style="padding:10px 12px;text-align:left;white-space:nowrap;">备份时间</th>
                                <th style="padding:10px 12px;text-align:center;white-space:nowrap;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $idx => $b): ?>
                            <tr <?= $idx % 2 === 1 ? 'style="background:#fafbfc;"' : '' ?>>
                                <td style="padding:8px 12px;">
                                    <i class="ti ti-file-text" style="color:#16a34a;"></i>
                                    <?= Security::e($b['filename']) ?>
                                </td>
                                <td style="padding:8px 12px;color:#555;"><?= $b['size_text'] ?></td>
                                <td style="padding:8px 12px;color:#999;font-size:12px;white-space:nowrap;"><?= $b['created_at'] ?></td>
                                <td style="padding:8px 12px;text-align:center;white-space:nowrap;">
                                    <form method="POST" style="display:inline;" onsubmit="return confirmRestore('<?= Security::eAttr($b['filename']) ?>')">
                                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="filename" value="<?= Security::eAttr($b['filename']) ?>">
                                        <button type="submit" class="btn btn-sm" style="color:#16a34a;border:1px solid #86efac;background:#f0fdf4;padding:4px 14px;" title="恢复此备份">
                                            <i class="ti ti-arrow-back-up"></i> 恢复
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 上传 SQL 文件导入 -->
        <div style="border-top:1px solid #e9ecef;padding:20px;">
            <h4 style="margin:0 0 12px;font-size:16px;color:#333;"><i class="ti ti-upload"></i> 导入 SQL 文件</h4>
            <p style="margin:0 0 16px;font-size:13px;color:#999;">上传一个 .sql 文件并导入到数据库（最大 50MB）。导入将执行文件中的所有 SQL 语句。</p>

            <form method="POST" enctype="multipart/form-data" onsubmit="return confirmImport()" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
                <input type="hidden" name="action" value="import">
                <div class="form-group" style="margin:0;flex:1;min-width:300px;">
                    <input type="file" name="sql_file" accept=".sql" required style="padding:8px;border:1px solid #ddd;border-radius:6px;font-size:14px;width:100%;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;">
                    <i class="ti ti-database-import"></i> 上传并导入
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.dbtool-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.dbtool-tab-content').forEach(function(c) { c.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    // 更新 URL 中的 tab 参数（不刷新页面）
    var url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
}

function confirmRestore(filename) {
    return confirm('确定要恢复数据库吗？\n\n' +
        '备份文件：' + filename + '\n\n' +
        '此操作将覆盖当前数据库中的所有数据！\n' +
        '建议在恢复前先创建备份。\n\n' +
        '恢复过程中请勿关闭页面。');
}

function confirmImport() {
    return confirm('确定要导入上传的 SQL 文件吗？\n\n' +
        '此操作将执行文件中的所有 SQL 语句，可能覆盖现有数据。\n' +
        '建议在导入前先创建备份。\n\n' +
        '导入过程中请勿关闭页面。');
}
</script>
