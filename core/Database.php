<?php
/**
 * 数据库抽象层
 * PDO 单例 + 预处理查询封装
 */

class Database
{
    private static ?PDO $instance = null;

    /** 获取 PDO 单例 */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /** 连接数据库 */
    private static function connect(): void
    {
        if (!defined('DB_HOST')) {
            throw new RuntimeException('数据库配置未加载，请先运行安装程序');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT ?: 3306,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('数据库连接失败：' . $e->getMessage());
        }
    }

    /**
     * 执行查询并返回多行
     * @param string $sql SQL 语句（含占位符）
     * @param array $params 绑定参数
     * @return array
     */
    public static function query(string $sql, array $params = []): array
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            Logger::log('database_error', "[SQL查询失败] {$e->getMessage()} | SQL={$sql} | 参数=" . json_encode($params));
            throw $e;
        }
    }

    /**
     * 执行查询并返回单行
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            Logger::log('database_error', "[SQL查询失败] {$e->getMessage()} | SQL={$sql} | 参数=" . json_encode($params));
            throw $e;
        }
    }

    /**
     * 执行写操作（INSERT/UPDATE/DELETE），返回影响行数
     */
    public static function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Logger::log('database_error', "[SQL执行失败] {$e->getMessage()} | SQL={$sql} | 参数=" . json_encode($params));
            throw $e;
        }
    }

    /**
     * 插入数据并返回 lastInsertId
     */
    public static function insert(string $sql, array $params = []): int
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return (int)self::getInstance()->lastInsertId();
        } catch (PDOException $e) {
            Logger::log('database_error', "[SQL插入失败] {$e->getMessage()} | SQL={$sql} | 参数=" . json_encode($params));
            throw $e;
        }
    }

    /**
     * 获取单个值
     */
    public static function scalar(string $sql, array $params = [])
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : null;
        } catch (PDOException $e) {
            Logger::log('database_error', "[SQL标量查询失败] {$e->getMessage()} | SQL={$sql} | 参数=" . json_encode($params));
            throw $e;
        }
    }

    /**
     * 获取表名（加前缀）
     */
    public static function table(string $name): string
    {
        return DB_PREFIX . $name;
    }

    /** 开启事务 */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /** 提交事务 */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /** 回滚事务 */
    public static function rollback(): void
    {
        if (self::getInstance()->inTransaction()) {
            self::getInstance()->rollBack();
        }
    }
}
