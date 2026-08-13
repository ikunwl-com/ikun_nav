<?php
/**
 * 安全核心模块
 * 提供：输入过滤、输出转义、CSRF防护、频率限制、Session安全
 */

class Security
{
    /** CSRF Token 生成 */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** CSRF Token 校验（滑动窗口：接受当前或上一个 token，兼容多标签页/后退按钮） */
    public static function verifyCSRFToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($token)) {
            Logger::log('security_csrf', "[CSRF校验失败] Token为空，IP=" . self::getClientIP());
            return false;
        }
        $current  = $_SESSION['csrf_token'] ?? '';
        $previous = $_SESSION['csrf_token_previous'] ?? '';
        $valid = ($current && hash_equals($current, $token))
              || ($previous && hash_equals($previous, $token));
        if (!$valid) {
            Logger::log('security_csrf', "[CSRF校验失败] Token不匹配，IP=" . self::getClientIP());
            return false;
        }
        // 验证成功后轮换 Token（防重放），同时保留旧 token 到滑动窗口
        $_SESSION['csrf_token_previous'] = $current;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }

    /** 输出 CSRF 隐藏字段 */
    public static function csrfField(): string
    {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }

    /**
     * HTML 输出转义（防 XSS）
     * @param mixed $value
     * @param bool $doubleEncode 是否二次编码
     * @return string
     */
    public static function e($value, bool $doubleEncode = false): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }

    /**
     * 属性值转义（用于 HTML 属性）
     */
    public static function eAttr($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * URL 转义（用于 href/src 属性）
     * 防止 javascript: 协议注入
     */
    public static function safeUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) return '';

        // 已有完整协议，直接返回
        if (preg_match('/^(https?:\/\/)/i', $url)) {
            return self::eAttr($url);
        }

        // 相对路径或锚点
        if (preg_match('/^(\/|#)/', $url)) {
            return self::eAttr($url);
        }

        // 纯域名或 IP（导航站常见格式），自动补全 https://
        $url = 'https://' . $url;
        return self::eAttr($url);
    }

    /**
     * 清洗整数输入
     */
    public static function int($value, int $default = 0): int
    {
        $val = filter_var($value, FILTER_VALIDATE_INT);
        return $val === false ? $default : $val;
    }

    /**
     * 清洗字符串输入（去除首尾空白、控制字符）
     * @param string|null $value 输入值
     * @param int $maxLength 最大长度（0=不限制）
     */
    public static function cleanString(?string $value, int $maxLength = 0): string
    {
        if ($value === null) return '';
        // 去除 NULL 字节和控制字符（保留换行和制表符）
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        $value = trim($value);
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    /**
     * 验证并清洗 URL
     * @return array [isValid, cleanedUrl, domain]
     */
    public static function validateUrl(?string $url): array
    {
        $url = self::cleanString($url);
        if (empty($url)) {
            return [false, '', ''];
        }

        // 补全协议
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        // 解析域名
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return [false, '', ''];
        }

        $host = strtolower($parsed['host']);

        // 禁止内网地址（SSRF 防护）
        if (self::isInternalHost($host)) {
            return [false, '', ''];
        }

        // 验证域名格式
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            return [false, '', ''];
        }

        return [true, $url, $host];
    }

    /**
     * 检测是否为内网地址（防 SSRF）
     */
    public static function isInternalHost(string $host): bool
    {
        // localhost 系列
        $blocked = ['localhost', '0.0.0.0', 'metadata.google.internal'];
        if (in_array($host, $blocked)) {
            return true;
        }

        // IP 地址检测
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            // 禁止私有 IP、回环、保留地址
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // *.local / *.internal
        if (preg_match('/\.local$/i', $host) || preg_match('/\.internal$/i', $host)) {
            return true;
        }

        return false;
    }

    /**
     * 提取域名（不带协议和路径）
     */
    public static function extractDomain(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/^https?:\/\//i', '', $url);
        $url = explode('/', $url)[0];
        // 规范化：统一去除 www. 前缀，提高匹配成功率
        $url = preg_replace('/^www\./i', '', $url);
        return strtolower(trim($url));
    }

    /**
     * 验证枚举值
     */
    public static function enum(?string $value, array $allowed, string $default = ''): string
    {
        $value = self::cleanString($value);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * 验证 slug 格式（仅允许 a-z0-9-）
     */
    public static function validateSlug(?string $slug): string
    {
        $slug = self::cleanString($slug);
        $slug = strtolower($slug);
        if (preg_match('/^[a-z0-9-]+$/', $slug)) {
            return $slug;
        }
        return '';
    }

    /**
     * 清洗标签数组
     * @return string[] 清洗后的标签数组
     */
    public static function cleanTags($tags): array
    {
        if (is_string($tags)) {
            // 如果是逗号分隔的字符串
            $tags = explode(',', $tags);
        }
        if (!is_array($tags)) {
            return [];
        }
        $result = [];
        foreach ($tags as $tag) {
            $tag = self::cleanString((string)$tag);
            if ($tag !== '' && mb_strlen($tag) <= 20) {
                $result[] = $tag;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * 限制文本长度
     */
    public static function truncate(string $text, int $maxLen): string
    {
        $text = self::cleanString($text);
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen);
        }
        return $text;
    }

    /**
     * 获取客户端真实 IP
     */
    public static function getClientIP(): string
    {
        // 优先级：直连 IP > 代理头
        // 注意：X-Forwarded-For 可被伪造，仅在受信任代理时使用
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 如果配置了信任代理，可以读取 XFF
        if (defined('TRUST_PROXY') && TRUST_PROXY) {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($xff) {
                $ips = explode(',', $xff);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        return $remoteAddr;
    }

    /**
     * 频率限制检查（基于文件锁，保证原子性）
     * @param string $key 限制键（如 "submit:1.2.3.4"）
     * @param int $maxCount 最大次数
     * @param int $windowSeconds 时间窗口（秒）
     * @return bool true=允许，false=超限
     */
    public static function rateLimit(string $key, int $maxCount, int $windowSeconds): bool
    {
        $dir = sys_get_temp_dir() . '/nav_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = $dir . '/' . md5($key) . '.json';
        $now = time();

        // 使用 'c+' 模式打开文件（不存在则创建，存在则不截断），配合 flock 保证原子性
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // 文件锁不可用时降级为非原子操作（容错）
            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
            if (file_exists($file)) {
                $content = @file_get_contents($file);
                if ($content) {
                    $decoded = json_decode($content, true);
                    if ($decoded && $now <= $decoded['reset']) {
                        $data = $decoded;
                    }
                }
            }
            if ($data['count'] >= $maxCount) {
                Logger::log('security_ratelimit', "[频率限制] 键={$key}，已超限 {$maxCount} 次/{$windowSeconds} 秒，IP=" . self::getClientIP());
                return false;
            }
            $data['count']++;
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return true;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                return false; // 获取锁失败，拒绝请求
            }

            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
            fseek($fp, 0);
            $content = stream_get_contents($fp);
            if ($content !== '' && $content !== false) {
                $decoded = json_decode($content, true);
                if ($decoded && $now <= $decoded['reset']) {
                    $data = $decoded;
                }
            }

            if ($data['count'] >= $maxCount) {
                Logger::log('security_ratelimit', "[频率限制] 键={$key}，已超限 {$maxCount} 次/{$windowSeconds} 秒，IP=" . self::getClientIP());
                flock($fp, LOCK_UN);
                fclose($fp);
                return false;
            }

            $data['count']++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            if (is_resource($fp)) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
            return false;
        }
    }

    /**
     * 安全初始化 Session
     */
    public static function initSession(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        // 在 session_start 之前设置安全参数
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_name('NAVSESSID');
            session_start();
        }
    }

    /**
     * 登录后重新生成 Session ID（防 Session Fixation）
     */
    public static function regenerateSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }

    /**
     * 检查 Session 超时
     * @param int $timeoutSeconds 超时时间（默认 3600 秒，可从后台设置读取）
     * @return bool true=已超时，false=未超时
     */
    public static function isSessionExpired(int $timeoutSeconds = 3600): bool
    {
        if (empty($_SESSION['last_activity'])) {
            return true;
        }
        if (time() - $_SESSION['last_activity'] > $timeoutSeconds) {
            return true;
        }
        $_SESSION['last_activity'] = time();
        return false;
    }

    /**
     * 生成安全随机 Token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * JSON 安全输出
     */
    public static function jsonOutput($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 检查请求来源是否合法（防盗链）
     * 用于 API 接口防外站盗刷
     */
    public static function checkReferer(): bool
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (empty($referer) || empty($host)) {
            Logger::log('security_referer', "[Referer校验失败] Referer或Host为空");
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (!$refererHost) {
            Logger::log('security_referer', "[Referer校验失败] 无法解析Referer域名：{$referer}");
            return false;
        }

        // 允许同源请求
        $valid = strcasecmp($refererHost, $host) === 0;
        if (!$valid) {
            Logger::log('security_referer', "[Referer校验失败] 来源域名不匹配：RefererHost={$refererHost}，本域={$host}");
        }
        return $valid;
    }

    /**
     * 清洗 HTML（白名单过滤，用于管理员自定义 HTML 字段如 site_footer）
     * 允许标签：a img div span p br strong em b i ul ol li noscript
     * 禁止：script、iframe、on* 事件属性、javascript: 协议、data: 协议
     * @param string $html 原始 HTML
     * @param int $maxLength 最大长度（0=不限制）
     * @return string 清洗后的 HTML
     */
    public static function cleanHtml(?string $html, int $maxLength = 0): string
    {
        if ($html === null) return '';
        // 去除 NULL 字节和控制字符
        $html = str_replace("\0", '', $html);
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);

        // 白名单标签（允许 script 用于统计代码，但保留事件属性和协议过滤）
        $allowed = '<a><img><div><span><p><br><strong><em><b><i><ul><ol><li><noscript><script>';
        $html = strip_tags($html, $allowed);

        // 移除所有 on* 事件属性（onclick onload onerror 等）
        // 分步处理：双引号包裹 → 单引号包裹 → 无引号，避免引号嵌套截断
        $html = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*\'[^\']*\'/i', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*[^\s>]+/i', '', $html);

        // 移除 javascript: 和 vbscript: 协议（防 href/src 注入）
        $html = preg_replace('/(href|src)\s*=\s*["\']\s*(javascript|vbscript)\s*:/i', '$1="#"', $html);
        $html = preg_replace('/(href|src)\s*=\s*(javascript|vbscript)\s*:/i', '$1="#"', $html);

        // 移除 data: 协议（防 <img src="data:..."> 绕过）
        $html = preg_replace('/src\s*=\s*["\']\s*data\s*:/i', 'src="#"', $html);

        if ($maxLength > 0 && mb_strlen($html) > $maxLength) {
            $html = mb_substr($html, 0, $maxLength);
        }

        return trim($html);
    }

    /**
     * 验证密码强度（布尔版，供表单验证用）
     */
    public static function validatePasswordStrength(string $password): bool
    {
        if (mb_strlen($password) < 8) return false;
        if (!preg_match('/[a-zA-Z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        return true;
    }

    /**
     * 验证密码强度（详细版，返回错误列表）
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        if (mb_strlen($password) < 8) {
            $errors[] = '密码至少 8 位';
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = '密码需包含字母';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '密码需包含数字';
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * 验证用户名格式
     */
    public static function validateUsername(string $username): bool
    {
        $username = self::cleanString($username);
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
    }
}
