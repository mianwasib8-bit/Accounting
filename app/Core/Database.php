<?php
/**
 * PDO Singleton Database Connection
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            http_response_code(500);
            $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Import database/schema.sql and check config/config.php.',
                    'error'   => APP_ENV === 'local' ? $e->getMessage() : null,
                ]);
            } else {
                echo '<!DOCTYPE html><html><body style="font-family:system-ui;padding:2rem;background:#0f172a;color:#e2e8f0">';
                echo '<h1 style="color:#818cf8">Accounting System — Database Error</h1>';
                echo '<p>Import <code>database/schema.sql</code> in phpMyAdmin, then refresh.</p>';
                if (APP_ENV === 'local') {
                    echo '<pre style="background:#1e293b;padding:1rem;border-radius:8px;overflow:auto">' . htmlspecialchars($e->getMessage()) . '</pre>';
                }
                echo '</body></html>';
            }
            exit;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
