<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private static ?Database $personalInstance = null;
    private PDO $connection;

    /**
     * @param array{host:string,port:string,database:string,username:string,password:string} $config
     */
    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);
            $this->connection->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(self::capacitacionesConfig());
        }

        return self::$instance;
    }

    /**
     * Conexion para consultar meridian_personal. Si host/usuario/clave coinciden
     * con capacitaciones (caso habitual en XAMPP y cPanel), se reutiliza PDO.
     */
    public static function personal(): self
    {
        if (self::mismasCredencialesPersonal()) {
            return self::getInstance();
        }

        if (self::$personalInstance === null) {
            self::$personalInstance = new self(self::personalConfig());
        }

        return self::$personalInstance;
    }

    public static function personalName(): string
    {
        $nombre = (string)Env::get(
            'DB_PERSONAL_NAME',
            Env::get('DB_PERSONAL_DATABASE', 'meridian_personal')
        );

        return self::identificadorSeguro($nombre);
    }

    public static function personalTable(string $tabla): string
    {
        return '`' . self::personalName() . '`.`' . self::identificadorSeguro($tabla) . '`';
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $stmt = $this->query($sql, [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /** @return array{host:string,port:string,database:string,username:string,password:string} */
    private static function capacitacionesConfig(): array
    {
        return [
            'host' => (string)Env::get('DB_HOST', '127.0.0.1'),
            'port' => (string)Env::get('DB_PORT', '3306'),
            'database' => (string)Env::get('DB_DATABASE', 'meridian_capacitaciones'),
            'username' => (string)Env::get('DB_USERNAME', 'root'),
            'password' => (string)Env::get('DB_PASSWORD', ''),
        ];
    }

    /** @return array{host:string,port:string,database:string,username:string,password:string} */
    private static function personalConfig(): array
    {
        $base = self::capacitacionesConfig();

        return [
            'host' => (string)Env::get('DB_PERSONAL_HOST', $base['host']),
            'port' => (string)Env::get('DB_PERSONAL_PORT', $base['port']),
            'database' => self::personalName(),
            'username' => (string)Env::get(
                'DB_PERSONAL_USER',
                Env::get('DB_PERSONAL_USERNAME', $base['username'])
            ),
            'password' => (string)Env::get('DB_PERSONAL_PASSWORD', $base['password']),
        ];
    }

    private static function mismasCredencialesPersonal(): bool
    {
        $cap = self::capacitacionesConfig();
        $per = self::personalConfig();

        return $cap['host'] === $per['host']
            && $cap['port'] === $per['port']
            && $cap['username'] === $per['username']
            && $cap['password'] === $per['password'];
    }

    private static function identificadorSeguro(string $nombre): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $nombre)) {
            throw new InvalidArgumentException('Identificador de base o tabla invalido.');
        }

        return $nombre;
    }
}
