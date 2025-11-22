<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get demo subdomain
$subdomain = App\Models\Subdomain::where('subdomain_name', 'demo')
    ->whereHas('parentDomain', function($q) {
        $q->where('domain_name', 'npanel.at');
    })
    ->first();

if (!$subdomain) {
    echo "Demo subdomain not found\n";
    exit(1);
}

echo "Updating subdomain: {$subdomain->full_domain}\n";

// Update with correct SSL paths
$subdomain->update([
    'ssl_cert_path' => '/etc/letsencrypt/live/demo.npanel.at/fullchain.pem',
    'ssl_key_path' => '/etc/letsencrypt/live/demo.npanel.at/privkey.pem',
    'ssl_expiry_date' => now()->addDays(90),
]);

echo "SSL paths updated\n";

// Regenerate Nginx config
$nginxService = app(App\Services\NginxService::class);
$config = $nginxService->generateSubdomainConfig($subdomain->fresh());
$nginxService->writeConfig($subdomain->full_domain, $config);

echo "Nginx config regenerated\n";

// Reload Nginx
exec('sudo nginx -t && sudo systemctl reload nginx', $output, $return);

if ($return === 0) {
    echo "Nginx reloaded successfully\n";
    echo "demo.npanel.at is now accessible via HTTPS with its own certificate!\n";
} else {
    echo "Nginx reload failed:\n";
    echo implode("\n", $output) . "\n";
}
