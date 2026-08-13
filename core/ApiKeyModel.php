<?php
/**
 * API Key 模型
 * 管理 API 密钥、鉴权、调用频率限制
 */

class ApiKeyModel
{
    /**
     * 根据 API Key 获取密钥信息
     */
    public function getByKey(string $apiKey): ?array
    {
        $tbl = Database::table('api_keys');
        $sql = "SELECT * FROM {$tbl} WHERE api_key = ? AND status = 1 LIMIT 1";
        $result = Database::queryOne($sql, [$apiKey]);
        return $result ?: null;
    }

    /**
     * 验证 API Key 是否有效
     */
    public function validate(string $apiKey): bool
    {
        $key = $this->getByKey($apiKey);
        if (!$key) return false;

        // 检查是否过期
        if (!empty($key['expires_at']) && strtotime($key['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * 验证签名（可选，用于高安全级别接口）
     */
    public function verifySignature(string $apiKey, string $signature, string $timestamp, string $body = ''): bool
    {
        $key = $this->getByKey($apiKey);
        if (!$key || empty($key['api_secret'])) return false;

        // 时间戳验证（5分钟内有效）
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        // 计算预期签名
        $expected = hash_hmac('sha256', $apiKey . $timestamp . $body, $key['api_secret']);
        return hash_equals($expected, $signature);
    }

    /**
     * 检查调用频率限制
     * 返回 true 表示允许调用，false 表示超出限制
     */
    public function checkRateLimit(string $apiKey): bool
    {
        $key = $this->getByKey($apiKey);
        if (!$key) return false;

        $tbl = Database::table('api_rate_limit');
        $now = time();

        // 检查三个时间维度的限制
        $periods = [
            'minute' => [
                'limit' => (int)$key['rate_limit_per_minute'],
                'key' => date('YmdHi', $now),
            ],
            'hour' => [
                'limit' => (int)$key['rate_limit_per_hour'],
                'key' => date('YmdH', $now),
            ],
            'day' => [
                'limit' => (int)$key['rate_limit_per_day'],
                'key' => date('Ymd', $now),
            ],
        ];

        foreach ($periods as $period => $config) {
            $current = $this->getCurrentCount($apiKey, $period, $config['key']);
            if ($current >= $config['limit']) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取当前周期的调用次数
     */
    private function getCurrentCount(string $apiKey, string $period, string $periodKey): int
    {
        $tbl = Database::table('api_rate_limit');
        $sql = "SELECT call_count FROM {$tbl} WHERE api_key = ? AND period = ? AND period_key = ? LIMIT 1";
        $result = Database::queryOne($sql, [$apiKey, $period, $periodKey]);
        return (int)($result['call_count'] ?? 0);
    }

    /**
     * 记录一次 API 调用（递增计数）
     */
    public function recordCall(string $apiKey): void
    {
        $tbl = Database::table('api_rate_limit');
        $now = time();

        $periods = [
            'minute' => date('YmdHi', $now),
            'hour' => date('YmdH', $now),
            'day' => date('Ymd', $now),
        ];

        foreach ($periods as $period => $periodKey) {
            // 使用 INSERT ... ON DUPLICATE KEY UPDATE 来原子递增
            $sql = "INSERT INTO {$tbl} (api_key, period, period_key, call_count, created_at, updated_at)
                    VALUES (?, ?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE call_count = call_count + 1, updated_at = NOW()";
            Database::execute($sql, [$apiKey, $period, $periodKey]);
        }

        // 更新密钥的总调用次数和最后调用时间
        $keysTbl = Database::table('api_keys');
        Database::execute(
            "UPDATE {$keysTbl} SET call_count = call_count + 1, last_call_at = NOW() WHERE api_key = ?",
            [$apiKey]
        );
    }

    /**
     * 获取限流剩余次数
     */
    public function getRateLimitRemaining(string $apiKey): array
    {
        $key = $this->getByKey($apiKey);
        if (!$key) return [];

        $now = time();
        return [
            'minute' => [
                'limit' => (int)$key['rate_limit_per_minute'],
                'remaining' => max(0, (int)$key['rate_limit_per_minute'] - $this->getCurrentCount($apiKey, 'minute', date('YmdHi', $now))),
            ],
            'hour' => [
                'limit' => (int)$key['rate_limit_per_hour'],
                'remaining' => max(0, (int)$key['rate_limit_per_hour'] - $this->getCurrentCount($apiKey, 'hour', date('YmdH', $now))),
            ],
            'day' => [
                'limit' => (int)$key['rate_limit_per_day'],
                'remaining' => max(0, (int)$key['rate_limit_per_day'] - $this->getCurrentCount($apiKey, 'day', date('Ymd', $now))),
            ],
        ];
    }

    /**
     * 生成新的 API Key
     */
    public function generateKey(): string
    {
        return 'ak_' . bin2hex(random_bytes(24));
    }

    /**
     * 生成 API Secret
     */
    public function generateSecret(): string
    {
        return 'sk_' . bin2hex(random_bytes(32));
    }

    /**
     * 创建新的 API Key
     */
    public function create(array $data): int
    {
        $tbl = Database::table('api_keys');
        $apiKey = $data['api_key'] ?? $this->generateKey();
        $apiSecret = $data['api_secret'] ?? $this->generateSecret();

        $sql = "INSERT INTO {$tbl}
                (api_key, api_secret, name, status, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        return Database::insert($sql, [
            $apiKey,
            $apiSecret,
            $data['name'] ?? '',
            $data['status'] ?? 1,
            $data['rate_limit_per_minute'] ?? 60,
            $data['rate_limit_per_hour'] ?? 1000,
            $data['rate_limit_per_day'] ?? 10000,
            $data['created_by'] ?? 0,
        ]);
    }

    /**
     * 获取所有 API Key 列表
     */
    public function getAll(int $page = 1, int $pageSize = 20): array
    {
        $tbl = Database::table('api_keys');
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT id, api_key, name, status, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day,
                       call_count, last_call_at, expires_at, created_at, updated_at
                FROM {$tbl}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        return Database::query($sql, [$pageSize, $offset]);
    }

    /**
     * 获取总数
     */
    public function countAll(): int
    {
        $tbl = Database::table('api_keys');
        $result = Database::queryOne("SELECT COUNT(*) as total FROM {$tbl}");
        return (int)($result['total'] ?? 0);
    }

    /**
     * 更新 API Key
     */
    public function update(int $id, array $data): bool
    {
        $tbl = Database::table('api_keys');
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'status', 'rate_limit_per_minute', 'rate_limit_per_hour', 'rate_limit_per_day', 'expires_at'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $sql = "UPDATE {$tbl} SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::execute($sql, $params) > 0;
    }

    /**
     * 删除 API Key
     */
    public function delete(int $id): bool
    {
        $tbl = Database::table('api_keys');
        return Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]) > 0;
    }

    /**
     * 清理过期的限流记录
     */
    public function cleanOldRateLimits(): int
    {
        $tbl = Database::table('api_rate_limit');
        // 删除 7 天前的记录
        $sql = "DELETE FROM {$tbl} WHERE period_key < ?";
        $cutoff = date('Ymd', strtotime('-7 days'));
        return Database::execute($sql, [$cutoff]);
    }
}
