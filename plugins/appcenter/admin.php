<?php
/**
 * 应用中心插件 - 后台商店页面
 *
 * 由 admin/plugin.php?p=appcenter 分发进入（外层已输出 adminHeader/Footer）。
 * 页面本身不处理任何 POST：全部状态变更走 AJAX 接口 api.php（登录 + CSRF 双重校验）。
 * 所有来自服务器目录的动态文本一律通过 textContent 渲染，杜绝 XSS。
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}
require_once __DIR__ . '/lib.php';

$preset     = appcenter_preset();
$customUrl  = appcenter_custom_url();
$autoEnable = appcenter_auto_enable();
$dlHosts    = implode(', ', appcenter_download_hosts());
$tlsLoose   = appcenter_tls_loose();
$presets    = appcenter_preset_urls();
$nowLabel   = appcenter_server_label();
$nowUrl     = appcenter_server_url();
?>
<style>
.ac-info { background:#f0f7ff; border:1px solid #b3d9ff; padding:12px 14px; border-radius:6px; margin-bottom:16px; font-size:13px; line-height:1.8; color:#1f3a5f; }
.ac-info strong { color:#0b4a8f; }
.ac-card { margin-bottom:16px; }
.ac-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
.ac-row > label { width:150px; flex:0 0 150px; font-size:13px; color:#374151; padding-top:7px; }
.ac-row .ac-field { flex:1; min-width:280px; }
.ac-row input[type="text"] { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #d1d5db; border-radius:5px; font-size:13px; }
.ac-row input[type="text"]:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
.ac-radio { display:flex; align-items:flex-start; gap:8px; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:8px; cursor:pointer; background:#fff; transition:border-color .2s; max-width:560px; }
.ac-radio:hover { border-color:#93c5fd; }
.ac-radio input { margin-top:2px; }
.ac-radio .ac-radio-name { font-size:13px; color:#111827; font-weight:500; }
.ac-radio .ac-radio-url { font-size:12px; color:#6b7280; margin-top:2px; word-break:break-all; }
.ac-radio.ac-radio-off { opacity:.6; }
.ac-now { display:inline-flex; align-items:center; gap:6px; font-size:12px; background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; border-radius:5px; padding:5px 10px; margin-top:2px; }
.ac-hint { font-size:12px; color:#6b7280; margin-top:4px; line-height:1.7; }
.ac-check { display:flex; align-items:center; gap:6px; font-size:13px; color:#374151; padding-top:6px; }
.ac-actions { display:flex; gap:8px; align-items:center; margin-top:4px; }
.ac-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border-radius:5px; font-size:13px; border:1px solid; cursor:pointer; text-decoration:none; transition:all .2s; background:#fff; }
.ac-btn:disabled { opacity:.55; cursor:not-allowed; }
.ac-btn-primary { color:#fff; background:#2563eb; border-color:#2563eb; }
.ac-btn-primary:hover:not(:disabled) { background:#1d4ed8; }
.ac-btn-ghost { color:#374151; border-color:#d1d5db; }
.ac-btn-ghost:hover:not(:disabled) { background:#f3f4f6; }
.ac-list-meta { font-size:12px; color:#6b7280; margin-bottom:10px; display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
.ac-badge { display:inline-block; font-size:11px; padding:1px 7px; border-radius:3px; margin-left:6px; vertical-align:1px; }
.ac-badge-plugin { color:#1d4ed8; background:#dbeafe; }
.ac-badge-theme { color:#7c3aed; background:#ede9fe; }
.ac-badge-builtin { color:#16a34a; background:#dcfce7; }
.ac-badge-third { color:#7c3aed; background:#ede9fe; }
.ac-badge-custom { color:#b45309; background:#fef3c7; }
.ac-state { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:500; padding:2px 8px; border-radius:4px; }
.ac-state-new { color:#16a34a; background:#dcfce7; }
.ac-state-up { color:#b45309; background:#fef3c7; }
.ac-state-none { color:#6b7280; background:#f3f4f6; }
.ac-state-bad { color:#dc2626; background:#fee2e2; }
.ac-ver { font-size:12px; line-height:1.9; color:#4b5563; }
.ac-ver b { color:#111827; }
.ac-local { color:#9ca3af; }
.ac-row-note { font-size:12px; color:#9ca3af; }
.ac-loading { text-align:center; color:#6b7280; padding:36px 0; font-size:13px; }
.ac-empty { text-align:center; color:#9ca3af; padding:36px 0; font-size:13px; }
.ac-desc { font-size:12px; color:#6b7280; max-width:420px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.ac-advanced summary { cursor:pointer; font-size:13px; color:#6b7280; user-select:none; }
.ac-advanced[open] summary { color:#2563eb; }
</style>

<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="ti ti-apps"></i>
  <strong>应用中心</strong>：从服务器拉取插件 / 主题目录，在线一键安装与升级。
  默认使用<strong>官方服务器</strong>，可切换第三方服务器或填写自定义地址（自定义非空时优先）。
  升级前自动备份旧版本、失败自动回滚；安装新插件默认自动启用。
</div>

<div id="ac-banner"></div>

<!-- ============ 服务器设置 ============ -->
<div class="card ac-card">
  <div class="card-header"><span class="card-title"><i class="ti ti-server"></i> 服务器设置</span></div>
  <div style="padding:2px 18px 16px;">

    <div class="ac-row">
      <label>选择服务器</label>
      <div class="ac-field" id="ac-preset-box">
        <?php foreach ($presets as $key => $p): ?>
        <label class="ac-radio" id="ac-radio-<?= Security::eAttr($key) ?>">
          <input type="radio" name="ac-preset" value="<?= Security::eAttr($key) ?>"
                 <?= $preset === $key ? 'checked' : '' ?>>
          <span>
            <span class="ac-radio-name"><?= Security::e($p['label']) ?></span>
            <div class="ac-radio-url"><?= Security::e($p['url']) ?></div>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ac-row">
      <label for="ac-custom">自定义地址</label>
      <div class="ac-field">
        <input type="text" id="ac-custom" placeholder="https://你的服务器/appcenter-server（选填）"
               value="<?= Security::eAttr($customUrl) ?>" autocomplete="off" spellcheck="false">
        <div class="ac-hint">填写后<strong>优先使用自定义地址</strong>；留空则使用上方勾选的预设服务器。填写的内容会与上方预设一起保存，想回到预设就清空此项再保存。</div>
      </div>
    </div>

    <div class="ac-row">
      <label></label>
      <div class="ac-field">
        <div class="ac-now" id="ac-server-now">当前生效：<?= Security::e($nowLabel) ?>（<?= Security::e($nowUrl) ?>）</div>
        <div class="ac-actions" style="margin-top:10px;">
          <button type="button" class="ac-btn ac-btn-primary" id="ac-save"><i class="ti ti-device-floppy"></i> 保存设置</button>
          <button type="button" class="ac-btn ac-btn-ghost" id="ac-refresh"><i class="ti ti-refresh"></i> 刷新目录</button>
        </div>
        <div class="ac-check" style="margin-top:10px;">
          <input type="checkbox" id="ac-auto" <?= $autoEnable ? 'checked' : '' ?>>
          <label for="ac-auto" style="padding:0;">安装新插件后自动启用（升级保持原启用状态）</label>
        </div>
      </div>
    </div>

    <details class="ac-advanced" style="margin-top:8px;">
      <summary>高级选项：下载白名单（一般无需设置）</summary>
      <div class="ac-row" style="margin-top:10px;">
        <label for="ac-dlhosts">允许下载的域名</label>
        <div class="ac-field">
          <input type="text" id="ac-dlhosts" placeholder="留空 = 仅允许当前生效服务器同域下载"
                 value="<?= Security::eAttr($dlHosts) ?>" autocomplete="off" spellcheck="false">
          <div class="ac-hint">
            出于安全，安装包只允许从「当前生效服务器」的<strong>同一个域名</strong>下载（防止目录被篡改后指向陌生服务器）。
            只有当你把安装包放到与服务器<strong>不同域名的 CDN / 文件服务器</strong>（例如
            <code>download.example.com</code>）上时，才需要把该域名填进这里，多个用逗号或空格分隔。
            日常使用官方 / 第三方 / 自建同域服务器，这里留空即可。
          </div>
        </div>
      </div>
      <div class="ac-row" style="margin-top:2px;">
        <label></label>
        <div class="ac-field">
          <div class="ac-check">
            <input type="checkbox" id="ac-tls" <?= $tlsLoose ? 'checked' : '' ?>>
            <label for="ac-tls" style="padding:0;">宽松 TLS：证书校验失败也继续连接（仅用于证书链不完整但<strong>可信</strong>的第三方服务器；开启后存在被中间人劫持的风险，请谨慎）</label>
          </div>
        </div>
      </div>
    </details>

  </div>
</div>

<!-- ============ 应用目录 ============ -->
<div class="card ac-card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-cloud-download"></i> 应用目录</span>
    <span style="flex:1"></span>
  </div>
  <div style="padding:2px 18px 16px;">
    <div class="ac-list-meta">
      <span id="ac-meta-server"></span>
      <span id="ac-meta-time"></span>
      <span id="ac-meta-appver"></span>
    </div>
    <div id="ac-list">
      <div class="ac-loading"><i class="ti ti-loader" style="display:inline-block;"></i> 正在加载目录…</div>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';
    var csrfEl = document.getElementById('csrfToken');
    var csrf = csrfEl ? csrfEl.value : '';

    // ============ 工具 ============
    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null && text !== '') {
            node.textContent = String(text); // 防 XSS：一律 textContent
        }
        return node;
    }
    function banner(msg, ok) {
        var box = document.getElementById('ac-banner');
        if (!msg) { box.innerHTML = ''; return; }
        var cls = ok === false ? 'alert alert-error' : 'alert alert-success';
        var div = document.createElement('div');
        div.className = cls;
        var i = document.createElement('i');
        i.className = 'ti ' + (ok === false ? 'ti-alert-circle' : 'ti-circle-check');
        div.appendChild(i);
        div.appendChild(document.createTextNode(' ' + msg));
        box.innerHTML = '';
        box.appendChild(div);
    }
    // 只允许 http/https 链接（防 javascript: 等协议注入），不合法则返回 null 供降级为纯文本
    function acLink(url, text) {
        if (!/^https?:\/\//i.test(String(url || ''))) return null;
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = text || url;
        return a;
    }
    function api(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', csrf);
        for (var k in (data || {})) { fd.append(k, data[k]); }
        return fetch('/plugins/appcenter/api.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .catch(function () { return { success: false, message: '网络错误或服务器无响应' }; });
    }
    function fmtSize(n) {
        n = parseInt(n, 10) || 0;
        if (n <= 0) return '';
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
        if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
        return (n / 1073741824).toFixed(2) + ' GB';
    }
    function fmtTime(ts) {
        if (!ts) return '尚未拉取目录';
        var d = new Date(ts * 1000);
        function p(x) { return (x < 10 ? '0' : '') + x; }
        return '目录更新于 ' + d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
             + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }

    // ============ 服务器选择状态 ============
    // 生效规则：自定义地址非空 → 自定义优先；否则 → 勾选的预设
    // 与服务端 appcenter_preset_urls() 保持一致的后备预设表（任何情况下都能显示）
    function defaultPresets() {
        return [
            { key: 'official', label: '官方服务器', url: 'https://site.ikunwl.com/appcenter-server' },
            { key: 'third',    label: '第三方服务器', url: 'https://www.92w.com/appcenter-server' }
        ];
    }
    function ensurePresets() {
        if (!window.__acPresets || !window.__acPresets.length) {
            window.__acPresets = defaultPresets();
        }
    }
    function currentPreset() {
        var radios = document.querySelectorAll('input[name="ac-preset"]');
        for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked) return radios[i].value;
        }
        return '';
    }
    function selectPreset(key) {
        var radios = document.querySelectorAll('input[name="ac-preset"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].checked = (radios[i].value === key);
        }
    }
    function currentCustom() {
        return document.getElementById('ac-custom').value.trim();
    }
    function presetsMap() {
        ensurePresets();
        var map = {};
        for (var i = 0; i < window.__acPresets.length; i++) {
            map[window.__acPresets[i].key] = window.__acPresets[i];
        }
        return map;
    }
    // “当前生效”统一由 updateNowFromInfo 渲染 —— 与「应用目录」卡片同一数据口径
    // （server_label / server_url / source 均来自接口返回的 appcenter_server_info()，刷新后必然一致）
    function updateNowFromInfo(info) {
        var box = document.getElementById('ac-server-now');
        if (!box || !info) return;
        var label = info.server_label || '服务器';
        var shown = info.source || info.server_url || '';
        box.innerHTML = '';
        box.appendChild(el('span', '', '当前生效：' + label + ' '));
        var link = acLink(shown);
        if (link) {
            box.appendChild(link);
        } else if (shown) {
            box.appendChild(document.createTextNode(shown));
        }
    }
    // 点选/输入时的即时预览（未保存前用当前勾选推算；保存/刷新后由接口数据覆盖，与目录卡片一致）
    function refreshNowLine() {
        var custom = currentCustom();
        var key = currentPreset();
        if (!key) { key = 'official'; selectPreset('official'); }
        var map = presetsMap();
        var p = map[key] || {};
        if (custom) {
            updateNowFromInfo({ server_label: '自定义服务器', server_url: custom });
        } else if (p.label && p.url) {
            updateNowFromInfo({ server_label: p.label, server_url: p.url });
        } else {
            updateNowFromInfo({ server_label: '官方服务器', server_url: 'https://site.ikunwl.com/appcenter-server' });
        }
    }
    function presetDim() {
        var custom = currentCustom();
        var radios = document.querySelectorAll('.ac-radio');
        for (var i = 0; i < radios.length; i++) {
            radios[i].classList.toggle('ac-radio-off', custom !== '');
        }
    }

    // ============ 行渲染 ============
    function renderRow(row) {
        var tr = document.createElement('tr');

        var td1 = document.createElement('td');
        td1.style.cssText = 'padding:12px;border:1px solid #e9ecef;';
        var nameLine = el('div');
        nameLine.appendChild(el('strong', '', row.title || row.id));
        var badge = el('span', 'ac-badge ' + (row.type === 'theme' ? 'ac-badge-theme' : 'ac-badge-plugin'),
            row.type === 'theme' ? '主题' : '插件');
        nameLine.appendChild(badge);
        if (row.origin_label) {
            var oc = row.origin_label === '第三方' ? 'ac-badge-third'
                   : (row.origin_label === '自定义' ? 'ac-badge-custom' : 'ac-badge-builtin');
            nameLine.appendChild(el('span', 'ac-badge ' + oc, row.origin_label));
        }
        td1.appendChild(nameLine);
        var idLine = el('div');
        idLine.style.cssText = 'font-size:12px;color:#9ca3af;margin-top:2px;';
        idLine.appendChild(document.createTextNode(row.id));
        if (row.author) {
            idLine.appendChild(document.createTextNode(' · ' + row.author));
        }
        td1.appendChild(idLine);
        if (row.installed && row.local_title && row.local_title !== row.title) {
            td1.appendChild(el('div', 'ac-row-note', '本地标题：' + row.local_title));
        }
        tr.appendChild(td1);

        var td2 = document.createElement('td');
        td2.style.cssText = 'padding:12px;border:1px solid #e9ecef;';
        if (row.description) {
            var desc = el('div', 'ac-desc', row.description);
            desc.title = row.description;
            td2.appendChild(desc);
        } else {
            td2.appendChild(el('span', 'ac-row-note', '暂无简介'));
        }
        tr.appendChild(td2);

        var td3 = document.createElement('td');
        td3.style.cssText = 'padding:12px;border:1px solid #e9ecef;white-space:nowrap;';
        var v1 = el('div', 'ac-ver');
        if (row.installed) {
            v1.appendChild(document.createTextNode('本地 '));
            v1.appendChild(el('b', '', row.local_version ? 'v' + row.local_version : '未知'));
            if (row.can_upgrade) {
                v1.appendChild(el('span', 'ac-local', ' → '));
                var nb = el('b', '', 'v' + row.version);
                nb.style.color = '#b45309';
                v1.appendChild(nb);
            }
        } else {
            v1.appendChild(document.createTextNode('最新 '));
            v1.appendChild(el('b', '', 'v' + row.version));
        }
        td3.appendChild(v1);
        var size = fmtSize(row.size);
        if (size) {
            td3.appendChild(el('div', 'ac-row-note', size));
        }
        tr.appendChild(td3);

        var td4 = document.createElement('td');
        td4.style.cssText = 'padding:12px;border:1px solid #e9ecef;width:190px;';
        td4.className = 'actions';
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;flex-direction:column;gap:6px;align-items:flex-start;';

        var stateCls = row.state === 'upgrade' ? 'ac-state-up'
                     : (row.state === 'not_installed' ? 'ac-state-none' : 'ac-state-new');
        if (!row.compat_ok) stateCls = 'ac-state-bad';
        wrap.appendChild(el('span', 'ac-state ' + stateCls, row.state_label));

        if (row.action === 'install' || row.action === 'upgrade') {
            var btn = el('button', 'ac-btn ' + (row.action === 'upgrade' ? 'ac-btn-ghost' : 'ac-btn-primary'),
                row.action === 'upgrade' ? '升级到 v' + row.version : '安装 v' + row.version);
            btn.type = 'button';
            btn.setAttribute('data-id', row.id);
            btn.setAttribute('data-title', row.title);
            btn.setAttribute('data-version', row.version);
            btn.setAttribute('data-local', row.local_version || '');
            btn.addEventListener('click', onInstall);
            wrap.appendChild(btn);
        } else if (row.action === 'blocked') {
            var dis = el('button', 'ac-btn', '不可用');
            dis.type = 'button';
            dis.disabled = true;
            dis.title = row.compat_reason || '';
            wrap.appendChild(dis);
            if (row.compat_reason) {
                wrap.appendChild(el('span', 'ac-row-note', row.compat_reason));
            }
        }
        td4.appendChild(wrap);
        tr.appendChild(td4);
        return tr;
    }

    function render(data) {
        var box = document.getElementById('ac-list');
        box.innerHTML = '';

        // 以服务端返回为准同步服务器选择状态（页面刷新 / 保存 / 拉取目录后保持一致）
        if (data && Array.isArray(data.presets) && data.presets.length) {
            window.__acPresets = data.presets;
        }
        if (data && data.preset) {
            selectPreset(data.preset);
            presetDim();
        }
        // “当前生效”与「应用目录」卡片使用同一接口数据渲染，两处永远一致
        if (data) {
            updateNowFromInfo(data);
        }

        // 服务器 meta（取目录来源地址；未拉取时显示当前生效地址）
        var metaServer = document.getElementById('ac-meta-server');
        metaServer.innerHTML = '';
        var shown = data.source || data.server_url;
        if (shown) {
            metaServer.appendChild(document.createTextNode((data.server_label || '服务器') + '：'));
            var link = acLink(shown);
            if (link) {
                metaServer.appendChild(link);
            } else {
                metaServer.appendChild(document.createTextNode(shown));
            }
        }
        document.getElementById('ac-meta-time').textContent = fmtTime(data.fetched_at);
        if (data.app_version) {
            document.getElementById('ac-meta-appver').textContent = '系统版本 v' + data.app_version;
        }

        if (!data.rows || data.rows.length === 0) {
            var empty = el('div', 'ac-empty', '目录为空：请点击「刷新目录」从当前生效服务器拉取最新目录。');
            box.appendChild(empty);
            return;
        }

        var table = document.createElement('table');
        table.className = 'table';
        table.style.cssText = 'width:100%;border-collapse:collapse;';
        var thead = document.createElement('thead');
        var hr = document.createElement('tr');
        hr.style.background = '#f8f9fa';
        ['应用', '简介', '版本', '操作'].forEach(function (h) {
            var th = document.createElement('th');
            th.style.cssText = 'padding:10px;border:1px solid #e9ecef;text-align:left;';
            th.textContent = h;
            hr.appendChild(th);
        });
        thead.appendChild(hr);
        table.appendChild(thead);
        var tbody = document.createElement('tbody');
        data.rows.forEach(function (row) { tbody.appendChild(renderRow(row)); });
        table.appendChild(tbody);
        box.appendChild(table);
    }

    function setBusy(busy) {
        var btns = document.querySelectorAll('#ac-list button');
        for (var i = 0; i < btns.length; i++) { btns[i].disabled = busy; }
        document.getElementById('ac-refresh').disabled = busy;
        document.getElementById('ac-save').disabled = busy;
    }

    function fetchRows(force, silent) {
        return api(force ? 'refresh' : 'list').then(function (data) {
            if (data.success === false) {
                if (data.message) banner(data.message, false);
                render(data);
                return data;
            }
            if (!silent && data.message) banner(data.message, true);
            render(data);
            return data;
        });
    }

    function loadList() {
        var box = document.getElementById('ac-list');
        box.innerHTML = '';
        box.appendChild(el('div', 'ac-loading', '正在加载目录…'));
        api('list').then(function (data) {
            // 目录缓存缺失，或目录来自其他服务器（切换服务器后旧缓存）→ 自动重新拉取当前服务器目录
            var stale = !!(data.server_url && data.source && data.source !== data.server_url);
            if (data.server_set && (!data.fetched_at || stale)) {
                fetchRows(true, false);
            } else {
                if (data.message) banner(data.message, data.success !== false);
                render(data);
            }
        });
    }

    function onInstall(ev) {
        var btn = ev.currentTarget;
        var id = btn.getAttribute('data-id');
        var title = btn.getAttribute('data-title');
        var ver = btn.getAttribute('data-version');
        var local = btn.getAttribute('data-local');
        var isUp = !!local && local !== '';
        var msg = isUp
            ? '检测到本地已安装 v' + local + '，确定升级「' + title + '」到 v' + ver + ' 吗？\n\n升级前会自动备份旧版本目录（数据库表不受影响）。'
            : '确定安装「' + title + '」v' + ver + ' 吗？\n\n安装包来自应用中心服务器，安装后插件将自动启用。';
        if (!window.confirm(msg)) { return; }

        setBusy(true);
        btn.textContent = isUp ? '正在升级…' : '正在安装…';
        api('install', { item_id: id }).then(function (data) {
            banner(data.message || '操作完成', data.success !== false);
            fetchRows(false, true);
        }).finally(function () {
            setBusy(false);
        });
    }

    // ============ 事件绑定 ============
    document.getElementById('ac-refresh').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-refresh"></i> 刷新中…';
        fetchRows(true, false).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-refresh"></i> 刷新目录';
        });
    });

    document.getElementById('ac-save').addEventListener('click', function () {
        var btn = this;
        var custom = currentCustom();
        var preset = currentPreset();
        var hosts = document.getElementById('ac-dlhosts').value.trim();
        var auto = document.getElementById('ac-auto').checked ? '1' : '0';
        var tls = document.getElementById('ac-tls').checked ? '1' : '0';
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader"></i> 保存中…';
        api('save_config', {
            preset: preset,
            custom_url: custom,
            download_hosts: hosts,
            auto_enable: auto,
            tls_loose: tls
        }).then(function (data) {
            if (data.success) {
                if (data.presets) window.__acPresets = data.presets;
                refreshNowLine();
                presetDim();
            }
            banner(data.message || (data.success ? '设置已保存' : '保存失败'), data.success !== false);
            // 保存生效后直接拉取新服务器目录
            if (data.success) { fetchRows(true, true); }
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-device-floppy"></i> 保存设置';
        });
    });

    // 选择变化即时预览
    var presetRadios = document.querySelectorAll('input[name="ac-preset"]');
    for (var i = 0; i < presetRadios.length; i++) {
        presetRadios[i].addEventListener('change', function () { refreshNowLine(); presetDim(); });
    }
    document.getElementById('ac-custom').addEventListener('input', function () {
        refreshNowLine();
        presetDim();
    });

    // ============ 初始化 ============
    // 服务端初始值（presets / preset / custom_url）已在 HTML 输出，直接构造预览所需映射
    window.__acPresets = <?= json_encode(array_values($presets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    refreshNowLine();
    presetDim();
    loadList();
})();
</script>
