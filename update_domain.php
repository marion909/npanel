<?php

require '/var/www/npanel/vendor/autoload.php';
$app = require_once '/var/www/npanel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Domain;

$domain = Domain::where('domain_name', 'npanel.at')->first();
if ($domain) {
    $domain->status = 'active';
    $domain->save();
    echo "Domain status updated to active\n";
} else {
    echo "Domain not found\n";
}
