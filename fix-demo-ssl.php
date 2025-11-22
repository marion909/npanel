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

echo "Found subdomain: {$subdomain->full_domain}\n";

// Enable SSL
$subdomain->ssl_enabled = true;
$subdomain->save();

echo "SSL enabled\n";

// Regenerate Nginx config
$nginxService = app(App\Services\NginxService::class);
$config = $nginxService->generateSubdomainConfig($subdomain);
$nginxService->writeConfig($subdomain->full_domain, $config);

echo "Nginx config regenerated\n";

// Test and reload
$nginxService->testAndReload();

echo "Nginx reloaded successfully\n";
echo "Demo subdomain now accessible via HTTPS\n";
