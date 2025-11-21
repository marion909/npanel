# Subdomain: {{ $subdomain->full_domain }}
server {
    listen 80;
    listen [::]:80;
    server_name {{ $subdomain->full_domain }};
    
@if($subdomain->ssl_enabled)
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {{ $subdomain->full_domain }};
    
    # SSL Configuration (shared with parent domain or own certificate)
    ssl_certificate {{ $sslCertPath }};
    ssl_certificate_key {{ $sslKeyPath }};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
@else
    # HTTP only (SSL not enabled)
@endif
    
    # Document Root
    root {{ $subdomain->document_root }};
    index index.php index.html index.htm;
    
    # Logging
    access_log /var/log/nginx/{{ str_replace('.', '_', $subdomain->full_domain) }}_access.log;
    error_log /var/log/nginx/{{ str_replace('.', '_', $subdomain->full_domain) }}_error.log;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
@if($subdomain->ssl_enabled)
    add_header Strict-Transport-Security "max-age=31536000" always;
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

    # Allow Let's Encrypt ACME Challenge
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }
    
    # Security: Deny Access to Hidden Files (except .well-known)
    location ~ /\.(?!well-known) {
        deny all;
    }
}
