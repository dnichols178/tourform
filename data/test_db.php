<?php
// --- Database Connection Variables ---
$serverName = "DRMSQL003"; 

echo "<h3>SQL Server Connection & Database Access Test</h3>";

try {
    // 1) Test connection using Windows Authentication
    // Omitting the "Database=" parameter connects to the user's default database.
    $dsn = "sqlsrv:Server=$serverName;TrustServerCertificate=1";
    
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Successfully connected to <strong>$serverName</strong>.</p>";

    // 2) Identify which Windows Account made the connection
    $identityQuery = "SELECT SUSER_SNAME() AS CurrentUser";
    $identityStmt = $conn->query($identityQuery);
    $identityRow = $identityStmt->fetch(PDO::FETCH_ASSOC);
    $currentUser = htmlspecialchars($identityRow['CurrentUser']);
    echo "<p><strong>Connected as:</strong> " . $currentUser . "</p>";

    // 3) List all databases this account has permission to access
    // HAS_DBACCESS returns 1 if the user has access to the database
    $dbQuery = "
        SELECT name 
        FROM sys.databases 
        WHERE HAS_DBACCESS(name) = 1 
        ORDER BY name
    ";
    
    $dbStmt = $conn->query($dbQuery);
    
    echo "<h4>Accessible Databases:</h4>";
    echo "<ul>";
    
    $dbCount = 0;
    while ($dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>" . htmlspecialchars($dbRow['name']) . "</li>";
        $dbCount++;
    }
    
    echo "</ul>";

    if ($dbCount === 0) {
        echo "<p style='color: orange;'>⚠️ This account connected successfully to the server but does not have access to any databases.</p>";
    } else {
        echo "<p><em>Total accessible databases: $dbCount</em></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><em>Note: If this fails, the IIS Application Pool Identity (likely a local virtual account or machine account) does not have a login mapped on DRMSQL003.</em></p>";
}

$conn = null;
?>
