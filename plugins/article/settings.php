<?php
/**
 * 文章插件 - 设置页面
 *
 * 由 admin/plugin.php?p=article&action=settings 分发进入此文件
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
                // 保存前台按钮 class
                $btnClass = Security::cleanString($_POST['btn_class'] ?? 'sidebar-item', 200);
                Plugin::setConfig('article', 'btn_class', $btnClass);

                // 保存自定义 CSS
                $customCss = trim($_POST['custom_css'] ?? '');
                Plugin::setConfig('article', 'custom_css', $customCss);

                // 保存文章分类（多选：逗号分隔的分类ID列表）
                $categoryIds = [];
                if (!empty($_POST['category_ids']) && is_array($_POST['category_ids'])) {
                    foreach ($_POST['category_ids'] as $cid) {
                        $cid = (int)$cid;
                        if ($cid > 0) $categoryIds[] = $cid;
                    }
                }
                Plugin::setConfig('article', 'category_ids', implode(',', $categoryIds));

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
$currentBtnClass = Plugin::config('article', 'btn_class', 'sidebar-item');
$currentCustomCss = Plugin::config('article', 'custom_css', '');
$currentCategoryIdsStr = Plugin::config('article', 'category_ids', '');
$currentCategoryIds = [];
if ($currentCategoryIdsStr) {
    foreach (explode(',', $currentCategoryIdsStr) as $cid) {
        $cid = (int)trim($cid);
        if ($cid > 0) $currentCategoryIds[] = $cid;
    }
}

// 获取全部分类
$catModel = new CategoryModel();
$allCategories = $catModel->getAll();

if ($msg) { adminAlert($msg, $msgType); }
?>

<div class="card">
    <div class="card-header flex-between-center">
        <span class="card-title"><i class="ti ti-settings"></i> 文章插件设置</span>
        <a href="/admin/plugin.php?p=article" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> 返回列表</a>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="save_settings">

        <!-- 前台按钮 Class -->
        <div class="form-group">
            <label>前台入口按钮 Class</label>
            <input type="text"
                   class="form-input"
                   name="btn_class"
                   value="<?= Security::eAttr($currentBtnClass) ?>"
                   placeholder="如：sidebar-item">
            <div class="form-help">
                设置文章专栏入口在前台侧边栏使用的 CSS 类名。默认 <code>sidebar-item</code> 与分类导航风格一致。
            </div>
        </div>

        <!-- 文章分类（多选关联导航分类） -->
        <div class="form-group">
            <label>文章专栏分类</label>
            <div class="form-help" style="margin-bottom:12px;">
                勾选哪些导航分类用于文章专栏。文章管理、前台筛选将只显示这些分类。
            </div>
            <div class="category-checkbox-group" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:10px;margin-top:8px;">
                <?php foreach ($allCategories as $cat): ?>
                <label class="category-checkbox-item" style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;cursor:pointer;transition:all .2s;">
                    <input type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $currentCategoryIds) ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer;">
                    <span style="flex:1;"><?= Security::e($cat['name']) ?></span>
                    <span style="color:#999;font-size:12px;">(<?= (int)$cat['site_count'] ?>)</span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 自定义 CSS -->
        <div class="form-group">
            <label>自定义 CSS</label>
            <textarea class="form-textarea"
                      name="custom_css"
                      rows="10"
                      placeholder="/* 在此输入自定义 CSS 代码，将注入到前台 <head> 中 */"><?= Security::e($currentCustomCss) ?></textarea>
            <div class="form-help">
                输入的 CSS 代码将被注入到前台页面的 <code>&lt;head&gt;</code> 中，可用于覆盖文章列表/详情页的默认样式。
            </div>
        </div>

        <!-- 预览说明 -->
        <div class="form-group">
            <label>当前配置预览</label>
            <div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:16px;font-size:13px;color:#666;">
                <p style="margin:0 0 8px 0;"><strong>前台入口 HTML：</strong></p>
                <code style="display:block;background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.6;">
&lt;a href="/article/" class="<?= Security::eAttr($currentBtnClass) ?>"&gt;<br>
&nbsp;&nbsp;&lt;i class="ti ti-article"&gt;&lt;/i&gt;<br>
&nbsp;&nbsp;&lt;span&gt;文章专栏&lt;/span&gt;<br>
&nbsp;&nbsp;&lt;span class="count"&gt;12&lt;/span&gt;<br>
&lt;/a&gt;
                </code>
            </div>
        </div>

        <div class="form-group">
            <label>富文本编辑器钩子说明</label>
            <div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:16px;font-size:13px;color:#555;line-height:1.7;">
                <p style="margin:0 0 12px 0;"><strong>文章编辑器挂载点</strong></p>
                <p style="margin:0 0 8px 0;">在发布/编辑文章的 textarea 前后各有一个钩子，第三方插件可以通过这两个钩子注入富文本编辑器。</p>

                <p style="margin:8px 0 6px 0;"><strong>可用钩子：</strong></p>
                <ul style="margin:0 0 12px 0;padding-left:20px;">
                    <li><code>article_editor_before</code> — 在编辑器 textarea 之前输出内容</li>
                    <li><code>article_editor_after</code> — 在编辑器 textarea 之后输出内容（推荐在此注入编辑器脚本）</li>
                </ul>

                <p style="margin:8px 0 6px 0;"><strong>使用示例（在第三方插件中）：</strong></p>
                <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0;">Plugin::registerHook('article_editor_after', function() {
    // 方式1：引入第三方编辑器 CDN
    echo '&lt;script src="https://cdn.example.com/editor.min.js"&gt;&lt;/script&gt;';
    echo '&lt;script&gt;';
    echo '  var editor = new RichEditor({ el: "#article-content" });';
    echo '&lt;/script&gt;';

    // 方式2：输出内联编辑器代码
    echo '&lt;script&gt;';
    echo '  document.addEventListener("DOMContentLoaded", function() {';
    echo '    var textarea = document.getElementById("article-content");';
    echo '    if (textarea) {';
    echo '      // 在此处初始化你的富文本编辑器';
    echo '      // textarea.style.display = "none"; // 隐藏原生 textarea';
    echo '      // 创建编辑器容器并绑定内容同步';
    echo '    }';
    echo '  });';
    echo '&lt;/script&gt;';
});</pre>

                <p style="margin:12px 0 0 0;"><strong>注意事项：</strong></p>
                <ul style="margin:6px 0 0 0;padding-left:20px;">
                    <li>textarea 的 <code>id="article-content"</code> 固定不变，可作为编辑器的挂载目标</li>
                    <li>编辑器应确保提交时 textarea 的值被正确同步（大多数编辑器框架会自动处理）</li>
                    <li>多个插件同时挂载时按注册顺序依次执行</li>
                </ul>
            </div>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
        </div>
    </form>
</div>
