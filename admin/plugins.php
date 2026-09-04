<?php
/**
 * 后台插件管理（独立页面）
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'plugins';

$msg = '';
$msgType = 'success';

// ========== POST 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugins.php?err=' . urlencode('CSRF验证失败，请重试'));
    }

    $adminId = $_SESSION['admin_id'] ?? '未知';
    $ip = Security::getClientIP();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'plugin_enable':
            $pluginName = Security::cleanString($_POST['plugin_name'] ?? '', 50);
            $info = Plugin::getInfo($pluginName);
            if ($info !== null) {
                Plugin::setEnabled($pluginName, true);
                Plugin::clearCache();
                $msg = "插件「{$info['title']}」已启动";
                Logger::log('admin_setting', "启动插件 admin_id={$adminId} IP={$ip} plugin={$pluginName}");
            } else {
                $msg = '插件不存在';
                $msgType = 'error';
            }
            break;

        case 'plugin_disable':
            $pluginName = Security::cleanString($_POST['plugin_name'] ?? '', 50);
            $info = Plugin::getInfo($pluginName);
            if ($info !== null) {
                Plugin::setEnabled($pluginName, false);
                Plugin::clearCache();
                $msg = "插件「{$info['title']}」已停用（数据保留）";
                Logger::log('admin_setting', "停用插件 admin_id={$adminId} IP={$ip} plugin={$pluginName}");
            } else {
                $msg = '插件不存在';
                $msgType = 'error';
            }
            break;

        case 'plugin_uninstall':
            $pluginName = Security::cleanString($_POST['plugin_name'] ?? '', 50);
            $confirm = $_POST['confirm'] ?? '';
            if ($pluginName && $confirm === 'yes') {
                $info = Plugin::getInfo($pluginName);
                if ($info !== null) {
                    $tables = $info['tables'] ?? [];
                    $tableHint = !empty($tables) ? '（含数据表：' . implode(', ', $tables) . '）' : '';
                    $result = Plugin::uninstall($pluginName);
                    $dropped = $result['dropped_tables'] ?? [];
                    $droppedCols = $result['dropped_columns'] ?? [];
                    $cleared = $result['cleared_keys'] ?? 0;
                    $msg = "插件 {$pluginName} 已卸载{$tableHint}，删除数据表 " . count($dropped) . " 张，删除字段 " . count($droppedCols) . " 个，清除配置 {$cleared} 项";
                    Logger::log('admin_setting', "卸载插件 admin_id={$adminId} IP={$ip} plugin={$pluginName} tables=" . implode(',', $dropped) . " columns=" . implode(',', $droppedCols) . " cleared={$cleared}");
                } else {
                    $msg = '插件不存在';
                    $msgType = 'error';
                }
            } else {
                $msg = '卸载请求参数不完整';
                $msgType = 'error';
            }
            break;
    }

    // PRG 跳转
    if ($msgType === 'success') {
        redirect('/admin/plugins.php?ok=' . urlencode($msg));
    } else {
        redirect('/admin/plugins.php?err=' . urlencode($msg));
    }
}

// GET 消息
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
    $msgType = 'success';
} elseif (isset($_GET['err'])) {
    $msg = Security::cleanString($_GET['err']);
    $msgType = 'error';
}

$allPlugins = Plugin::scan();

// 排序：已启用排前面，已停用排后面，同组内按名称排序
uasort($allPlugins, function ($a, $b) {
    $aEnabled = $a['enabled'] ?? false;
    $bEnabled = $b['enabled'] ?? false;
    if ($aEnabled !== $bEnabled) {
        return $aEnabled ? -1 : 1; // 启用在前
    }
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

adminHeader('插件管理');
if ($msg) { adminAlert($msg, $msgType); }
?>

<div class="card">
    <div class="card-header"><span class="card-title">插件管理</span></div>

    <div class="alert" style="background:#f0f7ff;border:1px solid #b3d9ff;padding:12px;border-radius:6px;margin-bottom:16px">
      <h4 style="margin:0 0 8px 0;font-size:14px"><i class="ti ti-info-circle"></i> 插件系统说明</h4>
      <ul style="margin:0;padding-left:20px;font-size:13px;color:#495057;line-height:1.8">
        <li><strong>启动</strong>插件后立即可用；<strong>停用</strong>后插件不加载但保留数据</li>
        <li><strong>卸载</strong>会停用插件、删除其自建数据表、清除所有配置项（<span style="color:#dc2626">数据不可恢复</span>）</li>
        <li>插件文件不会被删除，卸载后可随时重新启动</li>
        <li>插件配置存储在 settings 表，与系统设置共用同一存储层</li>
        <li>插件开发文档请参考 <a href="https://site.ikunwl.com/data/docs/" target="_blank">https://site.ikunwl.com/data/docs/</a></li>
      </ul>
    </div>

    <style>
    .plugin-actions { display:flex; gap:6px; justify-content:center; flex-wrap:wrap; }
    .plugin-btn { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:4px; font-size:12px; border:1px solid; cursor:pointer; text-decoration:none; transition:all .2s; }
    .plugin-btn-start { color:#16a34a; border-color:#86efac; background:#f0fdf4; }
    .plugin-btn-start:hover { background:#bbf7d0; }
    .plugin-btn-stop { color:#ca8a04; border-color:#fde047; background:#fefce8; }
    .plugin-btn-stop:hover { background:#fef08a; }
    .plugin-btn-uninstall { color:#dc2626; border-color:#fca5a5; background:#fef2f2; }
    .plugin-btn-uninstall:hover { background:#fecaca; }
    .plugin-btn-settings { color:#2563eb; border-color:#93c5fd; background:#eff6ff; }
    .plugin-btn-settings:hover { background:#bfdbfe; }
    .plugin-status { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:500; padding:2px 8px; border-radius:4px; }
    .plugin-status-enabled { color:#16a34a; background:#dcfce7; }
    .plugin-status-disabled { color:#6b7280; background:#f3f4f6; }
    </style>

    <?php if (empty($allPlugins)): ?>
    <div class="alert alert-warning"><i class="ti ti-alert-triangle"></i> 未找到任何插件，请检查 plugins/ 目录</div>
    <?php else: ?>
    <table class="table" style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#f8f9fa;">
          <th style="padding:10px;border:1px solid #e9ecef;">插件</th>
          <th style="padding:10px;border:1px solid #e9ecef;">描述</th>
          <th style="padding:10px;border:1px solid #e9ecef;width:70px;">版本</th>
          <th style="padding:10px;border:1px solid #e9ecef;width:80px;">状态</th>
          <th style="padding:10px;border:1px solid #e9ecef;width:130px;">钩子</th>
          <th style="padding:10px;border:1px solid #e9ecef;width:210px;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allPlugins as $name => $info):
          $isEnabled = $info['enabled'] ?? false;
          $pluginTables = $info['tables'] ?? [];
          $schema = Plugin::loadSchema($name);
          $schemaTables = array_keys($schema['tables'] ?? []);
          $schemaColumns = $schema['columns'] ?? [];
          // 合并 plugin.json 和 schema.php 声明的表（去重）
          $allTables = array_unique(array_merge($pluginTables, $schemaTables));
          // 计算插件的数据库影响（表、字段、settings 配置项）
          $dbItems = [];
          // 独立表
          if (!empty($allTables)) {
            foreach ($allTables as $tbl) {
              $dbItems[] = Security::e($tbl);
            }
          }
          // 向已有表添加的字段（显示为"表名(X字段)")
          foreach ($schemaColumns as $targetTable => $columns) {
            $colCount = count($columns);
            if ($colCount > 0) {
              $dbItems[] = Security::e($targetTable) . '(' . $colCount . '字段)';
            }
          }
          // settings 表中的配置项数量
          $configCount = count($schema['config'] ?? []);
          if ($configCount > 0) {
            $dbItems[] = 'settings(' . $configCount . '配置)';
          }
        ?>
        <tr>
          <td style="padding:10px;border:1px solid #e9ecef;">
            <strong><?= Security::e($info['title'] ?? $name) ?></strong>
            <?php if ($info['builtin'] ?? false): ?>
            <span style="font-size:11px;color:#16a34a;background:#dcfce7;padding:1px 6px;border-radius:3px;margin-left:4px;">内置</span>
            <?php endif; ?>
            <div style="font-size:12px;color:#999;margin-top:2px;"><?= Security::e($name) ?></div>
            <?php if (!empty($dbItems)): ?>
            <div style="font-size:11px;color:#e67e22;margin-top:2px;">
              <i class="ti ti-database"></i>
              <?php foreach ($dbItems as $i => $item): ?>
                <?= $i > 0 ? ' · ' : '' ?><?= $item ?>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="padding:10px;border:1px solid #e9ecef;font-size:13px;color:#495057;">
            <?= Security::e($info['description'] ?? '') ?>
          </td>
          <td style="padding:10px;border:1px solid #e9ecef;font-size:13px;"><?= Security::e($info['version'] ?? '1.0') ?></td>
          <td style="padding:10px;border:1px solid #e9ecef;">
            <span class="plugin-status <?= $isEnabled ? 'plugin-status-enabled' : 'plugin-status-disabled' ?>">
              <i class="ti ti-<?= $isEnabled ? 'circle-check' : 'circle-x' ?>"></i>
              <?= $isEnabled ? '已启用' : '已停用' ?>
            </span>
          </td>
          <td style="padding:10px;border:1px solid #e9ecef;font-size:12px;color:#666;">
            <?php
            $hooks = $info['hooks'] ?? [];
            if (!empty($hooks) && is_array($hooks)) {
              echo implode(', ', array_map(function($h) {
                return '<code>' . Security::e($h) . '</code>';
              }, $hooks));
            } else {
              echo '<span style="color:#ccc;">-</span>';
            }
            ?>
          </td>
          <td style="padding:10px;border:1px solid #e9ecef;">
            <div class="plugin-actions">
              <?php if (!$isEnabled): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="plugin_enable">
                <input type="hidden" name="plugin_name" value="<?= Security::eAttr($name) ?>">
                <button type="submit" class="plugin-btn plugin-btn-start" title="启用插件">
                  <i class="ti ti-player-play"></i> 启动
                </button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="plugin_disable">
                <input type="hidden" name="plugin_name" value="<?= Security::eAttr($name) ?>">
                <button type="submit" class="plugin-btn plugin-btn-stop" title="停用插件（保留数据）">
                  <i class="ti ti-player-pause"></i> 停用
                </button>
              </form>
              <?php endif; ?>

              <?php if ($isEnabled): ?>
              <?php
                // 自动检测：插件目录下有 admin.php 就有独立管理页面
                $adminUrl = file_exists($info['dir'] . '/admin.php')
                    ? '/admin/plugin.php?p=' . urlencode($name)
                    : null;
                // config_tab 优先，否则用插件名
                $tabKey = $info['config_tab'] ?? $name;
              ?>
              <?php if ($adminUrl): ?>
              <a href="<?= Security::eAttr($adminUrl) ?>" class="plugin-btn plugin-btn-settings" title="进入管理页面">
                <i class="ti ti-list-details"></i> 管理
              </a>
              <?php elseif (!empty($info['config_file'])): ?>
              <a href="/admin/settings.php?tab=<?= Security::eAttr($tabKey) ?>" class="plugin-btn plugin-btn-settings" title="进入插件设置">
                <i class="ti ti-settings"></i> 设置
              </a>
              <?php endif; ?>
              <?php endif; ?>

              <form method="POST" style="display:inline;" onsubmit="return confirmUninstall('<?= Security::eAttr($name) ?>', <?= json_encode(!empty($pluginTables)) ?>)">
                <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="plugin_uninstall">
                <input type="hidden" name="plugin_name" value="<?= Security::eAttr($name) ?>">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="plugin-btn plugin-btn-uninstall" title="卸载插件（停用+删表+清配置，不可恢复）">
                  <i class="ti ti-trash"></i> 卸载
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function confirmUninstall(pluginName, hasTables) {
  var msg = '确定要卸载插件「' + pluginName + '」吗？\n\n卸载将执行以下操作：\n- 停用插件\n- 清除该插件的所有配置项';
  if (hasTables) {
    msg += '\n- 删除插件创建的数据表（数据将永久丢失！）';
  }
  msg += '\n\n插件文件不会被删除，可随时重新启用。';
  return confirm(msg);
}
</script>

<?php adminFooter(); ?>
