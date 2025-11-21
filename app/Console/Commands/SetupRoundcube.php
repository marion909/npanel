<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class SetupRoundcube extends Command
{
    protected $signature = 'npanel:setup-roundcube';
    protected $description = 'Setup Roundcube database and configuration';

    public function handle()
    {
        $this->info("Setting up Roundcube...");

        // Try MySQL root credentials first, fallback to regular DB credentials
        $mysqlHost = env('MYSQL_ROOT_HOST') ?: env('DB_HOST', '127.0.0.1');
        $mysqlUser = env('MYSQL_ROOT_USERNAME') ?: env('DB_USERNAME', 'root');
        $mysqlPass = env('MYSQL_ROOT_PASSWORD') ?: env('DB_PASSWORD', '');

        if (empty($mysqlPass)) {
            $this->error("No MySQL password found. Set either MYSQL_ROOT_PASSWORD or DB_PASSWORD in .env");
            return 1;
        }

        $this->info("Using MySQL connection: {$mysqlUser}@{$mysqlHost}");

        // Create roundcube database
        $this->info("Creating Roundcube database...");
        $result = Process::run("mysql -h {$mysqlHost} -u {$mysqlUser} -p{$mysqlPass} -e \"CREATE DATABASE IF NOT EXISTS roundcube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"");
        
        if (!$result->successful()) {
            $this->error("Failed to create database: " . $result->errorOutput());
            return 1;
        }
        $this->info("✓ Database created");

        // Import schema
        $this->info("Importing Roundcube schema...");
        $schemaFile = '/var/www/roundcube/SQL/mysql.initial.sql';
        
        if (!File::exists($schemaFile)) {
            $this->error("Schema file not found: {$schemaFile}");
            return 1;
        }

        $result = Process::run("mysql -h {$mysqlHost} -u {$mysqlUser} -p{$mysqlPass} roundcube < {$schemaFile}");
        
        if (!$result->successful()) {
            $this->error("Failed to import schema: " . $result->errorOutput());
            return 1;
        }
        $this->info("✓ Schema imported");

        // Update Roundcube config
        $this->info("Updating Roundcube configuration...");
        $configPath = '/var/www/roundcube/config/config.inc.php';
        
        if (!File::exists($configPath)) {
            $this->error("Roundcube config not found: {$configPath}");
            return 1;
        }

        $config = File::get($configPath);
        
        // Replace database connection string
        $dbDsn = "mysql://{$mysqlUser}:{$mysqlPass}@{$mysqlHost}/roundcube";
        $config = preg_replace(
            "/\\\$config\['db_dsnw'\]\s*=\s*'[^']*';/",
            "\$config['db_dsnw'] = '{$dbDsn}';",
            $config
        );

        File::put($configPath, $config);
        $this->info("✓ Configuration updated");

        // Test database connection
        $this->info("Testing database connection...");
        $result = Process::run("mysql -h {$mysqlHost} -u {$mysqlUser} -p{$mysqlPass} roundcube -e \"SHOW TABLES;\"");
        
        if (!$result->successful()) {
            $this->error("Database connection test failed");
            return 1;
        }

        $tables = explode("\n", trim($result->output()));
        $this->info("✓ Database has " . (count($tables) - 1) . " tables");

        // Set proper permissions
        Process::run("chown www-data:www-data {$configPath}");
        Process::run("chmod 640 {$configPath}");
        $this->info("✓ Permissions set");

        $this->info("\n✅ Roundcube setup complete!");
        $this->info("You can now access Roundcube at: https://webmail.npanel.at");
        
        return 0;
    }
}
