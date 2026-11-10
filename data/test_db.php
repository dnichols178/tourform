<?php
// --- Configuration ---
$serverName = "DRMSQL003"; 

echo "<h3>SQL Server Privileges Check (Server Level)</h3>";

try {
    // 1) Connect directly to the SQL Server instance without specifying a target database
    $dsn = "sqlsrv:Server=$serverName;TrustServerCertificate=1";
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2) Get Current User
    $identityStmt = $conn->query("SELECT SUSER_SNAME() AS CurrentUser");
    $currentUser = $identityStmt->fetchColumn();
    echo "<p>Checking server-level privileges for: <strong>" . htmlspecialchars($currentUser) . "</strong></p>";

    // 3) Check specific Database Creation rights
    $roleQuery = "
        SELECT 
            IS_SRVROLEMEMBER('sysadmin') AS is_sysadmin,
            IS_SRVROLEMEMBER('dbcreator') AS is_dbcreator,
            HAS_PERMS_BY_NAME(null, null, 'CREATE ANY DATABASE') AS can_create
    ";
    $roleStmt = $conn->query($roleQuery);
    $roles = $roleStmt->fetch(PDO::FETCH_ASSOC);

    echo "<h4>Can this account create databases via PHP?</h4>";
    
    if ($roles['is_sysadmin'] || $roles['is_dbcreator'] || $roles['can_create']) {
        echo "<p style='color: green; font-size: 1.2em;'><strong>✅ YES.</strong></p>";
        echo "<p>This account has sufficient server-level privileges to create new databases dynamically.</p>";
    } else {
        echo "<p style='color: red; font-size: 1.2em;'><strong>❌ NO.</strong></p>";
        echo "<p>This account lacks the required server-level permissions (needs dbcreator, sysadmin, or explicit CREATE ANY DATABASE).</p>";
    }

    // 4) List ALL Server-Level Permissions for this account
    echo "<h4>Complete List of Server-Level Privileges on DRMSQL003:</h4>";
    
    // fn_my_permissions evaluates the effective permissions of the caller across the server
    $permQuery = "SELECT permission_name FROM fn_my_permissions(NULL, 'SERVER') ORDER BY permission_name";
    $permStmt = $conn->query($permQuery);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>Permission Name</th></tr>";
    
    $hasPerms = false;
    while ($permRow = $permStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasPerms = true;
        echo "<tr><td>" . htmlspecialchars($permRow['permission_name']) . "</td></tr>";
    }
    
    if (!$hasPerms) {
        echo "<tr><td style='color: orange;'>No server-level permissions found.</td></tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ SQL Connection failed.</p>";
    echo "<strong>Error Details:</strong>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    
    // Note: If you get a login failure here but the previous script worked, 
    // it means this account is blocked from accessing its default database (usually 'master').
    if (stripos($e->getMessage(), 'Login failed') !== false) {
        echo "<p style='color: orange;'>⚠️ NOTE: The connection was rejected. This usually happens when an account has access to a specific database (like GalleryDb) but is denied access to its default routing database.</p>";
    }
}

$conn = null;
?>
