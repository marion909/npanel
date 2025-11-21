<?php

namespace App\Services;

use App\Models\Database;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MySQLRootConnection
{
    protected \PDO $connection;

    /**
     * Initialize MySQL root connection
     */
    public function __construct()
    {
        $host = config('database.connections.mysql_root.host', '127.0.0.1');
        $port = config('database.connections.mysql_root.port', 3306);
        $username = config('database.connections.mysql_root.username', 'root');
        $password = config('database.connections.mysql_root.password', '');

        $dsn = "mysql:host={$host};port={$port}";

        try {
            // Try with password first
            $this->connection = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            // If password auth fails, try without password (unix socket auth)
            try {
                $this->connection = new \PDO($dsn, $username, '', [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
                Log::warning('MySQL root connected without password (unix socket auth)');
            } catch (\PDOException $e2) {
                Log::error('Failed to connect to MySQL as root', [
                    'error' => $e->getMessage(),
                    'error2' => $e2->getMessage(),
                    'host' => $host,
                    'username' => $username,
                ]);
                throw new \Exception('Failed to connect to MySQL. Please check MYSQL_ROOT credentials in .env or run: sudo mysql -e "ALTER USER \'root\'@\'localhost\' IDENTIFIED WITH mysql_native_password BY \'your_password\';"');
            }
        }
    }

    /**
     * Create database
     */
    public function createDatabase(string $databaseName): bool
    {
        try {
            $stmt = $this->connection->prepare("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return $stmt->execute();
        } catch (\PDOException $e) {
            Log::error('Failed to create database', ['database' => $databaseName, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to create database: ' . $e->getMessage());
        }
    }

    /**
     * Drop database
     */
    public function dropDatabase(string $databaseName): bool
    {
        try {
            $stmt = $this->connection->prepare("DROP DATABASE IF EXISTS `{$databaseName}`");
            return $stmt->execute();
        } catch (\PDOException $e) {
            Log::error('Failed to drop database', ['database' => $databaseName, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to drop database: ' . $e->getMessage());
        }
    }

    /**
     * Create MySQL user
     */
    public function createUser(string $username, string $password): bool
    {
        try {
            // Drop user if exists (MySQL 5.7+ syntax)
            $stmt = $this->connection->prepare("DROP USER IF EXISTS '{$username}'@'127.0.0.1'");
            $stmt->execute();

            // Create user
            $stmt = $this->connection->prepare("CREATE USER '{$username}'@'127.0.0.1' IDENTIFIED BY :password");
            $stmt->bindParam(':password', $password);
            return $stmt->execute();
        } catch (\PDOException $e) {
            Log::error('Failed to create user', ['user' => $username, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Drop MySQL user
     */
    public function dropUser(string $username): bool
    {
        try {
            $stmt = $this->connection->prepare("DROP USER IF EXISTS '{$username}'@'127.0.0.1'");
            return $stmt->execute();
        } catch (\PDOException $e) {
            Log::error('Failed to drop user', ['user' => $username, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to drop user: ' . $e->getMessage());
        }
    }

    /**
     * Grant privileges to user for database
     */
    public function grantPrivileges(string $username, string $databaseName): bool
    {
        try {
            $stmt = $this->connection->prepare("GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO '{$username}'@'127.0.0.1'");
            $stmt->execute();

            // Flush privileges
            $this->connection->exec("FLUSH PRIVILEGES");
            return true;
        } catch (\PDOException $e) {
            Log::error('Failed to grant privileges', [
                'user' => $username,
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to grant privileges: ' . $e->getMessage());
        }
    }

    /**
     * Revoke privileges from user for database
     */
    public function revokePrivileges(string $username, string $databaseName): bool
    {
        try {
            $stmt = $this->connection->prepare("REVOKE ALL PRIVILEGES ON `{$databaseName}`.* FROM '{$username}'@'127.0.0.1'");
            $stmt->execute();

            $this->connection->exec("FLUSH PRIVILEGES");
            return true;
        } catch (\PDOException $e) {
            Log::error('Failed to revoke privileges', [
                'user' => $username,
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get database size in MB
     */
    public function getDatabaseSize(string $databaseName): float
    {
        try {
            $stmt = $this->connection->prepare("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = :database
            ");
            $stmt->bindParam(':database', $databaseName);
            $stmt->execute();

            $result = $stmt->fetch();
            return (float) ($result['size_mb'] ?? 0);
        } catch (\PDOException $e) {
            Log::error('Failed to get database size', ['database' => $databaseName, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Check if database exists
     */
    public function databaseExists(string $databaseName): bool
    {
        try {
            $stmt = $this->connection->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :database");
            $stmt->bindParam(':database', $databaseName);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            Log::error('Failed to check database existence', ['database' => $databaseName, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if user exists
     */
    public function userExists(string $username): bool
    {
        try {
            $stmt = $this->connection->prepare("SELECT User FROM mysql.user WHERE User = :username AND Host = '127.0.0.1'");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            Log::error('Failed to check user existence', ['user' => $username, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
