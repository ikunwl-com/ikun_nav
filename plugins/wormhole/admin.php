<?php
/**
 * 虫洞联盟插件 - 后台管理页面
 * 提供联盟成员管理、联盟设置、检测脚本、黑名单管理
 *
 * 由 admin/plugin.php?p=wormhole 分发进入此文件
 */

// 安全检查（防止直接浏览器访问此文件）
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

$wormhole = new WormholeModel();
$siteModel = new SiteModel();
$blacklist = new BlacklistModel();
$settingsModel = new SettingsModel();

// ========== POST 操作（PRG：操作后统一跳转，防止刷新重复提交）==========
$redirectMsg = '';
$redirectTab = '';
$redirectType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugin.php?p=wormhole&msg=csrf');
    }

    $action = $_POST['action'] ?? '';
    $adminId = $_SESSION['admin_id'] ?? '未知';
    $ip = Security::getClientIP();

    // 黑名单相关操作（带 bl_ 前缀避免冲突）
    if (strpos($action, 'bl_') === 0) {
        $redirectTab = 'blacklist';
        switch ($action) {
            case 'bl_toggle_block_all_ip':
                $newValue = $_POST['block_all_ip'] ?? '0';
                $newValue = in_array($newValue, ['0', '1']) ? $newValue : '0';
                Database::execute(
                    "INSERT INTO " . Database::table('settings') . " (setting_key, setting_value) VALUES ('block_all_ip', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$newValue, $newValue]
                );
                $statusText = $newValue === '1' ? '开启' : '关闭';
                Logger::log('admin_blacklist', "全局IP屏蔽{$statusText} admin_id={$adminId} IP={$ip}");
                $redirectMsg = "全局 IP 屏蔽已{$statusText}";
                break;

            case 'bl_add':
                $input = trim($_POST['values'] ?? '');
                $remark = Security::cleanString($_POST['remark'] ?? '', 200);

                if (empty($input)) {
                    $redirectMsg = '请输入要屏蔽的值';
                    $redirectType = 'error';
                    Logger::log('admin_blacklist', "添加黑名单失败 admin_id={$adminId} IP={$ip} 原因=输入为空");
                } else {
                    $result = $blacklist->addBatchAuto($input, $remark, $adminId);
                    $parts = [];
                    if ($result['added'] > 0) $parts[] = "成功添加 {$result['added']} 条";
                    if ($result['skipped'] > 0) $parts[] = "跳过 {$result['skipped']} 条（已存在）";
                    if ($result['failed'] > 0) $parts[] = "失败 {$result['failed']} 条";
                    $redirectMsg = implode('，', $parts);
                    if ($result['added'] === 0 && $result['skipped'] === 0) {
                        $redirectType = 'error';
                        Logger::log('admin_blacklist', "添加黑名单失败 admin_id={$adminId} IP={$ip} 原因={$redirectMsg}");
                    } else {
                        Logger::log('admin_blacklist', "添加黑名单 admin_id={$adminId} IP={$ip} {$redirectMsg}");
                    }
                }
                break;

            case 'bl_delete':
                $id = Security::int($_POST['id'] ?? 0);
                if ($id > 0 && $blacklist->delete($id)) {
                    $redirectMsg = '黑名单记录已删除';
                    Logger::log('admin_blacklist', "删除黑名单 admin_id={$adminId} IP={$ip} id={$id}");
                } else {
                    $redirectMsg = '删除失败';
                    $redirectType = 'error';
                    Logger::log('admin_blacklist', "删除黑名单失败 admin_id={$adminId} IP={$ip} id={$id}");
                }
                break;

            case 'bl_batch_delete':
                $ids = $_POST['ids'] ?? [];
                if (!empty($ids) && is_array($ids)) {
                    $count = $blacklist->deleteBatch($ids);
                    $redirectMsg = "已批量删除 {$count} 条记录";
                    Logger::log('admin_blacklist', "批量删除黑名单 admin_id={$adminId} IP={$ip} count={$count}");
                } else {
                    $redirectMsg = '请选择要删除的记录';
                    $redirectType = 'error';
                    Logger::log('admin_blacklist', "批量删除黑名单失败 admin_id={$adminId} IP={$ip} 原因=未选择记录");
                }
                break;
        }
    } else {
        // 虫洞联盟原有操作
        switch ($action) {
            case 'add':
                $redirectTab = 'members';
                $siteId = Security::int($_POST['site_id'] ?? 0);
                if ($siteId > 0) {
                    if ($wormhole->joinManual($siteId)) {
                        $redirectMsg = '站点已加入虫洞联盟';
                        Logger::log('admin_wormhole', "加入联盟 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    } else {
                        $redirectMsg = '加入失败：站点不存在或未发布';
                        $redirectType = 'error';
                        Logger::log('admin_wormhole', "加入联盟失败 admin_id={$adminId} IP={$ip} site_id={$siteId} 原因=站点不存在或未发布");
                    }
                }
                break;

            case 'remove':
                $redirectTab = 'members';
                $siteId = Security::int($_POST['site_id'] ?? 0);
                if ($siteId > 0) {
                    if ($wormhole->leave($siteId)) {
                        $redirectMsg = '站点已移出虫洞联盟';
                        Logger::log('admin_wormhole', "移出联盟 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    } else {
                        $redirectMsg = '移出失败';
                        $redirectType = 'error';
                        Logger::log('admin_wormhole', "移出联盟失败 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    }
                }
                break;

            case 'approve':
                $redirectTab = 'members';
                $siteId = Security::int($_POST['site_id'] ?? 0);
                if ($siteId > 0) {
                    if ($wormhole->approve($siteId)) {
                        $redirectMsg = '审核已通过';
                        Logger::log('admin_wormhole', "审核通过 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    } else {
                        $redirectMsg = '审核失败';
                        $redirectType = 'error';
                        Logger::log('admin_wormhole', "审核通过失败 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    }
                }
                break;

            case 'reject':
                $redirectTab = 'members';
                $siteId = Security::int($_POST['site_id'] ?? 0);
                if ($siteId > 0) {
                    if ($wormhole->reject($siteId)) {
                        $redirectMsg = '已拒绝加入';
                        Logger::log('admin_wormhole', "审核拒绝 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    } else {
                        $redirectMsg = '操作失败';
                        $redirectType = 'error';
                        Logger::log('admin_wormhole', "审核拒绝失败 admin_id={$adminId} IP={$ip} site_id={$siteId}");
                    }
                }
                break;

            case 'batch_approve':
                $redirectTab = 'members';
                $ids = $_POST['site_ids'] ?? [];
                if (!empty($ids) && is_array($ids)) {
                    $count = $wormhole->approveBatch($ids);
                    $redirectMsg = "已批量通过 {$count} 个站点";
                    Logger::log('admin_wormhole', "批量审核通过 admin_id={$adminId} IP={$ip} count={$count}");
                }
                break;

            case 'save_settings':
                $redirectTab = 'settings';
                $settingsModel->set('wormhole_enable', isset($_POST['wormhole_enable']) ? '1' : '0');
                $settingsModel->set('wormhole_need_review', isset($_POST['wormhole_need_review']) ? '1' : '0');
                $settingsModel->set('wormhole_fallback_category', Security::cleanString($_POST['wormhole_fallback_category'] ?? '1', 50));
                $settingsModel->set('plugin_wormhole_rate_limit', max(0, Security::int($_POST['plugin_wormhole_rate_limit'] ?? 1)));
                $redirectMsg = '联盟设置已保存';
                Logger::log('admin_wormhole', "修改联盟设置 admin_id={$adminId} IP={$ip}");
                break;

            case 'check_all':
                $redirectTab = 'check';
                require_once __DIR__ . '/../core/cron_wormhole_check.php';
                $removed = $wormhole->removeFailedMembers();
                $redirectMsg = '全量检测完成，已清理 ' . (int)$removed . ' 个失效成员';
                Logger::log('admin_wormhole', "全量检测 admin_id={$adminId} IP={$ip} 清理失效成员={$removed}");
                break;
        }
    }

    // ========== PRG：统一跳转 GET，防止刷新重复提交 ==========
    if ($redirectMsg) {
        $url = '/admin/plugin.php?p=wormhole&tab=' . urlencode($redirectTab);
        if ($redirectType === 'error') {
            $url .= '&err=' . urlencode($redirectMsg);
        } else {
            $url .= '&ok=' . urlencode($redirectMsg);
        }
        redirect($url);
    }
    // 无操作或空操作也跳转回当前页
    redirect('/admin/plugin.php?p=wormhole');
}

// ========== 获取数据 ==========
$members = $wormhole->getMembers('all');
$stats = $wormhole->getStats();

// 黑名单数据
$tableExists = true;
try {
    $test = Database::queryOne("SELECT 1 FROM " . Database::table('blacklist') . " LIMIT 1");
} catch (Throwable $e) {
    $tableExists = false;
}
$blockAllIp = setting('block_all_ip', '0') === '1';
$filterType = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$blStats = $blacklist->getStats();
$blList = $blacklist->getAll($filterType, $search, $page, 20);

// 嵌入代码
$siteUrl = getSiteUrl();
$jsEmbed = '<script src="' . $siteUrl . '/api/index.php?endpoint=wormhole.js" async></script>';
$linkCode = '<a href="' . $siteUrl . '/wormhole/teleport.php?ref=贵站网址" target="_blank">🌌 神秘虫洞传送</a>';

// 当前激活的 Tab
$activeTab = Security::enum($_GET['tab'] ?? 'members', ['members', 'settings', 'check', 'blacklist'], 'members');

// ========== 从 URL 参数读取 PRG 消息 ==========
$msg = '';
$msgType = 'success';

if (isset($_GET['ok']) && $_GET['ok'] !== '') {
    $msg = Security::cleanString($_GET['ok'], 500);
} elseif (isset($_GET['err']) && $_GET['err'] !== '') {
    $msg = Security::cleanString($_GET['err'], 500);
    $msgType = 'error';
} elseif (isset($_GET['msg'])) {
    $msgParam = $_GET['msg'];
    if ($msgParam === 'toggle_ok') {
        $msg = '全局 IP 屏蔽设置已更新';
    } elseif ($msgParam === 'csrf') {
        $msg = 'CSRF 校验失败，请重试';
        $msgType = 'error';
    }
}

// ========== 页面渲染 ==========
if ($msg) { adminAlert($msg, $msgType); }
?>
<!-- Tab 导航 -->
<div class="settings-tabs">
  <a href="#tab-members" class="settings-tab <?= $activeTab === 'members' ? 'active' : '' ?>" onclick="switchTab('members', this)">联盟成员</a>
  <a href="#tab-settings" class="settings-tab <?= $activeTab === 'settings' ? 'active' : '' ?>" onclick="switchTab('settings', this)">联盟设置</a>
  <a href="#tab-check" class="settings-tab <?= $activeTab === 'check' ? 'active' : '' ?>" onclick="switchTab('check', this)">检测脚本</a>
  <a href="#tab-blacklist" class="settings-tab <?= $activeTab === 'blacklist' ? 'active' : '' ?>" onclick="switchTab('blacklist', this)">黑名单管理</a>
</div>

<!-- ========== Tab 1: 联盟成员 ========== -->
<div id="tab-members" class="tab-panel <?= $activeTab === 'members' ? 'active' : '' ?>">

    <!-- 统计卡片 -->
    <div class="stat-grid mb-af8d">
        <div class="stat-card">
            <div class="stat-icon icon-ad9d"><i class="ti ti-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$stats['total_count'] ?></div>
                <div class="stat-label">联盟成员</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-bg-blue"><i class="ti ti-star"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$stats['manual_count'] ?></div>
                <div class="stat-label">站长推荐</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-bf83"><i class="ti ti-bolt"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$stats['auto_count'] ?></div>
                <div class="stat-label">自动加入</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-bg-pink"><i class="ti ti-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$stats['pending_count'] ?></div>
                <div class="stat-label">待审核</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-3c3e"><i class="ti ti-refresh"></i></div>
            <div class="stat-info">
                <div class="stat-value">—</div>
                <div class="stat-label">今日检测</div>
            </div>
        </div>
    </div>

    <div class="grid-2-start">
        <!-- 左侧：联盟成员 -->
        <div class="card">
            <div class="card-header d-flex justify-between items-center">
                <span class="card-title"><i class="ti ti-users"></i> 联盟成员</span>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="check_all">
                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('即将触发全量检测，自动移出连续3次未检测到联盟代码的站点，确定继续？')"><i class="ti ti-refresh"></i> 全量检测</button>
                </form>
            </div>

            <?php
            $tab = Security::enum($_GET['subtab'] ?? 'all', ['all', 'pending'], 'all');
            $displayMembers = $tab === 'pending' ? $wormhole->getMembers('pending') : $wormhole->getMembers();
            ?>
            <div class="flex-p-gap-b500">
                <a href="?tab=members&subtab=all" class="btn btn-sm <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?> rounded-top-6">已审核</a>
                <?php if ((int)$stats['pending_count'] > 0): ?>
                <a href="?tab=members&subtab=pending" class="btn btn-sm <?= $tab === 'pending' ? 'btn-primary' : 'btn-ghost' ?> flex-center-gap-bbea">
                    待审核
                    <span class="badge badge-danger text-p-8470"><?= (int)$stats['pending_count'] ?></span>
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($displayMembers)): ?>
            <div class="empty-state p-24">
                <div class="icon"><i class="ti ti-world-question"></i></div>
                <p><?= $tab === 'pending' ? '暂无待审核站点' : '暂无联盟成员' ?></p>
                <?php if ($tab !== 'pending'): ?>
                <p class="text-mt-c639">从右侧添加站点，或通过前台嵌入代码自动加入</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <form id="batchForm" method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="batch_approve">
                <table class="data-table table-full-sm">
                    <thead>
                        <tr class="table-th">
                            <th width="30"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                            <th>站点</th>
                            <th width="70">类型</th>
                            <th width="90">来源</th>
                            <th width="80">最后检测</th>
                            <th width="50">失败</th>
                            <th width="120">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayMembers as $m):
                            $id = (int)$m['id'];
                            $name = $m['name'] ?? '';
                            $url = $m['url'] ?? '';
                            $status = $m['wormhole_status'] ?? 'none';
                            $source = $m['wormhole_source_domain'] ?? '';
                            $lastCheck = $m['wormhole_last_check'] ?? '';
                            $failCount = (int)($m['wormhole_check_fail'] ?? 0);
                            $domain = getDisplayDomain($url);
                        ?>
                        <tr class="tr-border">
                            <td class="py-10 px-6"><input type="checkbox" name="site_ids[]" value="<?= $id ?>"></td>
                            <td class="py-10 px-6">
                                <div class="fw-600"><?= Security::e($name) ?></div>
                                <div class="text-xs-dim-3"><?= Security::e($domain) ?></div>
                            </td>
                            <td class="py-10 px-6">
                                <?php if ($status === 'manual'): ?>
                                <span class="badge badge-info">站长推荐</span>
                                <?php elseif ($status === 'pending'): ?>
                                <span class="badge badge-warning">待审核</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">自动</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-p-5cd4"><?= Security::e($source) ?: '—' ?></td>
                            <td class="text-p-5cd4"><?= Security::e($lastCheck) ?: '—' ?></td>
                            <td class="py-10 px-6">
                                <?php if ($failCount > 0): ?>
                                <span class="badge badge-danger"><?= (int)$failCount ?></span>
                                <?php else: ?>
                                <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-10 px-6">
                                <?php if ($status === 'pending'): ?>
                                <div class="flex-gap-4">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="site_id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-sm btn-success">通过</button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定拒绝该站点加入联盟？')">
                                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="site_id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">拒绝</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('确定将该站点移出虫洞联盟？')">
                                    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="site_id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">移出</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php if ($tab === 'pending' && count($displayMembers) > 1): ?>
            <div class="px-16-py-12-border-top">
                <button type="button" class="btn btn-primary" onclick="submitBatchApprove()">批量通过</button>
            </div>
            <script>
            function toggleAll(cb) {
                var items = document.querySelectorAll('input[name="site_ids[]"]');
                for (var i = 0; i < items.length; i++) { items[i].checked = cb.checked; }
            }
            function submitBatchApprove() {
                var checked = document.querySelectorAll('input[name="site_ids[]"]:checked');
                if (checked.length === 0) { alert('请至少选择一个站点'); return; }
                if (!confirm('确定批量通过 ' + checked.length + ' 个站点？')) return;
                document.getElementById('batchForm').submit();
            }
            </script>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 右侧：添加站点 + 嵌入代码 -->
        <div>
            <!-- 添加站点 -->
            <div class="card mb-20">
                <div class="card-header">
                    <span class="card-title"><i class="ti ti-plus"></i> 添加站点</span>
                </div>
                <div class="p-16">
                    <form method="GET" class="flex-mb-gap-e274">
                        <input type="text" name="q" value="<?= Security::eAttr($_GET['q'] ?? '') ?>" placeholder="搜索站点名称或域名..." class="form-input flex-1">
                        <input type="hidden" name="tab" value="members">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-search"></i> 搜索</button>
                    </form>

                    <?php
                    $q = trim($_GET['q'] ?? '');
                    $searchResults = [];
                    if ($q !== '') {
                        $like = '%' . $q . '%';
                        $searchResults = Database::query(
                            "SELECT id, name, url, br_pc, br_mobile
                             FROM " . table('sites') . "
                             WHERE status = 'published'
                               AND (name LIKE ? OR url LIKE ?)
                               AND (wormhole_status = 'none' OR wormhole_status IS NULL OR wormhole_status = '' OR wormhole_status = 'pending')
                             ORDER BY br_pc DESC, br_mobile DESC
                             LIMIT 20",
                            [$like, $like]
                        );
                    }
                    ?>

                    <?php if ($q === ''): ?>
                    <div class="empty-center-md">
                        <i class="ti ti-search icon-32-block"></i>
                        <p class="fs-13">输入关键词搜索站点</p>
                    </div>
                    <?php elseif (empty($searchResults)): ?>
                    <div class="empty-center-md">
                        <i class="ti ti-mood-empty icon-32-block"></i>
                        <p class="fs-13">未找到匹配的站点</p>
                        <p class="text-mt-1357">可能已加入联盟，或不存在该站点</p>
                    </div>
                    <?php else: ?>
                    <div class="style-d6f8">
                        <p class="text-mb-0bdc">共 <?= count($searchResults) ?> 个结果</p>
                        <?php foreach ($searchResults as $s):
                            $id = (int)$s['id'];
                            $name = $s['name'] ?? '';
                            $url = $s['url'] ?? '';
                            $brPc = (int)($s['br_pc'] ?? 0);
                            $brMobile = (int)($s['br_mobile'] ?? 0);
                            $domain = getDisplayDomain($url);
                        ?>
                        <div class="flex-center-bg-mb-p-gap-4b11">
                            <div class="flex-1 text-ellipsis">
                                <div class="text-sm-bold"><?= Security::e($name) ?></div>
                                <div class="text-xs-dim-3"><?= Security::e($domain) ?></div>
                            </div>
                            <span class="badge badge-info">BR<?= $brPc ?></span>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="site_id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('确定将「<?= Security::eAttr($name) ?>」加入虫洞联盟？')"><i class="ti ti-plus"></i> 加入</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 嵌入代码 -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="ti ti-code"></i> 联盟嵌入代码</span>
                </div>
                <div class="mb-16">
                    <label class="label-block">JS 嵌入（推荐）</label>
                    <div class="code-block"><?= Security::e($jsEmbed) ?></div>
                    <button type="button" class="btn btn-sm btn-secondary mt-6" onclick='copyCode(this, <?= json_encode($jsEmbed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>复制代码</button>
                </div>
                <div>
                    <label class="label-block">传送友链</label>
                    <div class="code-block"><?= Security::e($linkCode) ?></div>
                    <button type="button" class="btn btn-sm btn-secondary mt-6" onclick='copyCode(this, <?= json_encode($linkCode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>复制代码</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== Tab 2: 联盟设置 ========== -->
<div id="tab-settings" class="tab-panel <?= $activeTab === 'settings' ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="ti ti-settings"></i> 联盟设置</span>
        </div>
    <?php
    $wormholeEnable = $settingsModel->get('wormhole_enable', '1') === '1';
    $wormholeNeedReview = $settingsModel->get('wormhole_need_review', '0') === '1';
    $rateLimit = (int)$settingsModel->get('plugin_wormhole_rate_limit', '1');
    ?>
    <form method="POST" class="p-16">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="save_settings">

        <div class="form-group">
            <label class="flex-center-gap-10">
                <input type="checkbox" name="wormhole_enable" value="1" <?= $wormholeEnable ? 'checked' : '' ?> class="wh-18">
                <span><strong>启用联盟</strong></span>
            </label>
            <div class="form-help">关闭后前台不再响应虫洞联盟自动加入请求</div>
        </div>

        <div class="form-group">
            <label class="flex-center-gap-10">
                <input type="checkbox" name="wormhole_need_review" value="1" <?= $wormholeNeedReview ? 'checked' : '' ?> class="wh-18">
                <span><strong>新成员需审核</strong></span>
            </label>
            <div class="form-help">开启后自动加入的站点需管理员审核后才显示在联盟中</div>
        </div>

        <div class="form-group">
            <label>兜底分类</label>
            <input type="text" name="wormhole_fallback_category" value="<?= Security::eAttr($settingsModel->get('wormhole_fallback_category', '1')) ?>" placeholder="ID 或 slug" class="form-input" style="max-width:200px">
            <div class="form-help">自动加入联盟时，站点默认归入的分类 ID 或别名</div>
        </div>

        <div class="form-group">
            <label>自动加入频率限制（次/小时）</label>
            <input type="number" class="form-input" name="plugin_wormhole_rate_limit" value="<?= $rateLimit ?>" min="0" max="100" style="max-width:200px">
            <div class="form-help">同一域名每小时最多自动加入联盟次数，0=不限制</div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
    </form>
    </div>
</div>

<!-- ========== Tab 3: 检测脚本 ========== -->
<div id="tab-check" class="tab-panel <?= $activeTab === 'check' ? 'active' : '' ?>">
    <div class="card mb-20">
        <div class="card-header">
            <span class="card-title"><i class="ti ti-terminal"></i> 宝塔计划任务设置</span>
        </div>
        <div class="p-16">
            <p class="mb-12">建议每 6~12 小时执行一次全量检测，自动清理已移除联盟代码的站点。</p>
            <div class="flex-gap-8-wrap-center-mb-8">
                <span class="fw-600">脚本路径：</span>
                <code class="icon-text-p-6bb6"><?= Security::e($_SERVER['DOCUMENT_ROOT'] . '/core/cron_wormhole_check.php') ?></code>
                <button type="button" class="btn btn-sm btn-secondary" onclick="copyPath(this)">复制路径</button>
            </div>
            <div class="flex-gap-8-wrap-center">
                <span class="fw-600">宝塔命令示例：</span>
                <code class="icon-text-p-6bb6">php <?= Security::e($_SERVER['DOCUMENT_ROOT']) ?>/core/cron_wormhole_check.php</code>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="ti ti-refresh"></i> 手动全量检测</span>
        </div>
        <div class="p-16">
            <p class="mb-12">立即触发一次全量检测，自动移出连续 3 次未检测到联盟代码的站点。</p>
            <form method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="check_all">
                <button type="submit" class="btn btn-primary" onclick="return confirm('即将触发全量检测，自动移出连续3次未检测到联盟代码的站点，确定继续？')"><i class="ti ti-refresh"></i> 立即全量检测</button>
            </form>
        </div>
    </div>
</div>

<!-- ========== Tab 4: 黑名单管理 ========== -->
<div id="tab-blacklist" class="tab-panel <?= $activeTab === 'blacklist' ? 'active' : '' ?>">

    <?php if (!$tableExists): ?>
    <div class="card bg-mb-58e0">
        <div class="card-header">
            <span class="card-title"><i class="ti ti-alert-circle text-orange"></i> 需要升级</span>
        </div>
        <div class="p-16">
            <p class="text-mt-88d4">检测到 `blacklist` 表尚未创建，请先执行升级脚本完成数据库更新。</p>
            <a href="/install/upgrade_v2.php" class="btn btn-primary mt-12">
                <i class="ti ti-rocket"></i> 立即升级
            </a>
        </div>
    </div>
    <?php else: ?>

    <!-- 统计卡片 -->
    <div class="stat-grid mb-2bb3">
        <div class="stat-card stat-card-inner">
            <div class="stat-icon icon-mb-w-b9ee"><i class="ti ti-shield-off"></i></div>
            <div>
                <div class="stat-value mb-2"><?= (int)$blStats['total_count'] ?></div>
                <div class="stat-label">黑名单总数</div>
            </div>
        </div>
        <div class="stat-card stat-card-inner">
            <div class="stat-icon icon-mb-w-401d"><i class="ti ti-world"></i></div>
            <div>
                <div class="stat-value mb-2"><?= (int)$blStats['domain_count'] ?></div>
                <div class="stat-label">域名屏蔽</div>
            </div>
        </div>
        <div class="stat-card stat-card-inner">
            <div class="stat-icon" style="background:<?= $blockAllIp ? '#fee2e2' : '#f3f4f6' ?>;color:<?= $blockAllIp ? '#ef4444' : '#6b7280' ?>">
                <i class="ti ti-<?= $blockAllIp ? 'shield-check' : 'shield-off' ?>"></i>
            </div>
            <div>
                <div class="stat-value mb-2">
                    <?= $blockAllIp
                        ? '<span class="text-dd0f">全部</span>'
                        : (int)$blStats['ip_count']
                    ?>
                </div>
                <div class="stat-label"><?= $blockAllIp ? '全局屏蔽中' : 'IP 屏蔽' ?></div>
            </div>
        </div>
    </div>

    <!-- 全局 IP 屏蔽开关 -->
    <div class="card mb-20" style="background:<?= $blockAllIp ? '#fef2f2' : '#f0fdf4' ?>;border-left:4px solid <?= $blockAllIp ? '#ef4444' : '#22c55e' ?>">
        <div class="card-header flex-between">
            <div>
                <span class="card-title"><i class="ti ti-toggle-<?= $blockAllIp ? 'right' : 'left' ?>"></i> 全局 IP 屏蔽</span>
                <p class="text-dim-mt-4">
                    <?= $blockAllIp
                        ? '已开启：所有纯 IP 地址的自动收录和自动加入联盟将被拒绝，仅域名可通过'
                        : '已关闭：纯 IP 地址和域名均可触发自动收录和加入联盟（黑名单仍生效）'
                    ?>
                </p>
            </div>
            <form method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="bl_toggle_block_all_ip">
                <input type="hidden" name="block_all_ip" value="<?= $blockAllIp ? '0' : '1' ?>">
                <button type="submit" class="btn btn-<?= $blockAllIp ? 'danger' : 'success' ?> w-0b55">
                    <i class="ti ti-power"></i> <?= $blockAllIp ? '关闭屏蔽' : '开启屏蔽' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- 添加黑名单 -->
    <div class="card mb-24">
        <div class="card-header">
            <span class="card-title"><i class="ti ti-plus"></i> 添加黑名单</span>
        </div>
        <form method="POST" class="p-16">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="bl_add">

            <div class="mb-12">
                <textarea name="values" placeholder="输入要屏蔽的 IP 或域名，多个值用逗号、空格或换行分隔&#10;例如：&#10;example.com&#10;192.168.1.1"
                    class="textarea-w-full-h-120"
                    required></textarea>
                <p class="text-dim-mt-4">
                    <i class="ti ti-info-circle"></i> 自动识别类型：纯数字加点的格式识别为 IP，其他识别为域名
                </p>
            </div>

            <div class="mb-12">
                <input type="text" name="remark" placeholder="备注说明（可选）..."
                    class="input-w-full-sm">
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> 添加屏蔽</button>
        </form>
    </div>

    <!-- 黑名单列表 -->
    <div class="card">
        <div class="card-header flex-between">
            <span class="card-title"><i class="ti ti-shield-off"></i> 黑名单列表</span>

            <div class="flex-center-gap-faaa">
                <form method="GET" class="flex-gap-8">
                    <input type="hidden" name="tab" value="blacklist">
                    <select name="type" class="input-sm-cbd">
                        <option value="">全部类型</option>
                        <option value="domain" <?= $filterType === 'domain' ? 'selected' : '' ?>>域名</option>
                        <option value="ip" <?= $filterType === 'ip' ? 'selected' : '' ?>>IP</option>
                    </select>
                    <input type="text" name="search" value="<?= Security::eAttr($search) ?>" placeholder="搜索..." class="text-p-w-93e6">
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="ti ti-search"></i></button>
                </form>
            </div>
        </div>

        <form id="batchDeleteForm" method="POST" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="bl_batch_delete">

            <table class="data-table table-full-sm">
                <thead>
                    <tr class="table-th">
                        <th width="30"><input type="checkbox" id="checkAllBl" onclick="toggleAllBl(this)"></th>
                        <th width="80">类型</th>
                        <th>值</th>
                        <th>备注</th>
                        <th width="160">添加时间</th>
                        <th width="80">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blList['items'])): ?>
                    <tr>
                        <td colspan="6" class="empty-center-lg">
                            <i class="ti ti-shield-check icon-32-block"></i>
                            <p>暂无黑名单记录</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($blList['items'] as $item):
                        $id = (int)$item['id'];
                        $type = $item['type'] ?? 'domain';
                        $value = $item['value'] ?? '';
                        $remark = $item['remark'] ?? '';
                        $createdAt = $item['created_at'] ?? '';
                    ?>
                    <tr class="tr-border">
                        <td class="td-compact"><input type="checkbox" name="ids[]" value="<?= $id ?>"></td>
                        <td class="td-compact">
                            <?php if ($type === 'ip'): ?>
                            <span class="badge badge-danger">IP</span>
                            <?php else: ?>
                            <span class="badge badge-warning">域名</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-p-abd2">
                            <?= Security::e($value) ?>
                        </td>
                        <td class="text-p-892b">
                            <?= Security::e($remark) ?: '&#8212;' ?>
                        </td>
                        <td class="text-p-3310">
                            <?= Security::e($createdAt) ?>
                        </td>
                        <td class="td-compact">
                            <form method="POST" class="d-inline" onsubmit="return confirm('确定删除此黑名单记录？')">
                                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action" value="bl_delete">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i> 删除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>

        <?php if (!empty($blList['items']) && $blList['totalPages'] > 1): ?>
        <div class="flex-between-p-12-16-border-top">
            <div>
                <?php if ($blList['total'] > 0): ?>
                <button type="button" class="btn btn-sm btn-danger" onclick="submitBatchDelete()">
                    <i class="ti ti-trash"></i> 批量删除
                </button>
                <?php endif; ?>
            </div>
            <div class="flex-gap-4">
                <?php if ($page > 1): ?>
                <a href="?tab=blacklist&page=<?= $page - 1 ?>&type=<?= $filterType ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-ghost">上一页</a>
                <?php endif; ?>
                <span class="px-6-py-12-text-muted">
                    <?= $page ?> / <?= $blList['totalPages'] ?> 页（共 <?= $blList['total'] ?> 条）
                </span>
                <?php if ($page < $blList['totalPages']): ?>
                <a href="?tab=blacklist&page=<?= $page + 1 ?>&type=<?= $filterType ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-ghost">下一页</a>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif (!empty($blList['items'])): ?>
        <div class="px-16-py-12-border-top">
            <button type="button" class="btn btn-sm btn-danger" onclick="submitBatchDelete()">
                <i class="ti ti-trash"></i> 批量删除
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTab(tabId, el) {
    // 隐藏所有 panel
    document.querySelectorAll('.tab-panel').forEach(function(p) {
        p.classList.remove('active');
    });
    // 显示目标 panel
    document.getElementById('tab-' + tabId).classList.add('active');
    // 更新 tab 样式
    document.querySelectorAll('.settings-tab').forEach(function(t) {
        t.classList.remove('active');
    });
    if (el) el.classList.add('active');
    // 更新 URL hash，便于刷新后保持当前 Tab
    if (history.replaceState) {
        history.replaceState(null, null, '?tab=' + tabId);
    }
}

function copyCode(btn, code) {
    var text = code;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.textContent;
            btn.textContent = '已复制';
            btn.classList.add('btn-success');
            setTimeout(function() {
                btn.textContent = orig;
                btn.classList.remove('btn-success');
            }, 2000);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        var orig = btn.textContent;
        btn.textContent = '已复制';
        setTimeout(function() { btn.textContent = orig; }, 2000);
    }
}

function copyPath(btn) {
    var path = btn.previousElementSibling.textContent.trim();
    copyCode(btn, path);
}

function toggleAllBl(cb) {
    var items = document.querySelectorAll('input[name="ids[]"]');
    for (var i = 0; i < items.length; i++) { items[i].checked = cb.checked; }
}
function submitBatchDelete() {
    var checked = document.querySelectorAll('input[name="ids[]"]:checked');
    if (checked.length === 0) { alert('请至少选择一条记录'); return; }
    if (!confirm('确定批量删除 ' + checked.length + ' 条黑名单记录？')) return;
    document.getElementById('batchDeleteForm').submit();
}
</script>

