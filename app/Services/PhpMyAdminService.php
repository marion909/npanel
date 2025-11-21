<?php

namespace App\Services;

use App\Models\Database;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PhpMyAdminService
{
    /**
     * Generate a single-use SSO token for phpMyAdmin access
     */
    public function generateSsoToken(Database $database): string
    {
        $token = Str::random(64);
        
        // Store credentials in Redis with 5-minute TTL
        $tokenData = [
            'database_id' => $database->id,
            'host' => config('database.connections.mysql_root.host'),
            'port' => config('database.connections.mysql_root.port'),
            'database' => $database->database_name,
            'username' => $database->mysql_user,
            'password' => $database->mysql_password, // Will be decrypted via accessor
            'created_at' => now()->toIso8601String(),
        ];
        
        // Store with 5-minute expiry
        Cache::put("phpmyadmin_sso:{$token}", $tokenData, now()->addMinutes(5));
        
        return $token;
    }
    
    /**
     * Validate and consume SSO token (one-time use)
     */
    public function consumeSsoToken(string $token): ?array
    {
        $key = "phpmyadmin_sso:{$token}";
        
        // Get token data
        $tokenData = Cache::get($key);
        
        if (!$tokenData) {
            return null;
        }
        
        // Delete token immediately (one-time use)
        Cache::forget($key);
        
        return $tokenData;
    }
    
    /**
     * Get phpMyAdmin URL for database
     */
    public function getPhpMyAdminUrl(Database $database): string
    {
        $token = $this->generateSsoToken($database);
        
        // Construct phpMyAdmin SSO URL
        $ssoUrl = config('npanel.phpmyadmin_sso_url', config('app.url') . '/phpmyadmin-sso.php');
        
        return "{$ssoUrl}?token={$token}";
    }
    
    /**
     * Validate token and return session credentials
     * Used by phpMyAdmin signon script
     */
    public function getSessionCredentials(string $token): ?array
    {
        $tokenData = $this->consumeSsoToken($token);
        
        if (!$tokenData) {
            return null;
        }
        
        return [
            'host' => $tokenData['host'],
            'port' => $tokenData['port'],
            'user' => $tokenData['username'],
            'password' => $tokenData['password'],
            'db' => $tokenData['database'],
        ];
    }
}
