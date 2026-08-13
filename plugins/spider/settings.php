<?php
/**
 * 蜘蛛来访统计插件 - 设置页面
 * 配置各搜索引擎开关、数据保留天数
 *
 * 由 admin/plugin.php?p=spider&action=settings 分发进入此文件
 */

// 安全检查
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

$msg     = '';
$msgType = 'success';

// ========== POST 处理：保存设置 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF验证失败';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'save_settings':
                // 保存启用的引擎列表
                $enabledEngines = [];
                if (!empty($_POST['engines']) && is_array($_POST['engines'])) {
                    foreach ($_POST['engines'] as $engine) {
                        if (isset(SpiderModel::$engines[$engine])) {
                            $enabledEngines[] = $engine;
                        }
                    }
                }
                Plugin::setConfig('spider', 'engines', implode(',', $enabledEngines));

                // 保存数据保留天数
                $retentionDays = (int)($_POST['retention_days'] ?? 30);
                if ($retentionDays < 1 || $retentionDays > 365) {
                    $retentionDays = 30;
                }
                Plugin::setConfig('spider', 'retention_days', (string)$retentionDays);

                $msg = '设置已保存';
                break;

            default:
                $msg = '未知操作';
                $msgType = 'warning';
                break;
        }
    }
}

// ========== 读取当前设置 ==========
$currentEngines = SpiderModel::getEnabledEngines();
$currentRetention = (int)Plugin::config('spider', 'retention_days', '30');

if ($msg) { adminAlert($msg, $msgType); }
?>

<div class="card">
    <div class="card-header flex-between-center">
        <span class="card-title"><i class="ti ti-settings"></i> 蜘蛛统计设置</span>
        <a href="/admin/plugin.php?p=spider" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> 返回统计</a>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="save_settings">

        <!-- 搜索引擎开关 -->
        <div class="form-group">
            <label>搜索引擎开关</label>
            <div class="form-help" style="margin-bottom:12px;">
                勾选要统计的搜索引擎蜘蛛，未勾选的引擎来访将不会被记录。
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:12px;margin-top:8px;">
                <?php foreach (SpiderModel::$engines as $key => $info): ?>
                <label style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;cursor:pointer;transition:all .2s;<?= in_array($key, $currentEngines) ? 'border-color:' . $info['color'] . ';background:' . $info['color'] . '0a;' : '' ?>"
                       onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'"
                       onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <input type="checkbox" name="engines[]" value="<?= Security::eAttr($key) ?>" <?= in_array($key, $currentEngines) ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;accent-color:<?= $info['color'] ?>;">
                    <span style="display:flex;align-items:center;gap:8px;flex:1;">
                        <i class="ti <?= $info['icon'] ?>" style="font-size:20px;color:<?= $info['color'] ?>;"></i>
                        <span style="font-weight:600;"><?= Security::e($info['name']) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 数据保留天数 -->
        <div class="form-group">
            <label>数据保留天数</label>
            <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                    <input type="number" class="form-input" name="retention_days" value="<?= (int)$currentRetention ?>" min="1" max="365" style="max-width:120px;">
                    <div class="form-help">设置蜘蛛来访数据的保留天数（1~365），超过天数的数据将被自动清理。默认 30 天。</div>
                </div>
            </div>
        </div>

        <!-- 说明信息 -->
        <div class="form-group">
            <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;font-size:13px;color:#1e40af;line-height:1.8;">
                <p style="margin:0 0 8px 0;"><i class="ti ti-info-circle"></i> <strong>功能说明</strong></p>
                <ul style="margin:0;padding-left:20px;">
                    <li>插件启用后，每次前台请求都会检测 User-Agent 是否为搜索引擎蜘蛛</li>
                    <li>只有在上方勾选的引擎才会被记录到数据库</li>
                    <li>数据自动清理：约每 100 次蜘蛛访问时触发一次过期数据清理</li>
                    <li>也可在统计页面手动点击「清理过期数据」按钮立即清理</li>
                    <li>修改引擎开关后立即生效，不影响已有数据</li>
                </ul>
            </div>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
        </div>
    </form>
</div>
