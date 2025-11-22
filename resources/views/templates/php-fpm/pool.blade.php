; PHP-FPM Pool: {{ $pool->pool_name }}
; Domain: {{ $domain->domain_name }}
; PHP Version: {{ $pool->php_version }}

[{{ $pool->pool_name }}]
user = {{ config('npanel.default_user') }}
group = {{ config('npanel.default_group') }}
listen = {{ $pool->socket_path }}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process Manager Configuration
pm = {{ $pool->pm_mode }}
pm.max_children = {{ $pool->pm_max_children }}
pm.start_servers = {{ $pool->pm_start_servers }}
pm.min_spare_servers = {{ $pool->pm_min_spare_servers }}
pm.max_spare_servers = {{ $pool->pm_max_spare_servers }}
pm.max_requests = 500

; Status page for monitoring
pm.status_path = /php-fpm-status-{{ $pool->pool_name }}
ping.path = /php-fpm-ping-{{ $pool->pool_name }}

; PHP Configuration
php_admin_value[error_log] = {{ dirname($domain->document_root) }}/logs/php_error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = {{ $pool->memory_limit }}
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = {{ $pool->max_execution_time }}
php_admin_value[max_input_time] = 300
php_admin_value[display_errors] = off

; Security: Restrict file access
php_admin_value[open_basedir] = {{ dirname($domain->document_root) }}:/tmp:/usr/share/php

; Security: Disable dangerous functions
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Session Configuration
php_admin_value[session.save_path] = {{ dirname($domain->document_root) }}/tmp
