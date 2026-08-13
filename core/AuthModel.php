<?php
/**
 * 管理员认证业务逻辑层
 */

class AuthModel
{
    /**
     * 验证登录凭据
     * @return array|false 成功返回 admin 数据，失败返回 false
     */
    public function verify(string $username, string $password)
    {
        $tbl = Database::table('admins');
        $admin = Database::queryOne(
            "SELECT * FROM {$tbl} WHERE username = ? AND status = 1",
            [$username]
        );

        if (!$admin) {
            return false;
        }

        if (password_verify($password, $admin['password_hash'])) {
            return $admin;
        }
        return false;
    }

    /**
     * 创建管理员
     */
    public function createAdmin(string $username, string $password, string $email = ''): int
    {
        $tbl = Database::table('admins');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        return Database::insert(
            "INSERT INTO {$tbl} (username, password_hash, email, status) VALUES (?, ?, ?, 1)",
            [$username, $hash, $email]
        );
    }

    /**
     * 按 ID 更新密码
     */
    public function updatePassword(int $adminId, string $newPassword): int
    {
        $tbl = Database::table('admins');
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        return Database::execute(
            "UPDATE {$tbl} SET password_hash = ? WHERE id = ?",
            [$hash, $adminId]
        );
    }

    /**
     * 按 ID 获取管理员信息
     */
    public function getById(int $id): ?array
    {
        $tbl = Database::table('admins');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE id = ?", [$id]);
    }

    /**
     * 按用户名获取管理员信息
     */
    public function getByUsername(string $username): ?array
    {
        $tbl = Database::table('admins');
        return Database::queryOne("SELECT * FROM {$tbl} WHERE username = ?", [$username]);
    }

    /**
     * 记录登录日志
     */
    public function logLogin(int $adminId, string $ip, bool $success = true): void
    {
        $admin = $this->getById($adminId);
        $username = $admin ? $admin['username'] : 'unknown';
        $status = $success ? '成功' : '失败';
        Logger::log('admin_auth', "[登录{$status}] 用户={$username}, IP={$ip}");
    }
}
