<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Exception;

class SettingsController extends Controller
{
    /**
     * Display settings page.
     */
    public function index()
    {
        $settings = [
            // System settings
            'base_path' => config('npanel.base_path'),
            'default_php_version' => config('npanel.default_php_version'),
            'nginx_sites_available' => config('npanel.nginx_sites_available'),
            'nginx_sites_enabled' => config('npanel.nginx_sites_enabled'),
            'panel_url' => config('npanel.panel_url'),
            
            // SSL settings
            'acme_sh_path' => config('npanel.acme_sh_path'),
            'ssl_provider' => config('npanel.ssl_provider'),
            'ssl_cert_base_path' => config('npanel.ssl_cert_base_path'),
            'ssl_auto_renew' => config('npanel.ssl_auto_renew'),
            
            // User/Security settings
            'default_user' => config('npanel.default_user'),
            'default_group' => config('npanel.default_group'),
            'config_backup_retention' => config('npanel.config_backup_retention'),
            
            // PHP versions
            'php_versions' => array_keys(config('npanel.php_versions')),
            
            // Mail settings
            'mail_enabled' => config('npanel.mail_enabled'),
            'roundcube_url' => config('npanel.roundcube_url'),
            
            // Registration
            'registration_enabled' => $this->isRegistrationEnabled(),
            
            // User count
            'total_users' => \App\Models\User::count(),
        ];

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'base_path' => 'nullable|string',
            'default_php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3',
            'panel_url' => ['nullable', 'url', 'regex:/^https:\/\/.+/'],
            'acme_sh_path' => 'nullable|string',
            'ssl_provider' => 'nullable|string|in:letsencrypt,manual',
            'ssl_auto_renew' => 'nullable|boolean',
            'default_user' => 'nullable|string',
            'default_group' => 'nullable|string',
            'config_backup_retention' => 'nullable|integer|min:1|max:100',
            'roundcube_url' => 'nullable|url',
            'registration_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $envPath = base_path('.env');
            
            if (!file_exists($envPath)) {
                return back()->with('error', '.env file not found');
            }

            $envContent = file_get_contents($envPath);
            $updated = false;

            // Update environment variables
            $envMappings = [
                'base_path' => 'NPANEL_BASE_PATH',
                'default_php_version' => 'NPANEL_DEFAULT_PHP_VERSION',
                'panel_url' => 'NPANEL_URL',
                'acme_sh_path' => 'ACME_SH_PATH',
                'ssl_provider' => 'SSL_PROVIDER',
                'ssl_auto_renew' => 'SSL_AUTO_RENEW',
                'default_user' => 'NPANEL_DEFAULT_USER',
                'default_group' => 'NPANEL_DEFAULT_GROUP',
                'config_backup_retention' => 'CONFIG_BACKUP_RETENTION',
                'roundcube_url' => 'NPANEL_ROUNDCUBE_URL',
            ];

            foreach ($envMappings as $key => $envVar) {
                if ($request->has($key)) {
                    $value = $request->input($key);
                    
                    // Convert boolean to string
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    $envContent = $this->updateEnvVariable($envContent, $envVar, $value);
                    $updated = true;
                }
            }

            // Handle registration enabled/disabled
            if ($request->has('registration_enabled')) {
                $enabled = $request->boolean('registration_enabled');
                $this->setRegistrationEnabled($enabled);
                $updated = true;
            }

            // Write updated .env file
            if ($updated) {
                file_put_contents($envPath, $envContent);
                
                // Clear config cache
                Artisan::call('config:clear');
                
                Log::info('Settings updated successfully', [
                    'user_id' => Auth::id(),
                    'changes' => $request->all(),
                ]);
            }

            return back()->with('success', 'Settings updated successfully!');

        } catch (Exception $e) {
            Log::error('Failed to update settings: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Update user profile (name, email, password).
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            if ($request->filled('name')) {
                $user->name = $request->input('name');
            }

            if ($request->filled('email')) {
                $user->email = $request->input('email');
            }

            if ($request->filled('new_password')) {
                if (!Hash::check($request->input('current_password'), $user->password)) {
                    return back()->with('error', 'Current password is incorrect');
                }

                $user->password = Hash::make($request->input('new_password'));
            }

            $user->save();

            Log::info('User profile updated', ['user_id' => $user->id]);

            return back()->with('success', 'Profile updated successfully!');

        } catch (Exception $e) {
            Log::error('Failed to update profile: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
            ]);
            
            return back()->with('error', 'Failed to update profile: ' . $e->getMessage());
        }
    }

    /**
     * Update or append environment variable in .env content.
     */
    private function updateEnvVariable(string $envContent, string $key, $value): string
    {
        // Escape special characters in value
        $escapedValue = $this->escapeEnvValue($value);
        
        // Check if key exists
        $pattern = "/^{$key}=.*/m";
        
        if (preg_match($pattern, $envContent)) {
            // Update existing
            return preg_replace($pattern, "{$key}={$escapedValue}", $envContent);
        } else {
            // Append new
            return rtrim($envContent) . "\n{$key}={$escapedValue}\n";
        }
    }

    /**
     * Escape value for .env file.
     */
    private function escapeEnvValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        // If value contains spaces or special characters, wrap in quotes
        if (preg_match('/[\s#"]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    /**
     * Check if registration is enabled.
     */
    private function isRegistrationEnabled(): bool
    {
        $routesPath = base_path('routes/web.php');
        
        if (!file_exists($routesPath)) {
            return false;
        }

        $content = file_get_contents($routesPath);
        
        // Check if register routes are commented out
        return !str_contains($content, "// Route::get('/register'") && 
               !str_contains($content, "// Route::post('/register'");
    }

    /**
     * Enable or disable registration.
     */
    private function setRegistrationEnabled(bool $enabled): void
    {
        $routesPath = base_path('routes/web.php');
        
        if (!file_exists($routesPath)) {
            throw new Exception('routes/web.php not found');
        }

        $content = file_get_contents($routesPath);

        if ($enabled) {
            // Uncomment register routes
            $content = str_replace(
                "// Route::get('/register', [AuthController::class, 'showRegister'])->name('register');",
                "Route::get('/register', [AuthController::class, 'showRegister'])->name('register');",
                $content
            );
            $content = str_replace(
                "// Route::post('/register', [AuthController::class, 'register']);",
                "Route::post('/register', [AuthController::class, 'register']);",
                $content
            );
        } else {
            // Comment out register routes
            $content = str_replace(
                "Route::get('/register', [AuthController::class, 'showRegister'])->name('register');",
                "// Route::get('/register', [AuthController::class, 'showRegister'])->name('register');",
                $content
            );
            $content = str_replace(
                "Route::post('/register', [AuthController::class, 'register']);",
                "// Route::post('/register', [AuthController::class, 'register']);",
                $content
            );
        }

        file_put_contents($routesPath, $content);
        
        // Clear route cache
        Artisan::call('route:clear');
        
        Log::info('Registration ' . ($enabled ? 'enabled' : 'disabled'));
    }
}
