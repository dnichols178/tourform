<?php
// --- Database Connection Variables ---
// Format: "ServerName\InstanceName" or "IPAddress,Port"
$serverName = "192.168.1.50,1433"; 
$username = "your_sql_username";
$password = "your_sql_password";
$database = "master"; // Connect to master to evaluate server-level permissions

echo "<h3>SQL Server Connection & Permission Test</h3>";

try {
    // 1) Test connection to the remote SQL Server
    // TrustServerCertificate=1 is useful for testing without a valid SSL cert on the SQL Server
    $dsn = "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=1";
    $conn = new PDO($dsn, $username, $password);
    
    // Set PDO to throw exceptions on error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Successfully connected to the remote SQL Server.</p>";

    // 2) Check account permissions to create databases
    // A user can create a database if they are a sysadmin, a member of the dbcreator role, 
    // or if they have been explicitly granted 'CREATE ANY DATABASE' permission.
    $query = "
        SELECT 
            IS_SRVROLEMEMBER('sysadmin') AS is_sysadmin,
            IS_SRVROLEMEMBER('dbcreator') AS is_dbcreator,
            HAS_PERMS_BY_NAME(null, null, 'CREATE ANY DATABASE') AS can_create_any_database
    ";
    
    $stmt = $conn->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Evaluate the results
    if ($row['is_sysadmin'] == 1 || $row['is_dbcreator'] == 1 || $row['can_create_any_database'] == 1) {
        echo "<p style='color: green;'>✅ The account <strong>HAS</strong> permissions to create databases on this SQL Server.</p>";
    } else {
        echo "<p style='color: red;'>❌ The account <strong>DOES NOT HAVE</strong> permissions to create databases.</p>";
    }
    
} catch (PDOException $e) {
    // Catch and display connection or query errors
    echo "<p style='color: red;'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the connection
$conn = null;
?>
