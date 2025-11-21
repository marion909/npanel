<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base Path for Domain Directories
    |--------------------------------------------------------------------------
    |
    | This is the base path where all domain directories will be created.
    | Default: /home
    |
    */
    'base_path' => env('NPANEL_BASE_PATH', '/home'),

    /*
    |--------------------------------------------------------------------------
    | Nginx Configuration Paths
    |--------------------------------------------------------------------------
    |
    | Paths for Nginx virtual host configuration files.
    |
    */
    'nginx_sites_available' => env('NGINX_SITES_AVAILABLE', '/etc/nginx/sites-available'),
    'nginx_sites_enabled' => env('NGINX_SITES_ENABLED', '/etc/nginx/sites-enabled'),
    'nginx_config_test_command' => 'sudo nginx -t',
    'nginx_reload_command' => 'sudo systemctl reload nginx',

    /*
    |--------------------------------------------------------------------------
    | PHP-FPM Configuration
    |--------------------------------------------------------------------------
    |
    | Supported PHP versions and default version for new domains.
    |
    */
    'php_versions' => [
        '7.4' => '/usr/sbin/php-fpm7.4',
        '8.0' => '/usr/sbin/php-fpm8.0',
        '8.1' => '/usr/sbin/php-fpm8.1',
        '8.2' => '/usr/sbin/php-fpm8.2',
        '8.3' => '/usr/sbin/php-fpm8.3',
    ],
    'default_php_version' => env('NPANEL_DEFAULT_PHP_VERSION', '8.3'),
    'php_fpm_pool_dir' => '/etc/php/{version}/fpm/pool.d',
    'php_fpm_socket_dir' => '/var/run/php',
    'php_fpm_reload_command' => 'sudo systemctl reload php{version}-fpm',

    /*
    |--------------------------------------------------------------------------
    | SSL/TLS Configuration
    |--------------------------------------------------------------------------
    |
    | acme.sh configuration for Let's Encrypt SSL certificates.
    |
    */
    'acme_sh_path' => env('ACME_SH_PATH', '/root/.acme.sh/acme.sh'),
    'ssl_provider' => env('SSL_PROVIDER', 'letsencrypt'),
    'ssl_cert_base_path' => env('SSL_CERT_BASE_PATH', '/etc/letsencrypt/live'),
    'ssl_auto_renew' => env('SSL_AUTO_RENEW', true),

    /*
    |--------------------------------------------------------------------------
    | Default User for Domain Operations
    |--------------------------------------------------------------------------
    |
    | Unix user that will own domain directories and run PHP-FPM pools.
    | In production, this should be per-domain users for better isolation.
    |
    */
    'default_user' => env('NPANEL_DEFAULT_USER', 'www-data'),
    'default_group' => env('NPANEL_DEFAULT_GROUP', 'www-data'),

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Number of configuration backups to keep before cleanup.
    |
    */
    'config_backup_retention' => env('CONFIG_BACKUP_RETENTION', 10),

    /*
    |--------------------------------------------------------------------------
    | DNS Configuration
    |--------------------------------------------------------------------------
    |
    | DNS verification and external provider settings.
    |
    */
    'verify_dns_before_ssl' => env('VERIFY_DNS_BEFORE_SSL', false),
    'dns_propagation_wait' => env('DNS_PROPAGATION_WAIT', 300), // seconds

    /*
    |--------------------------------------------------------------------------
    | phpMyAdmin Configuration
    |--------------------------------------------------------------------------
    |
    | phpMyAdmin SSO integration settings.
    |
    */
    'phpmyadmin_url' => env('PHPMYADMIN_URL', 'http://localhost/phpmyadmin'),
    'phpmyadmin_sso_url' => env('PHPMYADMIN_SSO_URL', env('APP_URL') . '/phpmyadmin-sso.php'),

    /*
    |--------------------------------------------------------------------------
    | Mail Server Configuration
    |--------------------------------------------------------------------------
    |
    | Mail server integration settings for Postfix, Dovecot, and Roundcube.
    |
    */
    'mail_enabled' => env('MAIL_ENABLED', false),
    'roundcube_url' => env('NPANEL_ROUNDCUBE_URL', 'https://webmail.example.com'),
    'vmail_base_path' => env('VMAIL_BASE_PATH', '/var/vmail'),
    'vmail_uid' => env('VMAIL_UID', 5000),
    'vmail_gid' => env('VMAIL_GID', 5000),
];
