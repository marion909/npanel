<?php

namespace App\Services;

use App\Models\Database;
use App\Models\Subdomain;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordPressService
{
    protected DatabaseService $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Install WordPress on a subdomain
     */
    public function installWordPress(Subdomain $subdomain): array
    {
        $domain = $subdomain->parentDomain;
        $fullDomain = $subdomain->subdomain_name === '@' 
            ? $domain->domain_name 
            : $subdomain->subdomain_name . '.' . $domain->domain_name;

        Log::info('Starting WordPress installation', [
            'subdomain_id' => $subdomain->id,
            'full_domain' => $fullDomain,
        ]);

        // Generate credentials
        $dbName = 'wp_' . Str::slug($subdomain->subdomain_name . '_' . Str::random(6), '_');
        $dbUser = substr($dbName, 0, 16); // MySQL username limit
        $dbPassword = Str::random(24);
        $wpAdminUser = 'admin';
        $wpAdminPassword = Str::random(16);
        $wpAdminEmail = 'admin@' . $domain->domain_name;

        try {
            // 1. Create database
            Log::info('Creating database for WordPress', ['db_name' => $dbName]);
            $database = $this->databaseService->createDatabase($domain, [
                'database_name' => $dbName,
                'username' => $dbUser,
                'password' => $dbPassword,
            ]);

            // 2. Download WordPress
            $wpPath = $subdomain->document_root;
            Log::info('Downloading WordPress', ['path' => $wpPath]);
            
            if (!File::exists($wpPath)) {
                File::makeDirectory($wpPath, 0755, true);
            }

            $wpZipPath = '/tmp/wordpress-' . time() . '.tar.gz';
            $wpDownloadUrl = 'https://wordpress.org/latest.tar.gz';

            // Download WordPress
            $response = Http::timeout(120)->get($wpDownloadUrl);
            if (!$response->successful()) {
                throw new \Exception('Failed to download WordPress');
            }
            File::put($wpZipPath, $response->body());

            // Extract WordPress
            exec("tar -xzf {$wpZipPath} -C /tmp/", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \Exception('Failed to extract WordPress archive');
            }

            // Move WordPress files to document root
            exec("cp -r /tmp/wordpress/* {$wpPath}/", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \Exception('Failed to move WordPress files');
            }

            // Cleanup
            File::delete($wpZipPath);
            File::deleteDirectory('/tmp/wordpress');

            // 3. Create wp-config.php
            Log::info('Creating wp-config.php');
            $this->createWpConfig($wpPath, $dbName, $dbUser, $dbPassword);

            // 4. Install WordPress via CLI
            Log::info('Installing WordPress via WP-CLI');
            $this->installWordPressCli($wpPath, $fullDomain, $wpAdminUser, $wpAdminPassword, $wpAdminEmail);

            // 5. Set proper permissions
            $this->setWordPressPermissions($wpPath);

            Log::info('WordPress installation completed', [
                'subdomain_id' => $subdomain->id,
                'database_id' => $database->id,
            ]);

            return [
                'success' => true,
                'credentials' => [
                    'site_url' => 'https://' . $fullDomain,
                    'admin_url' => 'https://' . $fullDomain . '/wp-admin',
                    'admin_user' => $wpAdminUser,
                    'admin_password' => $wpAdminPassword,
                    'admin_email' => $wpAdminEmail,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_password' => $dbPassword,
                ],
                'database' => $database,
            ];

        } catch (\Exception $e) {
            Log::error('WordPress installation failed', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cleanup on failure
            if (isset($database)) {
                try {
                    $this->databaseService->deleteDatabase($database);
                } catch (\Exception $cleanupError) {
                    Log::error('Failed to cleanup database after WordPress installation failure', [
                        'database_id' => $database->id,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Create wp-config.php file
     */
    protected function createWpConfig(string $wpPath, string $dbName, string $dbUser, string $dbPassword): void
    {
        $wpConfigPath = $wpPath . '/wp-config.php';
        $wpConfigSamplePath = $wpPath . '/wp-config-sample.php';

        if (!File::exists($wpConfigSamplePath)) {
            throw new \Exception('wp-config-sample.php not found');
        }

        $configContent = File::get($wpConfigSamplePath);

        // Replace database credentials
        $configContent = str_replace('database_name_here', $dbName, $configContent);
        $configContent = str_replace('username_here', $dbUser, $configContent);
        $configContent = str_replace('password_here', $dbPassword, $configContent);
        $configContent = str_replace('localhost', '127.0.0.1', $configContent);

        // Generate security keys
        $keys = $this->generateWpSecurityKeys();
        $configContent = preg_replace(
            '/define\s*\(\s*[\'"]AUTH_KEY[\'"]\s*,\s*[\'"]put your unique phrase here[\'"]\s*\);.*?define\s*\(\s*[\'"]NONCE_SALT[\'"]\s*,\s*[\'"]put your unique phrase here[\'"]\s*\);/s',
            $keys,
            $configContent
        );

        // Add WP_DEBUG configuration
        $debugConfig = "\n// Debugging\ndefine('WP_DEBUG', false);\ndefine('WP_DEBUG_LOG', false);\ndefine('WP_DEBUG_DISPLAY', false);\n";
        $configContent = str_replace(
            "/* That's all, stop editing! Happy publishing. */",
            $debugConfig . "\n/* That's all, stop editing! Happy publishing. */",
            $configContent
        );

        File::put($wpConfigPath, $configContent);
        chmod($wpConfigPath, 0644);
    }

    /**
     * Generate WordPress security keys
     */
    protected function generateWpSecurityKeys(): string
    {
        $keys = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        $keyStrings = [];
        foreach ($keys as $key) {
            $value = bin2hex(random_bytes(32));
            $keyStrings[] = "define('{$key}', '{$value}');";
        }

        return implode("\n", $keyStrings);
    }

    /**
     * Install WordPress using WP-CLI or direct database setup
     */
    protected function installWordPressCli(
        string $wpPath,
        string $siteUrl,
        string $adminUser,
        string $adminPassword,
        string $adminEmail
    ): void {
        // Check if WP-CLI is available
        exec('which wp', $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            // Use WP-CLI
            Log::info('Using WP-CLI for installation');
            $wpCliPath = trim($output[0]);
            
            $command = sprintf(
                'cd %s && %s core install --url="%s" --title="WordPress Site" --admin_user="%s" --admin_password="%s" --admin_email="%s" --skip-email 2>&1',
                escapeshellarg($wpPath),
                escapeshellarg($wpCliPath),
                escapeshellarg($siteUrl),
                escapeshellarg($adminUser),
                escapeshellarg($adminPassword),
                escapeshellarg($adminEmail)
            );

            exec($command, $installOutput, $installReturn);

            if ($installReturn !== 0) {
                throw new \Exception('WP-CLI installation failed: ' . implode("\n", $installOutput));
            }

            Log::info('WordPress installed via WP-CLI');
        } else {
            // Fallback: Manual installation via HTTP request
            Log::info('WP-CLI not available, using HTTP installation');
            $this->installWordPressHttp($siteUrl, $adminUser, $adminPassword, $adminEmail);
        }
    }

    /**
     * Install WordPress via HTTP request (fallback method)
     */
    protected function installWordPressHttp(
        string $siteUrl,
        string $adminUser,
        string $adminPassword,
        string $adminEmail
    ): void {
        try {
            $response = Http::asForm()->post($siteUrl . '/wp-admin/install.php?step=2', [
                'weblog_title' => 'WordPress Site',
                'user_name' => $adminUser,
                'admin_password' => $adminPassword,
                'admin_password2' => $adminPassword,
                'admin_email' => $adminEmail,
                'Submit' => 'Install WordPress',
            ]);

            if (!$response->successful()) {
                Log::warning('WordPress HTTP installation returned non-success status', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('WordPress HTTP installation failed, but continuing', [
                'error' => $e->getMessage(),
            ]);
            // Don't throw - WordPress files are in place, user can complete installation manually
        }
    }

    /**
     * Set proper WordPress file permissions
     */
    protected function setWordPressPermissions(string $wpPath): void
    {
        // Set ownership
        exec("chown -R www-data:www-data {$wpPath}");

        // Set directory permissions
        exec("find {$wpPath} -type d -exec chmod 755 {} \\;");

        // Set file permissions
        exec("find {$wpPath} -type f -exec chmod 644 {} \\;");

        // Special permissions for wp-content
        if (file_exists($wpPath . '/wp-content')) {
            chmod($wpPath . '/wp-content', 0775);
        }
        if (file_exists($wpPath . '/wp-content/uploads')) {
            chmod($wpPath . '/wp-content/uploads', 0775);
        }
        if (file_exists($wpPath . '/wp-content/plugins')) {
            chmod($wpPath . '/wp-content/plugins', 0775);
        }
        if (file_exists($wpPath . '/wp-content/themes')) {
            chmod($wpPath . '/wp-content/themes', 0775);
        }
    }

    /**
     * Check if WordPress is already installed
     */
    public function isWordPressInstalled(string $path): bool
    {
        return File::exists($path . '/wp-config.php') && File::exists($path . '/wp-admin');
    }
}
