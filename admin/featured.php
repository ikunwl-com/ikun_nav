<?php
/**
 * 后台推荐管理 - 重写版
 * 按分类显示站点，一键设置推荐
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'featured';

$catModel = new CategoryModel();
$featureModel = new FeatureModel();

$msg = $_GET['msg'] ?? '';
$catId = Security::int($_GET['cat'] ?? 0);

// ========== POST 操作 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/featured.php?msg=csrf');
    }

    $action = $_POST['action'] ?? '';
    $categoryId = Security::int($_POST['category_id'] ?? 0);

    $adminId = $_SESSION['admin_id'] ?? '未知';
    $ip = Security::getClientIP();

    switch ($action) {
        case 'add':
            $siteId = Security::int($_POST['site_id'] ?? 0);
            if ($siteId > 0 && $categoryId > 0) {
                $featureModel->add($siteId, $categoryId);
                Logger::log('admin_feature', "设置推荐 admin_id={$adminId} IP={$ip} site_id={$siteId} cat_id={$categoryId}");
            }
            redirect('/admin/featured.php?cat=' . $categoryId . '&msg=added');
            break;

        case 'remove':
            $siteId = Security::int($_POST['site_id'] ?? 0);
            if ($siteId > 0 && $categoryId > 0) {
                $featureModel->remove($siteId, $categoryId);
                Logger::log('admin_feature', "取消推荐 admin_id={$adminId} IP={$ip} site_id={$siteId} cat_id={$categoryId}");
            }
            redirect('/admin/featured.php?cat=' . $categoryId . '&msg=removed');
            break;

        case 'reorder':
            $orders = $_POST['orders'] ?? [];
            $count = 0;
            foreach ($orders as $siteId => $order) {
                Database::execute(
                    "UPDATE " . table('site_features') . " SET feature_order = ? WHERE site_id = ? AND category_id = ?",
                    [(int)$order, (int)$siteId, $categoryId]
                );
                $count++;
            }
            Logger::log('admin_feature', "保存推荐排序 admin_id={$adminId} IP={$ip} cat_id={$categoryId} 更新了{$count}条");
            redirect('/admin/featured.php?cat=' . $categoryId . '&msg=reordered');
            break;
    }
}

// ========== 获取数据 ==========
$categories = $catModel->getAll();

// 默认选中第一个分类
if ($catId <= 0 && !empty($categories)) {
    $catId = (int)$categories[0]['id'];
}

$currentCat = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === $catId) {
        $currentCat = $c;
        break;
    }
}

// 当前推荐
$featuredSites = [];
if ($catId > 0) {
    $featuredSites = Database::query(
        "SELECT s.*, sf.feature_order FROM " . table('sites') . " s
         INNER JOIN " . table('site_features') . " sf ON s.id = sf.site_id
         WHERE sf.category_id = ? AND s.status = 'published'
         ORDER BY sf.feature_order ASC",
        [$catId]
    );
}

// 可选站点（已发布但未推荐的）
$availableSites = [];
if ($catId > 0) {
    $availableSites = Database::query(
        "SELECT s.* FROM " . table('sites') . " s
         WHERE s.category_id = ? AND s.status = 'published'
           AND s.id NOT IN (
               SELECT site_id FROM " . table('site_features') . " WHERE category_id = ?
           )
         ORDER BY s.br_pc DESC, s.br_mobile DESC",
        [$catId, $catId]
    );
}

// ========== 页面渲染 ==========
adminHeader('推荐管理');
?>

<div class="content-header">
    <h1 class="page-title"><i class="ti ti-star"></i> 推荐管理</h1>
</div>

<div class="card">
    <!-- 分类切换 -->
    <div class="d-flex flex-wrap gap-8 mb-20">
        <?php foreach ($categories as $c):
            $isActive = ((int)$c['id'] === $catId);
            $count = (int)($c['site_count'] ?? 0);
        ?>
        <a href="/admin/featured.php?cat=<?= (int)$c['id'] ?>"
           class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>">
            <?= Security::e($c['name'] ?? '') ?>
            <span class="badge <?= $isActive ? 'badge-secondary' : 'badge-info' ?> ml-4" >
                <?= $count ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="grid-2">
        <!-- 左侧：当前推荐 -->
        <div>
            <h3 class="section-title">
                <i class="ti ti-star text-warning" ></i>
                当前推荐 (<?= count($featuredSites) ?>)
            </h3>

            <?php if (empty($featuredSites)): ?>
            <div class="empty-state p-20" >
                <div class="icon"><i class="ti ti-star-off"></i></div>
                <p>暂无推荐站点</p>
            </div>
            <?php else: ?>
            <form id="reorderForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="category_id" value="<?= (int)$catId ?>">

                <table class="data-table table-full" >
                    <thead>
                        <tr class="table-th">
                            <th width="50">排序</th>
                            <th>站点</th>
                            <th width="80">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($featuredSites as $s):
                            $name = $s['name'] ?? '';
                            $url  = $s['url']  ?? '';
                            $id   = (int)($s['id'] ?? 0);
                            $order = (int)($s['feature_order'] ?? 0);
                            $firstChar = mb_substr($name, 0, 1, 'UTF-8') ?: '?';
                            $domain = getDisplayDomain($url);
                            $color = getSiteColor($name);
                        ?>
                        <tr class="tr-border">
                            <td class="py-10 px-6">
                                <input type="number" name="orders[<?= $id ?>]" value="<?= $order ?>"
                                       class="input-sort-sm">
                            </td>
                            <td class="py-10 px-6">
                                <div class="d-flex items-center gap-8">
                                    <div style="width:32px;height:32px;border-radius:8px;background:<?= $color ?>15;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:13px;flex-shrink:0">
                                        <?= Security::e($firstChar) ?>
                                    </div>
                                    <div>
                                        <div class="text-sm-bold"><?= Security::e($name) ?></div>
                                        <div class="text-xs-dim-3"><?= Security::e($domain) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-10 px-6">
                                <form method="POST" class="d-inline" onsubmit="return confirm('确定取消推荐？')">
                                    <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="site_id" value="<?= $id ?>">
                                    <input type="hidden" name="category_id" value="<?= (int)$catId ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">取消</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary mt-8" ><i class="ti ti-check"></i> 保存排序</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- 右侧：可选站点 -->
        <div>
            <h3 class="section-title">
                <i class="ti ti-plus text-success" ></i>
                可选站点 (<?= count($availableSites) ?>)
            </h3>

            <?php if (empty($availableSites)): ?>
            <div class="empty-state p-20" >
                <div class="icon"><i class="ti ti-world-off"></i></div>
                <p>没有可选站点</p>
            </div>
            <?php else: ?>
            <div class="max-h-600-pr-4">
                <?php foreach ($availableSites as $s):
                    $name = $s['name'] ?? '';
                    $url  = $s['url']  ?? '';
                    $id   = (int)($s['id'] ?? 0);
                    $brPc = (int)($s['br_pc'] ?? 0);
                    if ($id <= 0 || $name === '') continue;

                    $firstChar = mb_substr($name, 0, 1, 'UTF-8') ?: '?';
                    $domain = getDisplayDomain($url);
                    $color = getSiteColor($name);
                ?>
                <div class="flex-center-bg-mb-p-gap-4b11">
                    <div style="width:32px;height:32px;border-radius:8px;background:<?= $color ?>15;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:13px;flex-shrink:0">
                        <?= Security::e($firstChar) ?>
                    </div>
                    <div class="flex-1 text-ellipsis">
                        <div class="text-sm-bold"><?= Security::e($name) ?></div>
                        <div class="text-xs-dim-3"><?= Security::e($domain) ?></div>
                    </div>
                    <span class="badge badge-info">BR<?= $brPc ?></span>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="site_id" value="<?= $id ?>">
                        <input type="hidden" name="category_id" value="<?= (int)$catId ?>">
                        <button type="submit" class="btn btn-sm btn-success"><i class="ti ti-plus"></i> 推荐</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($msg): ?>
<script>
setTimeout(() => {
    const m = <?= json_encode(getFeatureMsg($msg), JSON_UNESCAPED_UNICODE) ?>;
    if (m) alert(m);
    // 移除 URL 中的 msg 参数，避免刷新重复提示
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('msg');
        window.history.replaceState({}, '', url);
    }
}, 0);
</script>
<?php endif; ?>

<?php
adminFooter();

function getFeatureMsg($key) {
    $msgs = [
        'added'     => '推荐添加成功',
        'removed'   => '已取消推荐',
        'reordered' => '排序已保存',
        'csrf'      => 'CSRF验证失败，请重试',
    ];
    return $msgs[$key] ?? '';
}
