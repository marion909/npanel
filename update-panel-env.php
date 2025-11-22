<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

$domain = 'webadmin.npanel.at';
$envPath = base_path('.env');

if (!File::exists($envPath)) {
    echo "✗ .env file not found\n";
    exit(1);
}

$envContent = File::get($envPath);
$panelUrl = "https://{$domain}";

// Extract base domain
$baseDomain = implode('.', array_slice(explode('.', $domain), -2));
$sessionDomain = ".{$baseDomain}";

echo "Updating .env configuration for {$domain}\n\n";

// Update APP_URL
if (preg_match('/^APP_URL=/m', $envContent)) {
    $envContent = preg_replace('/^APP_URL=.*/m', "APP_URL={$panelUrl}", $envContent);
    echo "✓ Updated APP_URL\n";
} else {
    $envContent .= "APP_URL={$panelUrl}\n";
    echo "✓ Added APP_URL\n";
}

// Update NPANEL_URL
if (preg_match('/^NPANEL_URL=/m', $envContent)) {
    $envContent = preg_replace('/^NPANEL_URL=.*/m', "NPANEL_URL={$panelUrl}", $envContent);
    echo "✓ Updated NPANEL_URL\n";
} else {
    $envContent .= "NPANEL_URL={$panelUrl}\n";
    echo "✓ Added NPANEL_URL\n";
}

// Update SESSION_DOMAIN
if (preg_match('/^SESSION_DOMAIN=/m', $envContent)) {
    $envContent = preg_replace('/^SESSION_DOMAIN=.*/m', "SESSION_DOMAIN={$sessionDomain}", $envContent);
    echo "✓ Updated SESSION_DOMAIN\n";
} else {
    $envContent .= "SESSION_DOMAIN={$sessionDomain}\n";
    echo "✓ Added SESSION_DOMAIN\n";
}

// Update SANCTUM_STATEFUL_DOMAINS
$sanctumDomains = "{$domain},{$baseDomain},localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1";
if (preg_match('/^SANCTUM_STATEFUL_DOMAINS=/m', $envContent)) {
    preg_match('/^SANCTUM_STATEFUL_DOMAINS=(.*)$/m', $envContent, $matches);
    $existingDomains = isset($matches[1]) ? $matches[1] : '';
    
    $domainsArray = array_filter(explode(',', $existingDomains));
    $newDomains = [$domain, $baseDomain];
    
    foreach ($newDomains as $newDomain) {
        if (!in_array($newDomain, $domainsArray)) {
            $domainsArray[] = $newDomain;
        }
    }
    
    $sanctumDomains = implode(',', $domainsArray);
    $envContent = preg_replace('/^SANCTUM_STATEFUL_DOMAINS=.*/m', "SANCTUM_STATEFUL_DOMAINS={$sanctumDomains}", $envContent);
    echo "✓ Updated SANCTUM_STATEFUL_DOMAINS\n";
} else {
    $envContent .= "SANCTUM_STATEFUL_DOMAINS={$sanctumDomains}\n";
    echo "✓ Added SANCTUM_STATEFUL_DOMAINS\n";
}

File::put($envPath, $envContent);
Artisan::call('config:clear');

echo "\n✓ Configuration updated successfully!\n\n";
echo "Settings:\n";
echo "  APP_URL: {$panelUrl}\n";
echo "  NPANEL_URL: {$panelUrl}\n";
echo "  SESSION_DOMAIN: {$sessionDomain}\n";
echo "  SANCTUM_STATEFUL_DOMAINS: {$sanctumDomains}\n";
