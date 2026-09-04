# 懒人导航 · 开放 API 对接文档（API Key）

> 适用版本：懒人导航（含开放 API 模块）
> 面向读者：需要对接导航站数据与功能的 **App / 小程序 / 网站 / 合作方开发**
> 后台入口：管理后台 →「API 密钥」→ 创建 Key（可设置每分钟/每小时/每天调用上限）

开放 API 统一以 `open/` 开头，覆盖：**查询**、**发布（提交站点）**、**编辑**、**删除**、**分类管理**，以及**内置插件数据接口**（文章、虫洞联盟、友情链接、蜘蛛来访等，插件启用后自动生效）。API Key 视为受信凭证，写接口可操作任意站点（等同后台能力），请只把 Key 交给可信的开发方。

---

## 1. 快速开始

1. 登录后台 →「API 密钥」→ 创建 API Key（把生成的完整 Key 复制保存，页面只展示前 16 位，可点复制按钮）。
2. 用任意 HTTP 客户端携带 `X-API-Key` 请求：

```bash
curl -H "X-API-Key: ak_xxxxxxxxxxxxxxxx" "https://你的域名/api/open/sites?page=1&limit=5"
```

3. 收到类似响应即成功：

```json
{
  "success": true,
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1,
        "name": "示例站",
        "url": "https://example.com",
        "domain": "example.com",
        "category_id": 1,
        "description": "一句话简介",
        "tags": ["工具"],
        "br_pc": 3,
        "br_mobile": 2,
        "max_br": 3,
        "views": 100,
        "clicks": 20,
        "status": "published"
      }
    ],
    "total": 1,
    "page": 1,
    "limit": 5,
    "total_pages": 1
  }
}
```

---

## 2. 通用约定

### 2.1 接口地址

```
https://你的域名/api/{endpoint}
```

`{endpoint}` 形如 `open/sites`。支持 RESTful 风格 `/api/open/sites` 或传统 `?endpoint=` 参数方式。

### 2.2 鉴权（三选一）

| 方式 | 示例 |
|---|---|
| 请求头（推荐） | `X-API-Key: ak_xxx` |
| URL 参数 | `?api_key=ak_xxx` |
| POST JSON 字段 | `{"api_key": "ak_xxx", ...}` |

### 2.3 请求体

- GET 接口：URL 参数
- POST 接口：`Content-Type: application/json`，JSON 请求体
- 编辑类接口支持**部分更新**：传哪些字段就更新哪些字段

### 2.4 响应格式

```json
{ "success": true, "code": 0, "message": "ok", "data": { } }
```

- 列表类返回分页结构：`data.list` / `data.total` / `data.page` / `data.limit` / `data.total_pages`
- 失败响应：`{ "success": false, "code": 错误码, "message": "原因" }`

### 2.5 限流（响应头）

| 响应头 | 含义 |
|---|---|
| `X-RateLimit-Limit` | 当前周期（分钟）限制次数 |
| `X-RateLimit-Remaining` | 当前周期剩余次数 |
| `X-RateLimit-Reset` | 限流重置时间戳 |

超出限制返回 `429`，错误码 `42901`。额度不够可在后台编辑对应 Key 提高。

### 2.6 错误码

| code | HTTP | 含义 |
|---|---|---|
| `40101` | 401 | 缺少 API Key |
| `40102` | 401 | API Key 无效或已过期 |
| `42901` | 429 | 调用频率超出限制 |
| `40001` | 400 | 参数错误 |
| `40301` | 403 | 接口所属插件未启用 |
| `40401` | 404 | 资源不存在 |
| `40901` | 409 | 冲突（如分类下仍有站点无法删除） |

---

## 3. 站点查询接口

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/sites` | GET | 站点列表（分类/分页/排序） |
| `open/site` | GET | 站点详情（id） |
| `open/site/check` | GET | 网址是否收录 / 审核状态 |
| `open/site/related` | GET | 相关站点 |
| `open/featured` | GET | 推荐位站点 |
| `open/rank` | GET | 排行榜 |
| `open/search` | GET | 搜索 |
| `open/stats` | GET | 整体统计 |

### 3.1 站点列表 `GET /api/open/sites`

参数：

| 参数 | 必填 | 说明 |
|---|---|---|
| category | 否 | `all`（默认）或分类 slug |
| page | 否 | 页码，默认 1，最大 100 |
| limit | 否 | 每页条数，默认 20，最大 100 |
| sort | 否 | `newest`(默认)/`views`/`clicks`/`br`/`name` |

```bash
curl -H "X-API-Key: ak_xxx" "https://你的域名/api/open/sites?category=all&page=1&limit=20&sort=newest"
```

### 3.2 站点详情 `GET /api/open/site?id=1`

```bash
curl -H "X-API-Key: ak_xxx" "https://你的域名/api/open/site?id=1"
```

返回 `data` 为单个站点对象（字段同列表项，另含 `rating_avg/rating_count/related` 等，视版本而定，以实际返回为准）。

### 3.3 网址收录查询 `GET /api/open/site/check?url=...`

常用于：提交前查重、查询收录进度。

```bash
curl -H "X-API-Key: ak_xxx" "https://你的域名/api/open/site/check?url=https://example.com"
```

响应：

```json
{
  "success": true,
  "code": 0,
  "data": {
    "found": true,
    "id": 1,
    "name": "示例站",
    "url": "https://example.com",
    "domain": "example.com",
    "status": "published",
    "status_text": "已收录",
    "created_at": "2025-01-01 10:00:00"
  }
}
```

`found=false` 表示未收录；`status` 为 `pending`（待审核）或 `published`（已收录）。

### 3.4 相关站点 `GET /api/open/site/related?id=1&limit=6`

返回与指定站点同分类的站点，`limit` 默认 6、最大 12。

### 3.5 推荐位 `GET /api/open/featured?limit=12`

`limit` 默认 12、最大 50。

### 3.6 排行榜 `GET /api/open/rank?type=views&limit=20`

`type`：`views`(默认)/`clicks`/`br_pc`/`br_mobile`/`newest`；`limit` 默认 20、最大 100。

### 3.7 搜索 `GET /api/open/search?q=关键词&page=1&limit=20`

| 参数 | 必填 | 说明 |
|---|---|---|
| q | 是 | 关键词 |
| page / limit | 否 | 分页，同列表接口 |

### 3.8 整体统计 `GET /api/open/stats`

返回站点总数、已发布数、待审核数、总浏览/点击、平均权重、分类数、近 7 天新增等。

---

## 4. 站点发布 / 编辑 / 删除接口

> 带 API Key 调用等同后台录入：**发布默认直接上架**（`status=pending` 可改为待审核）；编辑/删除可作用于任意站点。

### 4.1 发布 / 提交站点 `POST /api/open/submit`

请求体：

```json
{
  "name": "示例站",
  "url": "https://example.com",
  "category_id": 1,
  "description": "一句话简介（最多200字）",
  "tags": ["工具", "效率"],
  "email": "contact@example.com",
  "br_pc": 0,
  "br_mobile": 0,
  "status": "published"
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| name | 是 | 站点名称（最多100字） |
| url | 是 | 站点网址（自动补全 https://） |
| category_id | 是 | 分类 ID（用 `open/categories` 查询） |
| description | 否 | 简介（最多200字） |
| tags | 否 | 标签：数组、逗号分隔字符串或 JSON 数组字符串 |
| email | 否 | 联系邮箱 |
| br_pc / br_mobile / br_360 / br_shenma | 否 | 权重 0-10 |
| status | 否 | `published`(默认)/`pending` |

```bash
curl -X POST -H "X-API-Key: ak_xxx" -H "Content-Type: application/json" \
  -d '{"name":"示例站","url":"https://example.com","category_id":1,"tags":["工具"]}' \
  "https://你的域名/api/open/submit"
```

响应：

```json
{ "success": true, "code": 0, "message": "发布成功", "id": 12, "status": "published" }
```

> 前台提交页也可以直接调这个接口（免 CSRF）；提交前建议先调 `open/site/check` 查重。

### 4.2 编辑站点 `POST /api/open/site/update`

部分更新：传哪些字段改哪些。

```json
{ "id": 12, "name": "新名称", "description": "新简介", "category_id": 2, "status": "published" }
```

| 字段 | 说明 |
|---|---|
| id | 必填，站点 ID |
| name / url | 名称 / 网址 |
| category_id | 分类 ID（需存在） |
| description | 简介（可传空字符串清空） |
| tags | 标签（格式同发布） |
| status | `published` / `pending` |
| br_pc / br_mobile / br_360 / br_shenma | 权重 0-10 |
| is_featured | 0 / 1 |
| sort_order | 排序（>=0） |

```bash
curl -X POST -H "X-API-Key: ak_xxx" -H "Content-Type: application/json" \
  -d '{"id":12,"name":"新名称","status":"published"}' \
  "https://你的域名/api/open/site/update"
```

### 4.3 删除站点 `POST /api/open/site/delete`

```json
{ "id": 12 }
```

```bash
curl -X POST -H "X-API-Key: ak_xxx" -H "Content-Type: application/json" \
  -d '{"id":12}' "https://你的域名/api/open/site/delete"
```

---

## 5. 分类管理接口

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/categories` | GET | 分类列表 |
| `open/category/create` | POST | 新增分类 |
| `open/category/update` | POST | 编辑分类 |
| `open/category/delete` | POST | 删除分类 |

### 5.1 分类列表 `GET /api/open/categories`

返回分类数组（id/name/slug/icon/sort_order/show_count/is_show/seo_title/seo_desc/site_count）。默认仅返回前台展示的分类，管理端传 `all=1` 返回全部分类（含隐藏）。

### 5.2 新增分类 `POST /api/open/category/create`

```json
{
  "name": "AI 工具",
  "slug": "ai",
  "icon": "category",
  "sort_order": 0,
  "show_count": 12,
  "is_show": 1,
  "seo_title": "",
  "seo_desc": "",
  "fill_sort": "newest"
}
```

> `slug` 必填且全站唯一，仅允许小写字母、数字、中划线。`fill_sort`：`newest`(默认)/`views`/`br`。

### 5.3 编辑分类 `POST /api/open/category/update`

```json
{ "id": 1, "name": "AI 工具箱", "is_show": 0 }
```

### 5.4 删除分类 `POST /api/open/category/delete`

```json
{ "id": 1 }
```

分类下仍有站点时返回 `409 / 40901`，需先迁移或删除站点（与后台行为一致）。

---

## 6. 系统查询接口

### 6.1 插件列表 `GET /api/open/plugins`

返回已安装插件（name/title/version/author/description/enabled/builtin/has_open_api），用于客户端判断哪些插件接口可用。

---

## 7. 内置插件接口（启用插件后自动生效）

插件接口同样需要 API Key。**插件停用时接口返回 `403 / 40301`**，启用后立即可用；可在后台「API 密钥」使用说明中看到已启用插件的实时清单与 curl 示例。

### 7.1 文章插件（article）

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/article/list` | GET | 文章列表（page/limit/status=published\|draft\|pending\|all） |
| `open/article/detail` | GET | 文章详情（id 或 slug，含正文） |
| `open/article/publish` | POST | 发布文章 |
| `open/article/update` | POST | 编辑文章（部分更新） |
| `open/article/delete` | POST | 删除文章 |

发布示例：

```json
{
  "title": "公告标题",
  "content": "<p>正文 HTML</p>",
  "excerpt": "摘要",
  "author": "站长",
  "category": "公告",
  "tags": ["公告"],
  "status": "published"
}
```

### 7.2 虫洞联盟插件（wormhole）

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/wormhole/members` | GET | 联盟成员列表（status=all\|manual\|auto\|pending） |
| `open/wormhole/stats` | GET | 成员数量统计 |
| `open/wormhole/random` | GET | 随机成员（limit，用于外站/小程序展示） |

### 7.3 友情链接插件（friendlink）

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/friendlinks` | GET | 友链列表（默认仅启用中；status=all 返回全部） |
| `open/friendlink/create` | POST | 新增友链 |
| `open/friendlink/update` | POST | 编辑友链 |
| `open/friendlink/delete` | POST | 删除友链 |

### 7.4 蜘蛛来访插件（spider）

| 端点 | 方法 | 说明 |
|---|---|---|
| `open/spider/stats` | GET | 来访汇总（range=today\|yesterday\|7\|30） |
| `open/spider/trend` | GET | 近 30 天每日趋势 |
| `open/spider/visits` | GET | 来访明细（page/limit/engine） |

> 插件机制：每个插件目录下可放 `api.php` 声明接口，启用即自动注册并出现在后台文档中（系统内置插件均已提供）。第三方插件开发者可参照 `plugins/article/api.php` 实现自己的开放接口。

---

## 8. 代码示例

### 8.1 浏览器 fetch（前台提交页直接发布）

```js
fetch('https://你的域名/api/open/site/check?url=' + encodeURIComponent('https://example.com'), {
  headers: { 'X-API-Key': 'ak_xxx' }
})
  .then(r => r.json())
  .then(res => {
    if (res.data && res.data.found) { alert('该站点已收录：' + res.data.status_text); return; }
    // 未收录则提交
    return fetch('https://你的域名/api/open/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-API-Key': 'ak_xxx' },
      body: JSON.stringify({ name: '示例站', url: 'https://example.com', category_id: 1 })
    });
  })
  .then(r => r && r.json())
  .then(res => res && console.log(res));
```

### 8.2 微信小程序

```js
// 获取站点列表
wx.request({
  url: 'https://你的域名/api/open/sites',
  data: { page: 1, limit: 20, sort: 'newest' },
  header: { 'X-API-Key': 'ak_xxx' },
  success(res) { console.log(res.data); }
});

// 提交 / 发布站点
wx.request({
  url: 'https://你的域名/api/open/submit',
  method: 'POST',
  header: { 'Content-Type': 'application/json', 'X-API-Key': 'ak_xxx' },
  data: { name: '示例站', url: 'https://example.com', category_id: 1 },
  success(res) { console.log(res.data); }
});
```

### 8.3 PHP（后端聚合）

```php
$ch = curl_init('https://你的域名/api/open/sites?page=1&limit=20');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-API-Key: ak_xxx'],
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);
```

---

## 9. 常见问题

1. **返回 40102 无效 Key**：Key 被删除/禁用或已过期，回后台「API 密钥」检查。
2. **返回 42901 超限**：降低调用频率，或在后台编辑该 Key 提高每分钟/每小时/每天上限。
3. **返回 40301 插件未启用**：接口属于某个插件，去后台「插件管理」启用该插件即可。
4. **Key 泄露了怎么办**：后台删除或停用该 Key（停用后请求立即失效），再新建一个。
5. **需要 CSRF Token 吗**：不需要。`open/*` 接口用 API Key 鉴权，免 CSRF。
6. **想给不同 App 不同额度**：每个 App 单独创建一个 Key 并设置不同限流即可，后台可看到每个 Key 的调用次数与最后调用时间。
