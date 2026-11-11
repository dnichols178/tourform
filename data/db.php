<?php
// ==========================================
// Database Instances Configuration
// ==========================================
$servers = [
    'Primary Instance' => [
        'host'     => 'localhost',
        'port'     => '3306',
        'username' => 'root',
        'password' => 'your_3306_password'
    ],
    'DMS Instance' => [
        'host'     => '192.169.249.165',
        'port'     => '3307',
        'username' => 'dmsuser',
        'password' => 'your_3307_password'
    ]
];

$dashboard_data = [];

// Process each configured server
foreach ($servers as $name => $config) {
    $server_result = [
        'config'       => $config,
        'databases'    => [],
        'total_size'   => 0,
        'total_tables' => 0,
        'error'        => null
    ];

    try {
        // Build the DSN string including the specific port
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        
        // Set PDO to throw exceptions on errors
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Updated query to also count the tables in each database
        $query = "
            SELECT 
                table_schema AS db_name, 
                COUNT(table_name) AS table_count,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
            FROM 
                information_schema.TABLES 
            GROUP BY 
                table_schema
            ORDER BY 
                size_mb DESC
        ";

        $stmt = $pdo->query($query);
        $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $server_result['databases'] = $databases;
        $server_result['total_size'] = array_sum(array_column($databases, 'size_mb'));
        $server_result['total_tables'] = array_sum(array_column($databases, 'table_count'));

    } catch (PDOException $e) {
        // Catch connection or query errors so the other instance still loads
        $server_result['error'] = $e->getMessage();
    }

    $dashboard_data[$name] = $server_result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Instance MySQL Dashboard</title>
    <style>
        :root {
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --header-bg: #f8f9fa;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            padding: 30px;
            margin: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .card-header {
            background-color: var(--header-bg);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header h2 {
            margin: 0 0 5px 0;
            font-size: 1.25rem;
            color: #2c3e50;
        }
        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
        }
        .card-body {
            padding: 0;
            flex-grow: 1;
        }
        .error-message {
            padding: 20px;
            background-color: #fff3f3;
            color: #dc3545;
            border-left: 4px solid #dc3545;
            margin: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background-color: #fafbfa;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover td {
            background-color: #f8f9fa;
        }
        .num-col {
            text-align: right;
            white-space: nowrap;
        }
        .card-footer {
            padding: 15px 20px;
            background-color: var(--header-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 0.95rem;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Database Storage Dashboard</h1>
    </div>

    <div class="dashboard-grid">
        <?php foreach ($dashboard_data as $name => $data): ?>
            <div class="card">
                <div class="card-header">
                    <h2><?php echo htmlspecialchars($name); ?></h2>
                    <p class="card-subtitle">
                        <?php echo htmlspecialchars($data['config']['host']); ?> : <?php echo htmlspecialchars($data['config']['port']); ?> 
                        (User: <?php echo htmlspecialchars($data['config']['username']); ?>)
                    </p>
                </div>
                
                <div class="card-body">
                    <?php if ($data['error']): ?>
                        <div class="error-message">
                            <strong>Connection Error:</strong><br>
                            <?php echo htmlspecialchars($data['error']); ?>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Database Name</th>
                                    <th class="num-col">Tables</th>
                                    <th class="num-col">Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($data['databases'])): ?>
                                    <?php foreach ($data['databases'] as $db): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($db['db_name']); ?></td>
                                            <td class="num-col"><?php echo number_format($db['table_count']); ?></td>
                                            <td class="num-col"><?php echo number_format($db['size_mb'], 2); ?> MB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                            No databases found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if (!$data['error']): ?>
                    <div class="card-footer">
                        <span>Total Server Usage</span>
                        <span>
                            <?php echo number_format($data['total_tables']); ?> Tables | 
                            <?php echo number_format($data['total_size'], 2); ?> MB
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
