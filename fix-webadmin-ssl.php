<?php

$config = <<<'NGINX'
# nPanel Domain Configuration - webadmin.npanel.at
server {
    listen 80;
    listen [::]:80;
    server_name webadmin.npanel.at;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name webadmin.npanel.at;

    ssl_certificate /etc/letsencrypt/live/webadmin.npanel.at/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/webadmin.npanel.at/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;

    root /var/www/npanel/public;
    index index.php index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Let's Encrypt ACME Challenge
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }

    location ~ /\. {
        deny all;
    }
}
NGINX;

$tempPath = '/tmp/webadmin.npanel.at.conf';
$targetPath = '/etc/nginx/sites-available/webadmin.npanel.at.conf';

file_put_contents($tempPath, $config);
exec("sudo mv {$tempPath} {$targetPath}");

echo "Testing nginx config...\n";
exec('sudo nginx -t 2>&1', $output, $returnCode);
echo implode("\n", $output) . "\n";

if ($returnCode === 0) {
    echo "\nReloading nginx...\n";
    exec('sudo systemctl reload nginx');
    echo "✓ SSL configuration activated for webadmin.npanel.at\n";
    echo "Access panel at: https://webadmin.npanel.at\n";
} else {
    echo "✗ Nginx config test failed!\n";
}
