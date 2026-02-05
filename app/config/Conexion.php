<?php
declare(strict_types=1);

class Conexion
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Preferencia: $_ENV (dotenv) y fallback a getenv()
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

        if ($db === '') {
            throw new RuntimeException("DB_NAME vacío. Revisa tu .env");
        }

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }
}
