@if($domain->ssl_enabled && $domain->ssl_cert_path && $domain->ssl_key_path)
# HTTP Server - Redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain->domain_name }} www.{{ $domain->domain_name }};
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name {{ $domain->domain_name }} www.{{ $domain->domain_name }};
    
    # SSL Configuration
    ssl_certificate {{ $domain->ssl_cert_path }};
    ssl_certificate_key {{ $domain->ssl_key_path }};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
@else
# HTTP Server (SSL not configured)
server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain->domain_name }} www.{{ $domain->domain_name }};
@endif
    
    # Document Root
    root {{ $domain->document_root }};
    index index.php index.html index.htm;
    
    # Logging
    access_log /var/log/nginx/{{ $domain->domain_name }}_access.log;
    error_log /var/log/nginx/{{ $domain->domain_name }}_error.log;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
@if($domain->ssl_enabled)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
@endif
    
    # PHP-FPM Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{{ $phpFpmSocket }};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static Files Caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
    }
    
    # Security: Deny Access to Hidden Files
    location ~ /\. {
        deny all;
    }
    
    # Security: Deny Access to Sensitive Files
    location ~ ^/(composer|package|package-lock|yarn)\.(json|lock)$ {
        deny all;
    }
    
    # Prevent access to .git directories
    location ~ /\.git {
        deny all;
    }
}
