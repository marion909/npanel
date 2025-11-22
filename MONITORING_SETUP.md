# System Monitoring Setup Guide

The Resource Monitoring Dashboard provides real-time insights into your server's performance, including CPU, memory, disk usage, network statistics, PHP-FPM pool status, and Nginx connection metrics.

## Features

- **Real-time System Metrics**: CPU usage, memory usage, disk space, load averages, and uptime
- **Network Statistics**: Traffic monitoring per network interface (RX/TX bytes)
- **PHP-FPM Pool Monitoring**: Active/idle processes, queue status, connection counts per pool
- **Nginx Statistics**: Active connections, accepts, handled requests, reading/writing/waiting states
- **Alert System**: Automatic warnings when metrics exceed thresholds (CPU >80%, Memory >90%, Disk >85%)
- **Historical Data**: Metrics stored every 5 minutes for trend analysis
- **Auto-refresh**: Dashboard updates every 5 seconds for real-time monitoring

## Installation Steps

### 1. Run Migrations

```bash
php artisan migrate
```

This creates the `monitoring_logs` table for historical metrics storage.

### 2. Configure Nginx Monitoring

Copy the monitoring configuration to your Nginx sites-available directory:

```bash
sudo cp config/nginx/monitoring.conf /etc/nginx/sites-available/npanel-monitoring.conf
sudo ln -s /etc/nginx/sites-available/npanel-monitoring.conf /etc/nginx/sites-enabled/
```

Or manually add to your main `nginx.conf`:

```nginx
server {
    listen 127.0.0.1:80;
    server_name localhost;

    # Nginx stub_status for monitoring
    location /nginx-status {
        stub_status on;
        access_log off;
        allow 127.0.0.1;
        deny all;
    }

    # PHP-FPM status endpoints (dynamically configured per pool)
    location ~ ^/php-fpm-status-(.+)$ {
        access_log off;
        allow 127.0.0.1;
        deny all;
        
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php-fpm-$1.sock;
    }
}
```

Test and reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 3. Enable PHP-FPM Status Pages

The PHP-FPM pool template has been updated to include status endpoints. For existing domains, regenerate their PHP-FPM configurations:

```bash
# Via Laravel Tinker
php artisan tinker

# In Tinker:
$domains = App\Models\Domain::all();
foreach ($domains as $domain) {
    $pool = $domain->phpFpmPool;
    if ($pool) {
        app('App\Services\PhpFpmService')->updatePool($pool, $domain);
    }
}

# Reload all PHP-FPM versions
exit
```

Then reload PHP-FPM services:

```bash
sudo systemctl reload php8.3-fpm
sudo systemctl reload php8.2-fpm
# ... for all installed PHP versions
```

### 4. Set Up Background Metrics Collection

The monitoring system collects metrics every 5 minutes via Laravel Scheduler. Add this cron job:

```bash
crontab -e
```

Add the following line (adjust path if needed):

```
* * * * * cd /path/to/npanel && php artisan schedule:run >> /dev/null 2>&1
```

Or run the scheduler worker (recommended for development):

```bash
php artisan schedule:work
```

### 5. Start Queue Worker (if not already running)

The `CollectMetricsJob` runs via the queue system:

```bash
php artisan queue:work --tries=3
```

For production, configure Supervisor to keep the queue worker running:

```ini
[program:npanel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/npanel/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasserver=3600
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/npanel/storage/logs/queue.log
stopwaitsecs=3600
```

### 6. Test Monitoring Endpoints

Verify that status endpoints are accessible:

```bash
# Test Nginx status
curl http://127.0.0.1/nginx-status

# Expected output:
# Active connections: 1 
# server accepts handled requests
#  123 123 456 
# Reading: 0 Writing: 1 Waiting: 0

# Test PHP-FPM status (replace {pool-name} with actual pool)
curl http://127.0.0.1/php-fpm-status-example-com?json

# Expected: JSON response with pool statistics
```

### 7. Access the Dashboard

Navigate to **Monitoring** in the nPanel navigation menu or visit `/monitoring` in your browser.

## Configuration

### Alert Thresholds

Default thresholds are configured in `SystemMonitorService`:

- **CPU**: Warning at 80%
- **Memory**: Critical at 90%
- **Disk**: Warning at 85%
- **Load Average**: Warning when 5-min load > (CPU cores × 2)

To customize, edit `app/Services/SystemMonitorService.php`:

```php
public function checkThresholds(array $metrics): array
{
    // Modify thresholds here
    if ($metrics['cpu'] > 80) { ... }
    if ($metrics['memory']['percentage'] > 90) { ... }
    // ...
}
```

### Metrics Collection Frequency

By default, metrics are collected every 5 minutes and stored in the database. To change this, edit `routes/console.php`:

```php
// Change from everyFiveMinutes() to your preferred interval
Schedule::job(new CollectMetricsJob())->everyMinute(); // Every minute
Schedule::job(new CollectMetricsJob())->everyTenMinutes(); // Every 10 minutes
Schedule::job(new CollectMetricsJob())->hourly(); // Every hour
```

### Dashboard Refresh Rate

The dashboard auto-refreshes every 5 seconds. To change this, edit `resources/js/Pages/Monitoring/Index.vue`:

```javascript
// Change 5000 (5 seconds) to your preferred milliseconds
updateInterval = setInterval(fetchMetrics, 5000);
```

## Troubleshooting

### PHP-FPM Status Not Available

If PHP-FPM pools show "Status not accessible":

1. Verify the pool config has `pm.status_path` enabled:
   ```bash
   cat /etc/php/8.3/fpm/pool.d/example-com.conf | grep status_path
   ```

2. Check Nginx can access the PHP-FPM socket:
   ```bash
   ls -la /var/run/php/php-fpm-*.sock
   ```

3. Test directly:
   ```bash
   SCRIPT_FILENAME=/status REQUEST_METHOD=GET cgi-fcgi -bind -connect /var/run/php/php-fpm-example-com.sock
   ```

### Nginx Status Returns 404

1. Verify the monitoring config is included:
   ```bash
   nginx -T | grep nginx-status
   ```

2. Ensure the server block listens on 127.0.0.1:
   ```bash
   curl -v http://127.0.0.1/nginx-status
   ```

3. Check if stub_status module is enabled:
   ```bash
   nginx -V 2>&1 | grep -o with-http_stub_status_module
   ```

### Metrics Not Being Collected

1. Check if scheduler is running:
   ```bash
   php artisan schedule:list
   ```

2. Verify queue worker is active:
   ```bash
   ps aux | grep queue:work
   ```

3. Check logs for errors:
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. Manually dispatch the job to test:
   ```bash
   php artisan tinker
   App\Jobs\CollectMetricsJob::dispatch();
   ```

### Permission Errors Reading /proc Files

The monitoring service reads from `/proc/stat`, `/proc/meminfo`, `/proc/net/dev`, etc. These files should be readable by the web server user:

```bash
ls -l /proc/stat /proc/meminfo /proc/net/dev
# Should show: -r--r--r-- (world-readable)
```

If running nPanel as a non-root user, ensure the user has read access to these files.

## Performance Considerations

- **Database Growth**: The `monitoring_logs` table will grow over time. Consider implementing a cleanup job to remove old metrics:
  
  ```php
  // Delete metrics older than 30 days
  DB::table('monitoring_logs')
      ->where('created_at', '<', now()->subDays(30))
      ->delete();
  ```

- **API Load**: The dashboard makes API requests every 5 seconds. For high-traffic panels, consider increasing the refresh interval or implementing caching.

- **Disk I/O**: Reading `/proc` files is generally fast, but on heavily loaded systems, consider increasing collection intervals.

## Security Notes

- **Localhost-Only Access**: Status endpoints are restricted to 127.0.0.1 and cannot be accessed externally.
- **Authentication Required**: The monitoring dashboard requires authentication via nPanel login.
- **No Sensitive Data**: Metrics do not expose sensitive information like file contents or database credentials.

## Future Enhancements

Potential improvements for the monitoring system:

1. **Historical Charts**: Integrate Chart.js or ECharts for visual trends
2. **Email Alerts**: Send notifications when thresholds are exceeded
3. **Per-Domain Metrics**: Track resource usage by individual domains
4. **Export Functionality**: Download metrics as CSV or JSON
5. **Multi-Server Support**: Aggregate metrics from multiple servers
6. **Custom Metrics**: Allow users to define custom monitoring endpoints

## Related Documentation

- [Laravel Task Scheduling](https://laravel.com/docs/11.x/scheduling)
- [Nginx stub_status Module](http://nginx.org/en/docs/http/ngx_http_stub_status_module.html)
- [PHP-FPM Status Page](https://www.php.net/manual/en/fpm.status.php)
