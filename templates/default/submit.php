<?php
/**
 * 主题模板 - 提交页
 * 变量由 Route.php 注入
 */
Theme::partial('header');

// 获取插件配置的收录分类ID列表
$submitCatIdsStr = Plugin::config('submit', 'category_ids', '');
$submitCatIds = [];
if ($submitCatIdsStr) {
    foreach (explode(',', $submitCatIdsStr) as $cid) {
        $cid = (int)trim($cid);
        if ($cid > 0) $submitCatIds[] = $cid;
    }
}

// 过滤侧边栏和表单中的分类，只显示已配置的收录分类
$filteredCategories = [];
foreach ($categories as $cat) {
    if (empty($submitCatIds) || in_array((int)$cat['id'], $submitCatIds)) {
        $filteredCategories[] = $cat;
    }
}
?>

<!-- 提交页大标题头 + 面包屑 -->
<div class="page-hero-header">
    <div class="container">
        <div class="page-hero-left">
            <div class="page-hero-icon">
                <i class="ti ti-send"></i>
            </div>
            <div class="page-hero-info">
                <h1>提交网站</h1>
                <p class="page-hero-desc">推荐优质网站，丰富导航内容</p>
            </div>
        </div>
        <div class="page-hero-right">
            <div class="page-hero-stats">
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?= (int)$siteStats['published'] ?></div>
                    <div class="hero-stat-label">收录站点</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
  <!-- 面包屑导航 -->
  <div class="breadcrumb-bar">
    <nav class="breadcrumb">
      <a href="/">首页</a>
      <span class="separator">/</span>
      <span class="current">提交网站</span>
    </nav>
  </div>
</div>

<div class="container">
  <div class="main-layout submit-page">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">全部分类</div>
      <a href="<?= Theme::url('home') ?>" class="sidebar-item"><i class="ti ti-home"></i><span>全部站点</span></a>
      <?php foreach ($filteredCategories as $cat): ?>
      <a href="<?= Theme::url('category', ['slug' => $cat['slug']]) ?>" class="sidebar-item">
        <i class="ti ti-<?= Theme::eAttr($cat['icon']) ?>"></i><span><?= Theme::e($cat['name']) ?></span>
        <span class="count"><?= (int)$cat['site_count'] ?></span>
      </a>
      <?php endforeach; ?>
    </aside>

    <!-- Form -->
    <div class="main-content">
      <?php if (!$enable): ?>
      <div class="empty-state">
        <i class="ti ti-lock"></i>
        <p class="empty-title">站点提交功能已关闭</p>
      </div>
      <?php else: ?>
      <?php
      $submitRules = Plugin::config('submit', 'rules', '');
      $submitNeedReview = Plugin::config('submit', 'need_review', '1') === '1';
      $submitDefaultCat = (int) Plugin::config('submit', 'default_category', 0);
      $submitRequireCat = Plugin::config('submit', 'require_category', '1') === '1';
      ?>
      <div class="submit-rules">
        <h3><i class="ti ti-clipboard-list"></i> 收录规则</h3>
        <?php if ($submitRules): ?>
        <?= Security::cleanHtml($submitRules, 20000) ?>
        <?php else: ?>
        <ol>
          <li>申请收录前，请先在本站添加友链。友链代码如下：<code class="rules-code">&lt;a href="<?= Theme::eAttr($settings['site_url'] ?? 'https://site.ikunwl.com/') ?>" target="_blank"&gt;<?= Theme::e($settings['site_name'] ?? 'ikun导航') ?>&lt;/a&gt;</code></li>
          <li>网站不得含有色情、挂马、虚假内容、广告过多等内容</li>
          <li>不收录无实质内容的网站（如建设中、尚无完整内容等）</li>
          <li>不收录挂靠他人网站下的二级域名（即无独立域名的网站）</li>
          <li>不收录无法正常连接或打开时间过长的网站</li>
          <li>不定期检查所有收录网站，违规者将删除链接</li>
        </ol>
        <div class="rules-tip">
          <i class="ti ti-info-circle"></i>
          不定期检查未做友链的站点，快审站除外。审核后取消友链的站点也会被删除，请认真对待！
        </div>
        <?php endif; ?>
      </div>

      <div class="submit-form">
        <div id="submitMsg"></div>
        <div class="form-group">
          <label>站点名称 <span class="required">*</span></label>
          <input type="text" id="sName" maxlength="100" placeholder="请输入站点名称">
        </div>
        <div class="form-group">
          <label>站点网址 <span class="required">*</span></label>
          <input type="url" id="sUrl" maxlength="500" placeholder="https://example.com">
          <button type="button" id="fetchBtn" class="btn btn-secondary btn-mt" onclick="fetchTDK()">自动获取标题/描述/权重</button>
        </div>
        <!-- 权重隐藏字段，由 fetchTDK() 自动填充，提交时一并传给后端 -->
        <input type="hidden" id="sBrPc" value="0">
        <input type="hidden" id="sBrMobile" value="0">
        <input type="hidden" id="sBr360" value="0">
        <input type="hidden" id="sBrShenma" value="0">
        <div class="form-group">
          <label>所属分类 <?php if ($submitRequireCat): ?><span class="required">*</span><?php endif; ?></label>
          <select id="sCat">
            <?php if (!$submitRequireCat): ?>
            <option value="">不选择分类</option>
            <?php else: ?>
            <option value="">请选择分类</option>
            <?php endif; ?>
            <?php foreach ($filteredCategories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $submitDefaultCat === (int)$cat['id'] ? 'selected' : '' ?>><?= Theme::e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>站点标签（逗号分隔）</label>
          <input type="text" id="sTags" maxlength="200" placeholder="如：AI,工具,免费">
        </div>
        <div class="form-group">
          <label>描述</label>
          <textarea id="sDesc" maxlength="500" rows="3" placeholder="简要描述站点功能..."></textarea>
        </div>
        <div class="form-hint">
          <i class="ti ti-info-circle"></i>
          提交后 <?php if ($submitNeedReview): ?>需要管理员审核通过才会显示<?php else: ?>直接发布<?php endif; ?>
        </div>
        <button type="button" class="btn btn-primary btn-block" onclick="submitSite()">
          <i class="ti ti-send"></i> 提交
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// CSRF Token
const CSRF_TOKEN = '<?= Security::eAttr(Security::generateCSRFToken()) ?>';

async function submitSite() {
  const btn = document.querySelector('.btn-primary');
  const msg = document.getElementById('submitMsg');

  const name = document.getElementById('sName').value.trim();
  const url = document.getElementById('sUrl').value.trim();
  const cat = document.getElementById('sCat').value;
  const tags = document.getElementById('sTags').value.trim();
  const desc = document.getElementById('sDesc').value.trim();

  if (!name || !url || (<?= $submitRequireCat ? 'true' : 'false' ?> && !cat)) {
    msg.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 请填写必填项</div>';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> 提交中...';
  msg.innerHTML = '';

  try {
    const res = await fetch('/api/index.php?endpoint=submit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN
      },
      body: JSON.stringify({
        name, url, category_id: cat, tags, description: desc,
        br_pc: parseInt(document.getElementById('sBrPc').value) || 0,
        br_mobile: parseInt(document.getElementById('sBrMobile').value) || 0,
        br_360: parseInt(document.getElementById('sBr360').value) || 0,
        br_shenma: parseInt(document.getElementById('sBrShenma').value) || 0,
      })
    });
    const json = await res.json();
    if (json.success) {
      msg.textContent = '';
      const alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-success';
      alertDiv.innerHTML = '<i class="ti ti-check"></i> ';
      alertDiv.appendChild(document.createTextNode(json.message || '提交成功'));
      msg.appendChild(alertDiv);
      document.getElementById('sName').value = '';
      document.getElementById('sUrl').value = '';
      document.getElementById('sCat').value = '';
      document.getElementById('sTags').value = '';
      document.getElementById('sDesc').value = '';
    } else {
      msg.textContent = '';
      const alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-error';
      alertDiv.innerHTML = '<i class="ti ti-alert-circle"></i> ';
      alertDiv.appendChild(document.createTextNode(json.message || '提交失败'));
      msg.appendChild(alertDiv);
    }
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 提交失败，请稍后重试</div>';
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ti ti-send"></i> 提交';
}

async function fetchTDK() {
  const btn = document.getElementById('fetchBtn');
  const url = document.getElementById('sUrl').value.trim();
  const msgDiv = document.getElementById('submitMsg');

  if (!url) {
    msgDiv.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> 请先输入网址</div>';
    return;
  }

  btn.disabled = true;
  const originalText = btn.textContent;
  btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> 获取中...';

  // 重置权重隐藏字段
  document.getElementById('sBrPc').value = 0;
  document.getElementById('sBrMobile').value = 0;
  document.getElementById('sBr360').value = 0;
  document.getElementById('sBrShenma').value = 0;

  let tdkOk = false;
  let rankOk = false;
  let errMsg = '';

  // ===== 并行调用本地 TDK 接口和权重接口 =====
  const [tdkRes, rankRes] = await Promise.allSettled([
    fetch('/api/tdk.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ url })
    }).then(r => r.json()),
    fetch('/api/rank.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ url })
    }).then(r => r.json())
  ]);

  // 处理 TDK 结果
  if (tdkRes.status === 'fulfilled' && tdkRes.value.success) {
    const data = tdkRes.value;
    if (!document.getElementById('sName').value.trim()) {
      document.getElementById('sName').value = data.title || '';
    }
    document.getElementById('sDesc').value = data.description || '';
    document.getElementById('sTags').value = data.keywords || '';
    tdkOk = true;
  } else if (tdkRes.status === 'fulfilled') {
    errMsg += (tdkRes.value.message || 'TDK 获取失败') + '; ';
  } else {
    errMsg += 'TDK 接口请求失败; ';
  }

  // 处理权重结果
  if (rankRes.status === 'fulfilled' && rankRes.value.success) {
    const r = rankRes.value;
    document.getElementById('sBrPc').value = r.br_pc || 0;
    document.getElementById('sBrMobile').value = r.br_mobile || 0;
    document.getElementById('sBr360').value = r.br_360 || 0;
    document.getElementById('sBrShenma').value = r.br_shenma || 0;
    rankOk = true;
  } else if (rankRes.status === 'fulfilled') {
    // 权重获取失败不阻断流程（可能未配置 API Key）
    errMsg += (rankRes.value.message || '权重获取失败') + '; ';
  } else {
    errMsg += '权重接口请求失败; ';
  }

  // 汇总结果
  if (tdkOk && rankOk) {
    const r = rankRes.value;
    msgDiv.innerHTML = '<div class="alert alert-success"><i class="ti ti-check"></i> 获取成功' +
      ' | 权重: PC ' + (r.br_pc || 0) + ' / 移动 ' + (r.br_mobile || 0) +
      ' / 360 ' + (r.br_360 || 0) + ' / 神马 ' + (r.br_shenma || 0) + '</div>';
  } else if (tdkOk) {
    msgDiv.innerHTML = '<div class="alert alert-success"><i class="ti ti-check"></i> TDK 获取成功（权重未获取：' + errMsg + '）</div>';
  } else {
    msgDiv.innerHTML = '<div class="alert alert-error"><i class="ti ti-alert-circle"></i> ' + (errMsg || '获取失败') + '</div>';
  }

  btn.disabled = false;
  btn.textContent = originalText;
}
</script>

<?php Theme::partial('footer'); ?>
