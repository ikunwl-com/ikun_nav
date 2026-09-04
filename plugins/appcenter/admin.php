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

$serverUrl  = appcenter_server_url();
$serverSet  = $serverUrl !== '';
$autoEnable = appcenter_auto_enable();
$dlHosts    = implode(', ', appcenter_download_hosts());
?>
<style>
.ac-info { background:#f0f7ff; border:1px solid #b3d9ff; padding:12px 14px; border-radius:6px; margin-bottom:16px; font-size:13px; line-height:1.8; color:#1f3a5f; }
.ac-info strong { color:#0b4a8f; }
.ac-card { margin-bottom:16px; }
.ac-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
.ac-row label { width:150px; flex:0 0 150px; font-size:13px; color:#374151; padding-top:7px; }
.ac-row .ac-field { flex:1; min-width:260px; }
.ac-row input[type="text"] { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #d1d5db; border-radius:5px; font-size:13px; }
.ac-row input[type="text"]:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
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
</style>

<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="ti ti-apps"></i>
  <strong>应用中心</strong>：从你的应用中心服务器拉取插件 / 主题目录，在线一键安装与升级。
  首次使用请先在下方填写<strong>服务器地址</strong>，然后点击<strong>刷新目录</strong>。
  升级前自动备份旧版本、失败自动回滚；安装新插件默认自动启用（可在下方关闭）。
</div>

<div id="ac-banner"></div>

<!-- ============ 服务器设置 ============ -->
<div class="card ac-card">
  <div class="card-header"><span class="card-title"><i class="ti ti-server"></i> 服务器设置</span></div>
  <div style="padding:2px 18px 16px;">
    <div class="ac-row">
      <label for="ac-server">服务器地址</label>
      <div class="ac-field">
        <input type="text" id="ac-server" placeholder="https://apps.example.com"
               value="<?= Security::eAttr($serverUrl) ?>" autocomplete="off" spellcheck="false">
        <div class="ac-hint">应用中心服务器基地址（与主程序在线更新的服务器同款协议），本站会自动请求 <code>{地址}/list.php</code> 拉取目录。留空则应用中心停用。</div>
      </div>
    </div>
    <div class="ac-row">
      <label for="ac-dlhosts">下载白名单</label>
      <div class="ac-field">
        <input type="text" id="ac-dlhosts" placeholder="留空表示仅允许与服务器同域"
               value="<?= Security::eAttr($dlHosts) ?>" autocomplete="off" spellcheck="false">
        <div class="ac-hint">可选：当安装包托管在与目录服务器不同域（如 CDN 子域名 download.example.com）时，在此添加域名白名单，多个用逗号或空格分隔。</div>
      </div>
    </div>
    <div class="ac-row">
      <label></label>
      <div class="ac-field">
        <div class="ac-check">
          <input type="checkbox" id="ac-auto" <?= $autoEnable ? 'checked' : '' ?>>
          <label for="ac-auto" style="padding:0;">安装新插件后自动启用（升级保持原启用状态）</label>
        </div>
        <div class="ac-actions" style="margin-top:12px;">
          <button type="button" class="ac-btn ac-btn-primary" id="ac-save"><i class="ti ti-device-floppy"></i> 保存设置</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ 应用目录 ============ -->
<div class="card ac-card">
  <div class="card-header">
    <span class="card-title"><i class="ti ti-cloud-download"></i> 应用目录</span>
    <span style="flex:1"></span>
    <button type="button" class="ac-btn ac-btn-primary" id="ac-refresh"><i class="ti ti-refresh"></i> 刷新目录</button>
  </div>
  <div style="padding:2px 18px 16px;">
    <div class="ac-list-meta">
      <span id="ac-meta-source" style="display:none;"></span>
      <span id="ac-meta-time"></span>
      <span id="ac-meta-appver"></span>
    </div>
    <div id="ac-list">
      <div class="ac-loading"><i class="ti ti-loader" style="animation:spin 1s linear infinite;display:inline-block;"></i> 正在加载目录…</div>
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

    // ============ 行渲染 ============
    function renderRow(row) {
        var tr = document.createElement('tr');
        tr.style.cssText = '';

        // --- 应用列 ---
        var td1 = document.createElement('td');
        td1.style.cssText = 'padding:12px;border:1px solid #e9ecef;';
        var nameLine = el('div');
        nameLine.appendChild(el('strong', '', row.title || row.id));
        var badge = el('span', 'ac-badge ' + (row.type === 'theme' ? 'ac-badge-theme' : 'ac-badge-plugin'),
            row.type === 'theme' ? '主题' : '插件');
        nameLine.appendChild(badge);
        if (row.type === 'plugin' && row.id === 'appcenter') {
            nameLine.appendChild(el('span', 'ac-badge ac-badge-builtin', '内置'));
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
            var lt = el('div', 'ac-row-note', '本地标题：' + row.local_title);
            td1.appendChild(lt);
        }
        tr.appendChild(td1);

        // --- 说明列 ---
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

        // --- 版本列 ---
        var td3 = document.createElement('td');
        td3.style.cssText = 'padding:12px;border:1px solid #e9ecef;white-space:nowrap;';
        var v1 = el('div', 'ac-ver');
        if (row.installed) {
            v1.appendChild(document.createTextNode('本地 '));
            var lb = el('b', '', row.local_version ? 'v' + row.local_version : '未知');
            v1.appendChild(lb);
            if (row.can_upgrade) {
                var up = el('span', 'ac-local', ' → ');
                v1.appendChild(up);
                var nb = el('b', '', 'v' + row.version);
                nb.style.color = '#b45309';
                v1.appendChild(nb);
            }
        } else {
            v1.appendChild(document.createTextNode('最新 '));
            var nb2 = el('b', '', 'v' + row.version);
            v1.appendChild(nb2);
        }
        td3.appendChild(v1);
        var size = fmtSize(row.size);
        if (size) {
            td3.appendChild(el('div', 'ac-row-note', size));
        }
        tr.appendChild(td3);

        // --- 状态/操作列 ---
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
        // action === 'none'：无需操作，状态徽标已说明（已是最新 / 已安装）
        td4.appendChild(wrap);
        tr.appendChild(td4);

        return tr;
    }

    function render(data) {
        var box = document.getElementById('ac-list');
        box.innerHTML = '';

        var source = document.getElementById('ac-meta-source');
        if (data.source) {
            source.style.display = '';
            source.innerHTML = '';
            source.appendChild(document.createTextNode('服务器：'));
            var a = document.createElement('a');
            a.href = data.source;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = data.source;
            source.appendChild(a);
        } else {
            source.style.display = 'none';
        }
        document.getElementById('ac-meta-time').textContent = fmtTime(data.fetched_at);
        var ver = document.getElementById('ac-meta-appver');
        if (data.app_version) {
            ver.textContent = '系统版本 v' + data.app_version;
        }

        if (!data.server_set) {
            var hint = el('div', 'ac-empty',
                '尚未配置服务器地址：请在上方填写应用中心服务器地址并保存，然后点击「刷新目录」。');
            box.appendChild(hint);
            return;
        }
        if (!data.rows || data.rows.length === 0) {
            var empty = el('div', 'ac-empty', '目录为空：请点击「刷新目录」从服务器拉取最新目录。');
            box.appendChild(empty);
            return;
        }

        var table = document.createElement('table');
        table.className = 'table';
        table.style.cssText = 'width:100%;border-collapse:collapse;';

        var thead = document.createElement('thead');
        var hr = document.createElement('tr');
        hr.style.background = '#f8f9fa';
        var heads = ['应用', '简介', '版本', '操作'];
        heads.forEach(function (h) {
            var th = document.createElement('th');
            th.style.cssText = 'padding:10px;border:1px solid #e9ecef;text-align:left;';
            th.textContent = h;
            hr.appendChild(th);
        });
        thead.appendChild(hr);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        data.rows.forEach(function (row) {
            tbody.appendChild(renderRow(row));
        });
        table.appendChild(tbody);
        box.appendChild(table);
    }

    function setBusy(busy) {
        document.querySelectorAll('#ac-list button').forEach(function (b) { b.disabled = busy; });
        var ref = document.getElementById('ac-refresh');
        if (ref) ref.disabled = busy;
        var save = document.getElementById('ac-save');
        if (save) save.disabled = busy;
    }

    function loadList() {
        var box = document.getElementById('ac-list');
        box.innerHTML = '';
        var loading = el('div', 'ac-loading', '正在加载目录…');
        box.appendChild(loading);
        api('list').then(function (data) {
            if (!data.success && data.message) { banner(data.message, false); }
            render(data);
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
            loadList();
        }).finally(function () {
            setBusy(false);
        });
    }

    // ============ 事件绑定 ============
    document.getElementById('ac-refresh').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-refresh"></i> 刷新中…';
        api('refresh').then(function (data) {
            banner(data.message || '目录为空', data.success !== false);
            render(data);
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-refresh"></i> 刷新目录';
        });
    });

    document.getElementById('ac-save').addEventListener('click', function () {
        var btn = this;
        var server = document.getElementById('ac-server').value.trim();
        var hosts = document.getElementById('ac-dlhosts').value.trim();
        var auto = document.getElementById('ac-auto').checked ? '1' : '0';
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader"></i> 保存中…';
        api('save_config', {
            server_url: server,
            download_hosts: hosts,
            auto_enable: auto
        }).then(function (data) {
            banner(data.message || (data.success ? '设置已保存' : '保存失败'), data.success !== false);
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-device-floppy"></i> 保存设置';
        });
    });

    // ============ 初始化 ============
    loadList();
})();
</script>
