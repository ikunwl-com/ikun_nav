<?php
/**
 * 邮箱通知插件 - 主文件
 *
 * 功能：
 *   1. PHP 原生 socket SMTP 发送（不依赖第三方库）
 *   2. 注册 site_submitted / feedback_submitted / site_approved / site_rejected 钩子
 *   3. 事件触发时自动发送邮件并记录日志
 *
 * 配置项（通过 settings 表管理）：
 *   plugin_notify_smtp_host     - SMTP 服务器地址
 *   plugin_notify_smtp_port     - SMTP 端口
 *   plugin_notify_smtp_user     - SMTP 用户名
 *   plugin_notify_smtp_pass     - SMTP 密码
 *   plugin_notify_smtp_secure   - 加密方式 (ssl/tls/none)
 *   plugin_notify_from_email    - 发件人邮箱
 *   plugin_notify_from_name     - 发件人名称
 *   plugin_notify_recipient     - 收件人邮箱（多个用英文逗号分隔）
 *   plugin_notify_on_submit     - 提交站点时通知 (1/0)
 *   plugin_notify_on_feedback   - 收到反馈时通知 (1/0)
 *   plugin_notify_on_approve    - 审核通过时通知 (1/0)
 *   plugin_notify_on_reject     - 审核拒绝时通知 (1/0)
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ============================================================
//  SMTP 原生 Socket 发送类
// ============================================================

class NotifySMTP
{
    /** @var resource|null socket 连接 */
    private $socket = null;

    /** @var string SMTP 服务器 */
    private $host;

    /** @var int SMTP 端口 */
    private $port;

    /** @var string 用户名 */
    private $user;

    /** @var string 密码 */
    private $pass;

    /** @var string 加密方式：ssl / tls / none */
    private $secure;

    /** @var int 超时（秒） */
    private $timeout = 15;

    /** @var string 最后一条错误信息 */
    public $lastError = '';

    /**
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     * @param string $secure  ssl|tls|none
     */
    public function __construct($host, $port, $user, $pass, $secure = 'ssl')
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = $secure;
    }

    /**
     * 发送邮件
     * @param string $fromEmail 发件人邮箱
     * @param string $fromName  发件人名称
     * @param array  $to        收件人数组 ['user@example.com', ...]
     * @param string $subject   主题
     * @param string $body      HTML 正文
     * @return bool
     */
    public function send($fromEmail, $fromName, array $to, $subject, $body)
    {
        $this->lastError = '';

        // 基本校验
        if (empty($this->host) || empty($this->port)) {
            $this->lastError = 'SMTP 主机或端口未配置';
            return false;
        }
        if (empty($fromEmail)) {
            $this->lastError = '发件人邮箱未配置';
            return false;
        }
        if (empty($to)) {
            $this->lastError = '收件人为空';
            return false;
        }

        // 建立连接
        if (!$this->connect()) {
            return false;
        }

        try {
            // 读取服务器欢迎消息
            if (!$this->readResponse(220)) {
                throw new \Exception('服务器未返回 220 就绪状态');
            }

            // EHLO
            $hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $this->writeCommand("EHLO {$hostname}");
            if (!$this->readResponse(250)) {
                throw new \Exception('EHLO 失败');
            }

            // TLS 升级
            if ($this->secure === 'tls') {
                $this->writeCommand('STARTTLS');
                if (!$this->readResponse(220)) {
                    throw new \Exception('STARTTLS 失败');
                }
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \Exception('TLS 加密握手失败');
                }
                // EHLO again after TLS
                $this->writeCommand("EHLO {$hostname}");
                if (!$this->readResponse(250)) {
                    throw new \Exception('TLS 后 EHLO 失败');
                }
            }

            // 认证
            if (!empty($this->user) && !empty($this->pass)) {
                $this->writeCommand('AUTH LOGIN');
                if (!$this->readResponse(334)) {
                    throw new \Exception('服务器不支持 AUTH LOGIN');
                }
                $this->writeCommand(base64_encode($this->user));
                if (!$this->readResponse(334)) {
                    throw new \Exception('用户名被拒绝');
                }
                $this->writeCommand(base64_encode($this->pass));
                if (!$this->readResponse(235)) {
                    throw new \Exception('密码认证失败');
                }
            }

            // MAIL FROM
            $this->writeCommand("MAIL FROM:<{$fromEmail}>");
            if (!$this->readResponse(250)) {
                throw new \Exception('MAIL FROM 被拒绝');
            }

            // RCPT TO
            foreach ($to as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient)) continue;
                $this->writeCommand("RCPT TO:<{$recipient}>");
                if (!$this->readResponse(250, [251])) {
                    throw new \Exception("收件人 {$recipient} 被拒绝");
                }
            }

            // DATA
            $this->writeCommand('DATA');
            if (!$this->readResponse(354)) {
                throw new \Exception('DATA 命令失败');
            }

            // 构建邮件内容
            $email = $this->buildEmailContent($fromEmail, $fromName, $to, $subject, $body);
            $this->writeCommand($email);
            $this->writeCommand("\r\n.");
            if (!$this->readResponse(250)) {
                throw new \Exception('邮件发送失败（DATA 结束后未收到 250）');
            }

            // QUIT
            $this->writeCommand('QUIT');

            $this->disconnect();
            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    /**
     * 建立 socket 连接
     * @return bool
     */
    private function connect()
    {
        $remote = '';

        if ($this->secure === 'ssl') {
            $remote = 'ssl://' . $this->host . ':' . $this->port;
            $this->socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        } else {
            // tls 或 none：先明文连接，TLS 后续升级
            $remote = $this->host . ':' . $this->port;
            $this->socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        }

        if (!$this->socket) {
            $this->lastError = "连接 {$remote} 失败: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);
        return true;
    }

    /**
     * 关闭连接
     */
    private function disconnect()
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * 发送 SMTP 命令
     * @param string $cmd
     */
    private function writeCommand($cmd)
    {
        fwrite($this->socket, $cmd . "\r\n");
    }

    /**
     * 读取服务器响应并检查状态码
     * @param int      $expectedCode 期望的响应码
     * @param int[]    $extraCodes   额外可接受的响应码
     * @return bool
     */
    private function readResponse($expectedCode, array $extraCodes = array())
    {
        $allowed = array_merge([$expectedCode], $extraCodes);
        $response = '';
        $code = 0;

        while (true) {
            $line = @fgets($this->socket, 515);
            if ($line === false) {
                $this->lastError = '读取服务器响应超时或连接断开';
                return false;
            }
            $response .= $line;

            // SMTP 多行响应：第 4 位为空格表示结束，为 - 表示还有后续行
            if (isset($line[3]) && $line[3] === ' ') {
                $code = (int)substr($line, 0, 3);
                break;
            }
            if (isset($line[3]) && $line[3] === '-' ) {
                // 多行，继续读
                continue;
            }
            // 没有第 4 位或其他情况，尝试解析
            $code = (int)substr($line, 0, 3);
            break;
        }

        if (!in_array($code, $allowed, true)) {
            $this->lastError = "SMTP 期望 {$expectedCode}，实际 {$code}: " . trim($response);
            return false;
        }

        return true;
    }

    /**
     * 构建 RFC 822 邮件内容
     * @param string $fromEmail
     * @param string $fromName
     * @param array  $to
     * @param string $subject
     * @param string $htmlBody
     * @return string
     */
    private function buildEmailContent($fromEmail, $fromName, array $to, $subject, $htmlBody)
    {
        $boundary = 'b_' . md5(uniqid('', true));

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . implode(', ', array_map(function ($addr) {
            return '<' . trim($addr) . '>';
        }, $to));
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        // 纯文本部分（简单去标签）
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');

        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody));
        $body .= "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody));
        $body .= "\r\n";
        $body .= "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * RFC 2047 编码邮件头
     * @param string $str
     * @return string
     */
    private function encodeHeader($str)
    {
        // 纯 ASCII 无需编码
        if (!preg_match('/[^\x20-\x7E]/', $str)) {
            return $str;
        }
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
}

// ============================================================
//  通知发送核心函数
// ============================================================

/**
 * 获取通知插件配置
 * @param string $key
 * @param mixed  $default
 * @return string
 */
function notify_config($key, $default = '')
{
    return (string) Plugin::config('notify', $key, $default);
}

/**
 * 获取收件人列表
 * @return array
 */
function notify_getRecipients()
{
    $raw = notify_config('recipient', '');
    if (empty($raw)) return [];
    $list = explode(',', $raw);
    $result = [];
    foreach ($list as $email) {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result[] = $email;
        }
    }
    return $result;
}

/**
 * 发送通知邮件并记录日志
 * @param string $type     通知类型
 * @param string $subject  邮件主题
 * @param string $body     HTML 邮件正文
 * @return bool
 */
function notify_send($type, $subject, $body, array $to = array())
{
    $recipients = empty($to) ? notify_getRecipients() : $to;
    if (empty($recipients)) {
        notify_log($type, '', $subject, $body, 0, '未配置收件人邮箱');
        return false;
    }

    $host     = notify_config('smtp_host', '');
    $port     = (int) notify_config('smtp_port', 465);
    $user     = notify_config('smtp_user', '');
    $pass     = notify_config('smtp_pass', '');
    $secure   = notify_config('smtp_secure', 'ssl');
    $fromMail = notify_config('from_email', '');
    $fromName = notify_config('from_name', '懒人导航');

    if (empty($host) || empty($fromMail)) {
        notify_log($type, implode(',', $recipients), $subject, $body, 0, 'SMTP 主机或发件人邮箱未配置');
        return false;
    }

    $smtp = new NotifySMTP($host, $port, $user, $pass, $secure);
    $ok = $smtp->send($fromMail, $fromName, $recipients, $subject, $body);
    $error = $ok ? '' : $smtp->lastError;

    notify_log($type, implode(',', $recipients), $subject, $body, $ok ? 1 : 0, $error);
    return $ok;
}

/**
 * 记录通知日志
 * @param string $type
 * @param string $recipient
 * @param string $subject
 * @param string $body
 * @param int    $status    0=失败 1=成功
 * @param string $error
 */
function notify_log($type, $recipient, $subject, $body, $status, $error = '')
{
    try {
        Database::execute(
            "INSERT INTO " . table('notify_logs') . " (type, recipient, subject, body, status, error) VALUES (?, ?, ?, ?, ?, ?)",
            [$type, $recipient, mb_substr($subject, 0, 300), mb_substr($body, 0, 60000), $status, mb_substr($error, 0, 500)]
        );
    } catch (\Exception $e) {
        if (class_exists('Logger')) {
            Logger::log('plugin_error', "notify 插件写日志失败: " . $e->getMessage());
        }
    }
}

/**
 * 构建邮件 HTML 模板
 * @param string $title
 * @param string $content  HTML 内容
 * @return string
 */
function notify_email_template($title, $content)
{
    $siteName = setting('site_name', '懒人导航');
    $siteUrl  = setting('site_url', '');
    $year     = date('Y');
    $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="min-width:320px;max-width:640px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
    <tr>
        <td style="padding:24px 32px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
            <span style="font-size:22px;color:#fff;font-weight:700;letter-spacing:1px;">{$siteName}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 32px 24px 32px;">
            <h2 style="margin:0 0 20px 0;font-size:18px;color:#333;line-height:1.5;">{$title}</h2>
            <div style="font-size:14px;color:#555;line-height:1.8;">
                {$content}
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding:20px 32px;border-top:1px solid #f0f0f0;">
            <p style="margin:0;font-size:12px;color:#999;line-height:1.6;">
                此邮件由 {$siteName} 系统自动发送，请勿直接回复。<br>
                &copy; {$year} {$siteName} {$httpHost}
            </p>
        </td>
    </tr>
</table>
</body>
</html>
HTML;
}

// ============================================================
//  钩子注册
// ============================================================

// --- 站点提交通知 ---
Plugin::registerHook('site_submitted', function ($data) {
    if (notify_config('on_submit', '1') !== '1') return;

    $siteName = isset($data['name']) ? $data['name'] : '未知站点';
    $siteUrl  = isset($data['url']) ? $data['url'] : '';
    $catId    = isset($data['category_id']) ? $data['category_id'] : 0;
    $status   = isset($data['status']) ? $data['status'] : 'pending';
    $ip       = isset($data['ip']) ? $data['ip'] : '';
    $email    = isset($data['email']) ? $data['email'] : '';
    $id       = isset($data['id']) ? $data['id'] : 0;

    $statusText = $status === 'published' ? '已直接发布' : '待审核';

    // 尝试获取分类名
    $catName = '未分类';
    if ($catId) {
        try {
            $cat = Database::queryOne("SELECT name FROM " . table('categories') . " WHERE id = ?", [$catId]);
            if ($cat) $catName = $cat['name'];
        } catch (\Exception $e) {}
    }

    $content = "<p style=\"margin:0 0 12px 0;\">有新站点提交，详情如下：</p>";
    $content .= "<table style=\"width:100%;font-size:14px;border-collapse:collapse;\">";
    $content .= "<tr><td style=\"padding:6px 0;color:#999;width:80px;\">站点名称</td><td style=\"padding:6px 0;color:#333;font-weight:600;\">" . htmlspecialchars($siteName) . "</td></tr>";
    $content .= "<tr><td style=\"padding:6px 0;color:#999;\">站点URL</td><td style=\"padding:6px 0;color:#333;\"><a href=\"" . htmlspecialchars($siteUrl) . "\" style=\"color:#667eea;text-decoration:none;\">" . htmlspecialchars($siteUrl) . "</a></td></tr>";
    $content .= "<tr><td style=\"padding:6px 0;color:#999;\">分类</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($catName) . "</td></tr>";
    $content .= "<tr><td style=\"padding:6px 0;color:#999;\">状态</td><td style=\"padding:6px 0;color:#333;\">" . $statusText . "</td></tr>";
    if ($email) {
        $content .= "<tr><td style=\"padding:6px 0;color:#999;\">联系邮箱</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($email) . "</td></tr>";
    }
    $content .= "<tr><td style=\"padding:6px 0;color:#999;\">提交IP</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($ip) . "</td></tr>";
    $content .= "</table>";

    $adminUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . '/admin/review.php';
    if ($status !== 'published') {
        $content .= "<p style=\"margin:20px 0 0 0;\"><a href=\"" . htmlspecialchars($adminUrl) . "\" style=\"display:inline-block;padding:10px 24px;background:#667eea;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;\">前往审核</a></p>";
    }

    $body = notify_email_template('新站点提交：' . $siteName, $content);
    notify_send('submitted', '【' . setting('site_name', '懒人导航') . '】新站点提交：' . $siteName, $body);
}, 10);

// --- 反馈通知 ---
Plugin::registerHook('feedback_submitted', function ($data) {
    if (notify_config('on_feedback', '1') !== '1') return;

    $siteId  = isset($data['site_id']) ? $data['site_id'] : 0;
    $type    = isset($data['type']) ? $data['type'] : '';
    $content = isset($data['content']) ? $data['content'] : '';
    $email   = isset($data['email']) ? $data['email'] : '';
    $ip      = isset($data['ip']) ? $data['ip'] : '';

    $typeMap = [
        'broken'   => '报错（死链/打不开）',
        'suggest'  => '建议',
        'complaint'=> '投诉',
        'other'    => '其他',
    ];
    $typeText = isset($typeMap[$type]) ? $typeMap[$type] : $type;

    // 尝试获取站点名
    $siteName = '未知站点';
    if ($siteId) {
        try {
            $site = Database::queryOne("SELECT name, url FROM " . table('sites') . " WHERE id = ?", [$siteId]);
            if ($site) $siteName = $site['name'] . ' (' . $site['url'] . ')';
        } catch (\Exception $e) {}
    }

    $html = "<p style=\"margin:0 0 12px 0;\">收到新的用户反馈：</p>";
    $html .= "<table style=\"width:100%;font-size:14px;border-collapse:collapse;\">";
    $html .= "<tr><td style=\"padding:6px 0;color:#999;width:80px;\">关联站点</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($siteName) . "</td></tr>";
    $html .= "<tr><td style=\"padding:6px 0;color:#999;\">反馈类型</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($typeText) . "</td></tr>";
    $html .= "<tr><td style=\"padding:6px 0;color:#999;\">联系方式</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($email ?: '未留') . "</td></tr>";
    $html .= "<tr><td style=\"padding:6px 0;color:#999;\">反馈内容</td><td style=\"padding:6px 0;color:#333;\">" . nl2br(htmlspecialchars($content)) . "</td></tr>";
    $html .= "<tr><td style=\"padding:6px 0;color:#999;\">提交IP</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($ip) . "</td></tr>";
    $html .= "</table>";

    $body = notify_email_template('用户反馈：' . $typeText, $html);
    notify_send('feedback', '【' . setting('site_name', '懒人导航') . '】用户反馈：' . $typeText, $body);
}, 10);

// --- 审核通过通知 ---
Plugin::registerHook('site_approved', function ($data) {
    if (notify_config('on_approve', '1') !== '1') return;

    $id = isset($data['id']) ? $data['id'] : 0;
    if (!$id) return;

    // 获取站点信息
            $site = null;
            try {
                $site = Database::queryOne("SELECT name, url, submit_email FROM " . table('sites') . " WHERE id = ?", [$id]);
            } catch (\Exception $e) {}

            $siteName = $site ? $site['name'] : "ID:{$id}";
            $siteUrl  = $site ? $site['url'] : '';

            // 通知管理员
            $content = "<p style=\"margin:0 0 12px 0;\">以下站点已审核通过并发布：</p>";
            $content .= "<table style=\"width:100%;font-size:14px;border-collapse:collapse;\">";
            $content .= "<tr><td style=\"padding:6px 0;color:#999;width:80px;\">站点名称</td><td style=\"padding:6px 0;color:#333;font-weight:600;\">" . htmlspecialchars($siteName) . "</td></tr>";
            if ($siteUrl) {
                $content .= "<tr><td style=\"padding:6px 0;color:#999;\">站点URL</td><td style=\"padding:6px 0;color:#333;\"><a href=\"" . htmlspecialchars($siteUrl) . "\" style=\"color:#667eea;text-decoration:none;\">" . htmlspecialchars($siteUrl) . "</a></td></tr>";
            }
            $content .= "</table>";
            $body = notify_email_template('站点审核通过：' . $siteName, $content);
            notify_send('approved', '【' . setting('site_name', '懒人导航') . '】站点审核通过：' . $siteName, $body);

            // 通知提交者（仅在提交者有邮箱时）
            $submitEmail = isset($site['submit_email']) ? $site['submit_email'] : '';
            if (!empty($submitEmail)) {
                $userBody = notify_email_template('您的站点已通过审核', "<p>您提交的站点 <strong>" . htmlspecialchars($siteName) . "</strong> 已通过管理员审核，现已发布。</p>" . ($siteUrl ? "<p><a href=\"" . htmlspecialchars($siteUrl) . "\" style=\"color:#667eea;text-decoration:none;\">" . htmlspecialchars($siteUrl) . "</a></p>" : '') . "<p style=\"margin-top:24px;\">感谢您对懒人导航的支持！</p>");
                notify_send('approved', '【' . setting('site_name', '懒人导航') . '】您的站点已通过审核', $userBody, [$submitEmail]);
            }
}, 10);

// --- 审核拒绝通知 ---
Plugin::registerHook('site_rejected', function ($data) {
    if (notify_config('on_reject', '1') !== '1') return;

    $id = isset($data['id']) ? $data['id'] : 0;
    if (!$id) return;

            $site = null;
            try {
                $site = Database::queryOne("SELECT name, url, submit_email FROM " . table('sites') . " WHERE id = ?", [$id]);
            } catch (\Exception $e) {}

            $siteName = $site ? $site['name'] : "ID:{$id}";
            $siteUrl  = $site ? $site['url'] : '';

            // 通知管理员
            $content = "<p style=\"margin:0 0 12px 0;\">以下站点审核未通过（已拒绝）：</p>";
            $content .= "<table style=\"width:100%;font-size:14px;border-collapse:collapse;\">";
            $content .= "<tr><td style=\"padding:6px 0;color:#999;width:80px;\">站点名称</td><td style=\"padding:6px 0;color:#333;font-weight:600;\">" . htmlspecialchars($siteName) . "</td></tr>";
            if ($siteUrl) {
                $content .= "<tr><td style=\"padding:6px 0;color:#999;\">站点URL</td><td style=\"padding:6px 0;color:#333;\">" . htmlspecialchars($siteUrl) . "</td></tr>";
            }
            $content .= "</table>";
            $body = notify_email_template('站点审核拒绝：' . $siteName, $content);
            notify_send('rejected', '【' . setting('site_name', '懒人导航') . '】站点审核拒绝：' . $siteName, $body);

            // 通知提交者（仅在提交者有邮箱时）
            $submitEmail = isset($site['submit_email']) ? $site['submit_email'] : '';
            if (!empty($submitEmail)) {
                $userBody = notify_email_template('您的站点审核未通过', "<p>您提交的站点 <strong>" . htmlspecialchars($siteName) . "</strong> 审核未通过。</p>" . ($siteUrl ? "<p>" . htmlspecialchars($siteUrl) . "</p>" : '') . "<p style=\"margin-top:24px;\">如需了解原因，请联系管理员。</p>");
                notify_send('rejected', '【' . setting('site_name', '懒人导航') . '】您的站点审核未通过', $userBody, [$submitEmail]);
            }
}, 10);
