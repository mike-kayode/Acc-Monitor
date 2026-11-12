<?php
/**
 * Database Connection Manager
 * PDO wrapper with prepared statements support
 */

namespace NAPM\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['connection'],
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
                self::$instance->exec("SET time_zone = '+00:00'");
                self::$instance->exec("SET NAMES 'utf8mb4'");
            } catch (PDOException $e) {
                throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
            }
        }
        return self::$instance;
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->getConnection()->prepare($sql);
    }

    public function query(string $sql): \PDOStatement
    {
        $statement = $this->getConnection()->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Database query failed.');
        }
        return $statement;
    }

    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->getConnection()->lastInsertId($name);
    }

    public function quote(string $string): string
    {
        return $this->getConnection()->quote($string);
    }

    public function close(): void
    {
        self::$instance = null;
    }
}