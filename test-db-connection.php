<?php
/**
 * Database Connection Test Script
 * Upload this file to test MySQL database connectivity
 */

// Database credentials from nPanel
$host = '127.0.0.1';
$port = 3306;
$database = 'npanel_at_test';
$username = 'db_npanel_at_test';
$password = 'rrBtLH0OTJ70bND7ZYqK';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #1a202c;
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .subtitle {
            color: #718096;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .info-grid {
            display: grid;
            gap: 16px;
            margin-bottom: 30px;
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }
        
        .info-value {
            font-family: 'Courier New', monospace;
            color: #2d3748;
            font-size: 14px;
            background: white;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
        }
        
        .test-result {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .success {
            background: #f0fdf4;
            border: 2px solid #86efac;
        }
        
        .error {
            background: #fef2f2;
            border: 2px solid #fca5a5;
        }
        
        .icon {
            font-size: 24px;
            line-height: 1;
        }
        
        .result-content h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .success h2 {
            color: #166534;
        }
        
        .error h2 {
            color: #991b1b;
        }
        
        .result-content p {
            font-size: 14px;
            line-height: 1.6;
        }
        
        .success p {
            color: #15803d;
        }
        
        .error p {
            color: #dc2626;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-top: 8px;
        }
        
        .details {
            background: #f7fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .details h3 {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .details ul {
            list-style: none;
            font-size: 13px;
            color: #2d3748;
        }
        
        .details li {
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .details li:before {
            content: "";
            color: #10b981;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            🔌 Database Connection Test
        </h1>
        <p class="subtitle">Testing MySQL database connectivity for nPanel</p>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Host</span>
                <span class="info-value"><?php echo htmlspecialchars($host); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Port</span>
                <span class="info-value"><?php echo htmlspecialchars($port); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Database</span>
                <span class="info-value"><?php echo htmlspecialchars($database); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Username</span>
                <span class="info-value"><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
        
        <?php
        $connectionSuccess = false;
        $errorMessage = '';
        $serverInfo = '';
        $databaseVersion = '';
        
        try {
            // Attempt to connect
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Get server info
            $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
            $databaseVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            // Test query
            $stmt = $pdo->query("SELECT DATABASE() as current_db, NOW() as db_time, VERSION() as version");
            $result = $stmt->fetch();
            
            // Test CRUD operations
            $crudTests = [
                'create_table' => false,
                'insert' => false,
                'select' => false,
                'update' => false,
                'delete' => false,
                'drop_table' => false,
            ];
            
            try {
                // Create test table
                $pdo->exec("CREATE TABLE IF NOT EXISTS npanel_test (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    test_data VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $crudTests['create_table'] = true;
                
                // Insert test data
                $stmt = $pdo->prepare("INSERT INTO npanel_test (test_data) VALUES (?)");
                $stmt->execute(['Test data from nPanel ' . date('Y-m-d H:i:s')]);
                $insertedId = $pdo->lastInsertId();
                $crudTests['insert'] = true;
                
                // Select test data
                $stmt = $pdo->prepare("SELECT * FROM npanel_test WHERE id = ?");
                $stmt->execute([$insertedId]);
                $testRow = $stmt->fetch();
                $crudTests['select'] = ($testRow !== false);
                
                // Update test data
                $stmt = $pdo->prepare("UPDATE npanel_test SET test_data = ? WHERE id = ?");
                $stmt->execute(['Updated test data', $insertedId]);
                $crudTests['update'] = ($stmt->rowCount() > 0);
                
                // Delete test data
                $stmt = $pdo->prepare("DELETE FROM npanel_test WHERE id = ?");
                $stmt->execute([$insertedId]);
                $crudTests['delete'] = ($stmt->rowCount() > 0);
                
                // Drop test table
                $pdo->exec("DROP TABLE npanel_test");
                $crudTests['drop_table'] = true;
                
            } catch (PDOException $e) {
                // Try to clean up if something failed
                try {
                    $pdo->exec("DROP TABLE IF EXISTS npanel_test");
                } catch (PDOException $cleanupEx) {
                    // Ignore cleanup errors
                }
            }
            
            $connectionSuccess = true;
            
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
        }
        
        if ($connectionSuccess): ?>
            <div class="test-result success">
                <span class="icon">✅</span>
                <div class="result-content">
                    <h2>Connection Successful! <span class="badge badge-success">Active</span></h2>
                    <p>Successfully connected to the MySQL database. All credentials are working correctly.</p>
                    
                    <div class="details">
                        <h3>Connection Details:</h3>
                        <ul>
                            <li>Database: <strong><?php echo htmlspecialchars($result['current_db']); ?></strong></li>
                            <li>Server Version: <strong><?php echo htmlspecialchars($result['version']); ?></strong></li>
                            <li>Current Time: <strong><?php echo htmlspecialchars($result['db_time']); ?></strong></li>
                            <li>Connection Type: <strong>PDO MySQL</strong></li>
                            <li>Character Set: <strong>UTF-8 (utf8mb4)</strong></li>
                        </ul>
                    </div>
                    
                    <div class="details" style="margin-top: 20px;">
                        <h3>CRUD Operations Test:</h3>
                        <ul style="display: grid; gap: 8px;">
                            <li style="<?php echo $crudTests['create_table'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['create_table']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>CREATE TABLE</strong> - Successfully created test table
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>CREATE TABLE</strong> - Failed to create table
                                <?php endif; ?>
                            </li>
                            <li style="<?php echo $crudTests['insert'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['insert']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>INSERT</strong> - Successfully inserted test data
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>INSERT</strong> - Failed to insert data
                                <?php endif; ?>
                            </li>
                            <li style="<?php echo $crudTests['select'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['select']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>SELECT</strong> - Successfully read test data
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>SELECT</strong> - Failed to read data
                                <?php endif; ?>
                            </li>
                            <li style="<?php echo $crudTests['update'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['update']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>UPDATE</strong> - Successfully updated test data
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>UPDATE</strong> - Failed to update data
                                <?php endif; ?>
                            </li>
                            <li style="<?php echo $crudTests['delete'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['delete']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>DELETE</strong> - Successfully deleted test data
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>DELETE</strong> - Failed to delete data
                                <?php endif; ?>
                            </li>
                            <li style="<?php echo $crudTests['drop_table'] ? '' : 'color: #dc2626;'; ?>">
                                <?php if ($crudTests['drop_table']): ?>
                                    <span style="color: #10b981;">✓</span> <strong>DROP TABLE</strong> - Successfully dropped test table
                                <?php else: ?>
                                    <span style="color: #dc2626;">✗</span> <strong>DROP TABLE</strong> - Failed to drop table
                                <?php endif; ?>
                            </li>
                        </ul>
                        <?php if (array_reduce($crudTests, fn($carry, $item) => $carry && $item, true)): ?>
                            <p style="margin-top: 12px; padding: 12px; background: #d1fae5; color: #065f46; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                ✅ All CRUD operations successful! Database has full read/write permissions.
                            </p>
                        <?php else: ?>
                            <p style="margin-top: 12px; padding: 12px; background: #fee2e2; color: #991b1b; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                ⚠️ Some CRUD operations failed. Check user privileges.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="test-result error">
                <span class="icon">❌</span>
                <div class="result-content">
                    <h2>Connection Failed <span class="badge badge-error">Error</span></h2>
                    <p><strong>Could not connect to the database.</strong></p>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    
                    <div class="details">
                        <h3>Possible Solutions:</h3>
                        <ul>
                            <li>Verify database credentials are correct</li>
                            <li>Ensure MySQL/MariaDB service is running</li>
                            <li>Check if user has proper privileges</li>
                            <li>Verify firewall allows database connections</li>
                            <li>Confirm database exists: <code><?php echo htmlspecialchars($database); ?></code></li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>🛡️ Powered by nPanel • <?php echo date('Y-m-d H:i:s'); ?></p>
            <p style="margin-top: 8px; font-size: 11px;">⚠️ Delete this file after testing for security</p>
        </div>
    </div>
</body>
</html>
