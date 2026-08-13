<?php
/**
 * 友情链接插件 - 后台管理页面
 *
 * 功能：
 *   1. 友链列表（含状态切换、排序、编辑、删除）
 *   2. 弹窗添加/编辑友链（名称+链接+CSS类名+ICO图标）
 *   3. 设置面板（标题、打开方式、最大显示数）
 *
 * 交互方式：
 *   点击「添加友链」按钮 → 弹出模态框 → 填写4个字段 → 点确定提交
 *
 * 由 admin/plugin.php?p=friendlink 分发进入此文件
 */

// 安全检查
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

$linkModel = new FriendLinkModel();
$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugin.php?p=friendlink&msg=csrf');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $data = [
                'name'       => trim($_POST['name'] ?? ''),
                'url'        => trim($_POST['url'] ?? ''),
                'css_class'  => trim($_POST['css_class'] ?? ''),
                'icon'       => trim($_POST['icon'] ?? ''),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'status'     => isset($_POST['status']) ? 1 : 0,
            ];
            if ($data['name'] === '' || $data['url'] === '') {
                $msg = '名称和链接不能为空';
                $msgType = 'error';
            } else {
                $id = $linkModel->create($data);
                $msg = $id > 0 ? "友链「{$data['name']}」已添加" : '添加失败';
                $msgType = $id > 0 ? 'success' : 'error';
            }
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'name'       => trim($_POST['name'] ?? ''),
                'url'        => trim($_POST['url'] ?? ''),
                'css_class'  => trim($_POST['css_class'] ?? ''),
                'icon'       => trim($_POST['icon'] ?? ''),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'status'     => isset($_POST['status']) ? 1 : 0,
            ];
            if ($data['name'] === '' || $data['url'] === '') {
                $msg = '名称和链接不能为空';
                $msgType = 'error';
            } else {
                $ok = $linkModel->update($id, $data);
                $msg = $ok ? "友链「{$data['name']}」已更新" : '更新失败或无变化';
                $msgType = $ok ? 'success' : 'error';
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $ok = $linkModel->delete($id);
            $msg = $ok ? '友链已删除' : '删除失败';
            $msgType = $ok ? 'success' : 'error';
            break;

        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            $link = $linkModel->getById($id);
            if ($link) {
                $newStatus = $link['status'] ? 0 : 1;
                Database::execute(
                    "UPDATE " . Database::table('friendlinks') . " SET status = ? WHERE id = ?",
                    [$newStatus, $id]
                );
                $msg = $newStatus ? "友链「{$link['name']}」已启用" : "友链「{$link['name']}」已隐藏";
            } else {
                $msg = '友链不存在';
                $msgType = 'error';
            }
            break;

        case 'save_settings':
            $title = trim($_POST['fl_title'] ?? '友情链接');
            $target = $_POST['fl_target'] ?? '_blank';
            if (!in_array($target, ['_blank', '_self'])) {
                $target = '_blank';
            }
            $maxDisplay = max(1, (int)($_POST['fl_max_display'] ?? 50));
            Plugin::setConfig('friendlink', 'title', $title);
            Plugin::setConfig('friendlink', 'target', $target);
            Plugin::setConfig('friendlink', 'max_display', (string)$maxDisplay);
            $msg = '设置已保存';
            break;
    }

    redirect('/admin/plugin.php?p=friendlink&ok=' . urlencode($msg));
}

// ========== GET 消息显示 ==========
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'csrf') {
    $msg = 'CSRF验证失败';
    $msgType = 'error';
}

// ========== 读取数据 ==========
$links = $linkModel->getAllLinks();
$totalLinks = count($links);
$activeLinks = count(array_filter($links, function ($l) { return $l['status'] == 1; }));

// 设置值
$flTitle = Plugin::config('friendlink', 'title', '友情链接');
$flTarget = Plugin::config('friendlink', 'target', '_blank');
$flMaxDisplay = (int)Plugin::config('friendlink', 'max_display', '50');

if ($msg) { adminAlert($msg, $msgType); }
?>

<!-- ========== 操作栏 ========== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header flex-between-center">
        <span class="card-title">
            <i class="ti ti-link"></i> 友情链接
            <span style="font-size:13px;font-weight:400;color:#999;margin-left:8px;">
                共 <?= $totalLinks ?> 条 · 启用 <?= $activeLinks ?> 条
            </span>
        </span>
        <div class="flex-center-gap-8">
            <button type="button" class="btn btn-primary" onclick="openLinkModal()">
                <i class="ti ti-plus"></i> 添加友链
            </button>
        </div>
    </div>

    <!-- 设置区域 -->
    <div style="padding:16px 20px;border-bottom:1px solid #e9ecef;background:#f8f9fa;">
        <form method="POST" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">区块标题</label>
                <input type="text" name="fl_title" value="<?= Security::eAttr($flTitle) ?>" class="form-input" style="width:160px;" placeholder="友情链接">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">打开方式</label>
                <select name="fl_target" class="form-input" style="width:120px;">
                    <option value="_blank" <?= $flTarget === '_blank' ? 'selected' : '' ?>>新窗口</option>
                    <option value="_self" <?= $flTarget === '_self' ? 'selected' : '' ?>>当前窗口</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">最大显示数</label>
                <input type="number" name="fl_max_display" value="<?= $flMaxDisplay ?>" class="form-input" style="width:100px;" min="1" max="500">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="ti ti-device-floppy"></i> 保存设置</button>
        </form>
    </div>

    <!-- 友链列表 -->
    <?php if (empty($links)): ?>
    <div style="padding:48px;text-align:center;color:#999;">
        <i class="ti ti-link-off" style="font-size:48px;opacity:0.3;"></i>
        <p style="margin:12px 0 0;font-size:15px;">暂无友情链接，点击右上角「添加友链」开始</p>
    </div>
    <?php else: ?>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">排序</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">名称</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">链接</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">CSS类名</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:left;">图标</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">状态</th>
                <th style="padding:10px 12px;border:1px solid #e9ecef;text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($links as $link): ?>
            <tr>
                <td style="padding:8px 12px;border:1px solid #e9ecef;text-align:center;font-weight:600;color:#999;"><?= (int)$link['sort_order'] ?></td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;font-weight:600;"><?= Security::e($link['name']) ?></td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;">
                    <a href="<?= Security::eAttr($link['url']) ?>" target="_blank" style="color:#3b82f6;"><?= Security::e($link['url']) ?></a>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;font-size:13px;font-family:monospace;">
                    <?= $link['css_class'] ? Security::e($link['css_class']) : '<span style="color:#ccc;">—</span>' ?>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;font-size:13px;">
                    <?php
                    $icon = trim($link['icon'] ?? '');
                    if ($icon === '') {
                        echo '<span style="color:#ccc;">—</span>';
                    } elseif (preg_match('#^(https?:)?/#i', $icon) || preg_match('/\.(png|jpg|jpeg|gif|svg|webp|ico)$/i', $icon)) {
                        echo '<img src="' . Security::eAttr($icon) . '" style="width:16px;height:16px;vertical-align:middle;"> <span style="color:#999;font-size:12px;">图片</span>';
                    } else {
                        $iconCls = strpos($icon, 'ti') === 0 ? $icon : 'ti ' . $icon;
                        echo '<i class="' . Security::eAttr($iconCls) . '" style="font-size:16px;"></i> <span style="color:#999;font-size:12px;">' . Security::e($icon) . '</span>';
                    }
                    ?>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;text-align:center;">
                    <?php if ($link['status']): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;background:#dcfce7;color:#16a34a;border-radius:12px;font-size:12px;font-weight:600;">显示</span>
                    <?php else: ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;background:#fee2e2;color:#ef4444;border-radius:12px;font-size:12px;font-weight:600;">隐藏</span>
                    <?php endif; ?>
                </td>
                <td style="padding:8px 12px;border:1px solid #e9ecef;text-align:center;white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick='editLink(<?= json_encode([
                        'id'        => (int)$link['id'],
                        'name'      => $link['name'],
                        'url'       => $link['url'],
                        'css_class' => $link['css_class'],
                        'icon'      => $link['icon'],
                        'sort_order'=> (int)$link['sort_order'],
                        'status'    => (int)$link['status'],
                    ], JSON_UNESCAPED_UNICODE) ?>)' title="编辑">
                        <i class="ti ti-edit"></i>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定切换显示状态？')">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" title="切换显示/隐藏">
                            <i class="ti ti-eye<?= $link['status'] ? '-off' : '' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除友链「<?= Security::eAttr($link['name']) ?>」？')">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="color:#ef4444;" title="删除">
                            <i class="ti ti-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ========== 添加/编辑友链弹窗 ========== -->
<div id="linkModal" class="fl-modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="fl-modal" style="background:#fff;border-radius:12px;padding:28px;width:480px;max-width:92%;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h3 style="font-size:18px;font-weight:700;margin:0;" id="modalTitle">添加友链</h3>
            <button type="button" onclick="closeLinkModal()" style="border:none;background:none;font-size:22px;cursor:pointer;color:#999;padding:0;line-height:1;">&times;</button>
        </div>

        <form method="POST" id="linkForm">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="id" id="field-id" value="0">

            <div class="form-group">
                <label style="font-weight:600;font-size:14px;margin-bottom:6px;display:block;">
                    名称 <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="name" id="field-name" class="form-input" placeholder="如：百度搜索" required style="width:100%;">
            </div>

            <div class="form-group">
                <label style="font-weight:600;font-size:14px;margin-bottom:6px;display:block;">
                    链接 <span style="color:#ef4444;">*</span>
                </label>
                <input type="url" name="url" id="field-url" class="form-input" placeholder="https://www.baidu.com" required style="width:100%;">
            </div>

            <div class="form-group">
                <label style="font-weight:600;font-size:14px;margin-bottom:6px;display:block;">
                    CSS 类名
                    <span style="font-weight:400;font-size:12px;color:#999;">（选填，填写则输出到 &lt;a class="..."&gt;）</span>
                </label>
                <input type="text" name="css_class" id="field-css_class" class="form-input" placeholder="如：fl-highlight fl-bold" style="width:100%;">
            </div>

            <div class="form-group">
                <label style="font-weight:600;font-size:14px;margin-bottom:6px;display:block;">
                    图标 (ICO)
                    <span style="font-weight:400;font-size:12px;color:#999;">（选填，图片URL或图标类名，填写则显示）</span>
                </label>
                <input type="text" name="icon" id="field-icon" class="form-input" placeholder="如：ti-home 或 https://example.com/favicon.ico" style="width:100%;">
                <div style="margin-top:6px;font-size:12px;color:#999;line-height:1.6;">
                    支持 Tabler 图标类名（如 <code style="background:#f1f5f9;padding:1px 4px;border-radius:3px;">ti-home</code>）或图片URL（.ico/.png/.svg）
                </div>
            </div>

            <div style="display:flex;gap:16px;">
                <div class="form-group" style="flex:1;">
                    <label style="font-weight:600;font-size:14px;margin-bottom:6px;display:block;">排序</label>
                    <input type="number" name="sort_order" id="field-sort_order" class="form-input" value="0" min="0" style="width:100%;">
                </div>
                <div class="form-group" style="flex:1;display:flex;align-items:flex-end;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;font-weight:600;">
                        <input type="checkbox" name="status" id="field-status" value="1" checked style="width:18px;height:18px;">
                        显示
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary" onclick="closeLinkModal()">取消</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-check"></i> 确定
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// 弹窗控制
function openLinkModal() {
    document.getElementById('modalTitle').textContent = '添加友链';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('field-id').value = '0';
    document.getElementById('field-name').value = '';
    document.getElementById('field-url').value = '';
    document.getElementById('field-css_class').value = '';
    document.getElementById('field-icon').value = '';
    document.getElementById('field-sort_order').value = '0';
    document.getElementById('field-status').checked = true;

    var modal = document.getElementById('linkModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
        document.getElementById('field-name').focus();
    }, 100);
}

function editLink(data) {
    document.getElementById('modalTitle').textContent = '编辑友链';
    document.getElementById('modalAction').value = 'update';
    document.getElementById('field-id').value = data.id;
    document.getElementById('field-name').value = data.name || '';
    document.getElementById('field-url').value = data.url || '';
    document.getElementById('field-css_class').value = data.css_class || '';
    document.getElementById('field-icon').value = data.icon || '';
    document.getElementById('field-sort_order').value = data.sort_order || 0;
    document.getElementById('field-status').checked = data.status === 1;

    var modal = document.getElementById('linkModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLinkModal() {
    var modal = document.getElementById('linkModal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// 点击遮罩关闭
document.getElementById('linkModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLinkModal();
    }
});

// ESC 关闭
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('linkModal');
        if (modal.style.display === 'flex') {
            closeLinkModal();
        }
    }
});
</script>
