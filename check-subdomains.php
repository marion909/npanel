<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$domain = App\Models\Domain::where('domain_name', 'npanel.at')->first();

echo "Domain: {$domain->domain_name}\n";
echo "\nSubdomains:\n";
foreach ($domain->subdomains as $subdomain) {
    $fullName = $subdomain->subdomain_name === '@' 
        ? $domain->domain_name 
        : $subdomain->subdomain_name . '.' . $domain->domain_name;
    
    echo "  - {$subdomain->subdomain_name} => {$fullName}\n";
}

echo "\n=== ISSUE FOUND ===\n";
echo "The @ subdomain generates config as 'npanel.at.conf'\n";
echo "This overwrites the main domain's 'npanel.at.conf' config!\n";
