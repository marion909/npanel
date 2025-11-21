<?php
/**
 * phpMyAdmin SSO Sign-On Script for nPanel
 * 
 * This script validates SSO tokens from nPanel and logs users into phpMyAdmin
 * Token-based authentication with single-use tokens stored in Redis
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Boot the application
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->handle($request);

use App\Services\PhpMyAdminService;

/**
 * Get credentials from SSO token
 */
function getCredentials(): ?array
{
    $token = $_GET['token'] ?? null;
    
    if (!$token) {
        return null;
    }
    
    try {
        $service = app(PhpMyAdminService::class);
        return $service->getSessionCredentials($token);
    } catch (\Exception $e) {
        \Log::error('phpMyAdmin SSO sign-on failed', [
            'error' => $e->getMessage(),
            'token' => substr($token, 0, 10) . '...'
        ]);
        return null;
    }
}

// Get credentials from token
$credentials = getCredentials();

if (!$credentials) {
    // Invalid or expired token
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied - nPanel phpMyAdmin SSO</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                margin: 0;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                padding: 40px;
                text-align: center;
            }
            h1 {
                color: #dc2626;
                font-size: 24px;
                margin-bottom: 16px;
            }
            p {
                color: #4b5563;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            a {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 12px 24px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
            }
            a:hover {
                background: #5568d3;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔒 Access Denied</h1>
            <p>
                Invalid or expired SSO token. Tokens are single-use and expire after 5 minutes.
                Please return to nPanel and try again.
            </p>
            <a href="<?php echo config('app.url'); ?>/dashboard">← Back to nPanel</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Start session for phpMyAdmin
session_name('phpMyAdmin_sso');
session_start();

// Set credentials in session
$_SESSION['PMA_single_signon_user'] = $credentials['user'];
$_SESSION['PMA_single_signon_password'] = $credentials['password'];
$_SESSION['PMA_single_signon_host'] = $credentials['host'];
$_SESSION['PMA_single_signon_port'] = $credentials['port'];
$_SESSION['PMA_single_signon_db'] = $credentials['db'];

// Optional: Store auth time
$_SESSION['PMA_single_signon_cfgupdate'] = ['DefaultDb' => $credentials['db']];

// Redirect to phpMyAdmin
$phpMyAdminUrl = config('npanel.phpmyadmin_url', 'http://localhost/phpmyadmin');
header('Location: ' . $phpMyAdminUrl . '/index.php');
exit;
