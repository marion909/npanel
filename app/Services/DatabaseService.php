<?php

namespace App\Services;

use App\Models\Database;
use App\Models\Domain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    public function __construct(
        protected MySQLRootConnection $mysqlRoot
    ) {}

    /**
     * Create a new database for a domain
     */
    public function createDatabase(Domain $domain, array $data): Database
    {
        $displayName = $data['display_name'];
        
        // Generate database name and username
        $databaseName = Database::generateDatabaseName($domain, $displayName);
        $username = Database::generateUsername($domain, $displayName);
        $password = Database::generatePassword(20);

        // Check if database already exists
        if ($this->mysqlRoot->databaseExists($databaseName)) {
            throw new \Exception("Database '{$databaseName}' already exists");
        }

        // Check if user already exists
        if ($this->mysqlRoot->userExists($username)) {
            throw new \Exception("MySQL user '{$username}' already exists");
        }

        try {
            // Create MySQL database
            $this->mysqlRoot->createDatabase($databaseName);
            Log::info('MySQL database created', ['database' => $databaseName]);

            // Create MySQL user
            $this->mysqlRoot->createUser($username, $password);
            Log::info('MySQL user created', ['user' => $username]);

            // Grant privileges
            $this->mysqlRoot->grantPrivileges($username, $databaseName);
            Log::info('Privileges granted', ['user' => $username, 'database' => $databaseName]);

            // Create database record
            $database = Database::create([
                'domain_id' => $domain->id,
                'database_name' => $databaseName,
                'display_name' => $displayName,
                'mysql_user' => $username,
                'mysql_password' => $password, // Will be encrypted via mutator
                'status' => 'active',
                'size_mb' => 0,
            ]);

            Log::info('Database record created', ['id' => $database->id, 'database' => $databaseName]);

            return $database;
        } catch (\Exception $e) {
            // Rollback: try to clean up what was created
            try {
                if ($this->mysqlRoot->databaseExists($databaseName)) {
                    $this->mysqlRoot->dropDatabase($databaseName);
                }
                if ($this->mysqlRoot->userExists($username)) {
                    $this->mysqlRoot->dropUser($username);
                }
            } catch (\Exception $cleanupException) {
                Log::error('Failed to cleanup after database creation failure', [
                    'error' => $cleanupException->getMessage()
                ]);
            }

            Log::error('Database creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a database
     */
    public function deleteDatabase(Database $database): bool
    {
        try {
            // Revoke privileges
            $this->mysqlRoot->revokePrivileges($database->mysql_user, $database->database_name);

            // Drop MySQL user
            $this->mysqlRoot->dropUser($database->mysql_user);
            Log::info('MySQL user dropped', ['user' => $database->mysql_user]);

            // Drop MySQL database
            $this->mysqlRoot->dropDatabase($database->database_name);
            Log::info('MySQL database dropped', ['database' => $database->database_name]);

            // Delete database record
            $database->delete();
            Log::info('Database record deleted', ['id' => $database->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Database deletion failed', [
                'id' => $database->id,
                'database' => $database->database_name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get all databases for a domain
     */
    public function getDomainDatabases(Domain $domain): Collection
    {
        return $domain->databases()->get();
    }

    /**
     * Update database size
     */
    public function updateDatabaseSize(Database $database): bool
    {
        try {
            $size = $this->mysqlRoot->getDatabaseSize($database->database_name);
            $database->update(['size_mb' => $size]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update database size', [
                'id' => $database->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update all database sizes for a domain
     */
    public function updateDomainDatabaseSizes(Domain $domain): void
    {
        foreach ($domain->databases as $database) {
            $this->updateDatabaseSize($database);
        }
    }

    /**
     * Suspend a database (revoke privileges but keep data)
     */
    public function suspendDatabase(Database $database): bool
    {
        try {
            $this->mysqlRoot->revokePrivileges($database->mysql_user, $database->database_name);
            $database->update(['status' => 'suspended']);
            
            Log::info('Database suspended', ['id' => $database->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Database suspension failed', [
                'id' => $database->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Resume a suspended database
     */
    public function resumeDatabase(Database $database): bool
    {
        try {
            $this->mysqlRoot->grantPrivileges($database->mysql_user, $database->database_name);
            $database->update(['status' => 'active']);
            
            Log::info('Database resumed', ['id' => $database->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Database resume failed', [
                'id' => $database->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
