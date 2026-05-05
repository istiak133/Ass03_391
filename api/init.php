<?php
/**
 * DATABASE INITIALIZATION ENDPOINT
 * Accessible at: http://localhost:8080/api/init.php?action=setup
 * 
 * This endpoint sets up the database when MySQL root can be accessed.
 * Note: This should be deleted after first run for security!
 */

// Check if init is allowed (can add IP whitelist for production)
$action = $_GET['action'] ?? '';

if ($action === 'setup') {
    setupDatabase();
} elseif ($action === 'status') {
    checkStatus();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Available actions: ?action=setup or ?action=status'
    ]);
}

function setupDatabase() {
    echo "<h2>🔧 Car Workshop Database Setup</h2>";
    echo "<pre>";
    
    // Try to connect to MySQL without specifying a database
    $dsn = "mysql:host=localhost;charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✅ Connected to MySQL\n\n";
    } catch (PDOException $e) {
        echo "❌ Failed to connect: " . $e->getMessage() . "\n";
        echo "Make sure MySQL is running and root user has no password.\n";
        echo "</pre>";
        return;
    }
    
    // Read SQL file
    $sqlFile = __DIR__ . '/../database.sql';
    $sql = file_get_contents($sqlFile);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
    );
    
    $count = 0;
    $errors = [];
    
    foreach ($statements as $idx => $statement) {
        try {
            $pdo->exec($statement);
            $count++;
            if ($count % 3 === 0) echo ".";
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    echo "\n✅ Executed $count SQL statements\n";
    
    if (count($errors) > 0) {
        echo "\n⚠️  Errors (may be safe to ignore):\n";
        foreach (array_slice($errors, 0, 5) as $err) {
            echo "  - $err\n";
        }
        if (count($errors) > 5) {
            echo "  ... and " . (count($errors) - 5) . " more\n";
        }
    }
    
    // Verify
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "VERIFICATION:\n";
    echo str_repeat("=", 50) . "\n";
    
    try {
        $pdo2 = new PDO("mysql:host=localhost;dbname=car_workshop;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $stmt = $pdo2->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'car_workshop'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "✅ Database 'car_workshop' created!\n";
        echo "Tables: " . implode(', ', $tables) . "\n\n";
        
        // Count data
        $mechanics = $pdo2->query("SELECT COUNT(*) FROM mechanics")->fetchColumn();
        $admins = $pdo2->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        echo "Data loaded:\n";
        echo "  - Mechanics: $mechanics\n";
        echo "  - Admins: $admins\n";
        
    } catch (PDOException $e) {
        echo "❌ Verification failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Setup complete!\n";
    echo "🚀 Go to: http://localhost:8080\n";
    echo "</pre>";
}

function checkStatus() {
    echo "<h2>Database Status Check</h2>";
    echo "<pre>";
    
    try {
        // Temporarily configure connection
        $host     = 'localhost';
        $dbname   = 'car_workshop';
        $username = 'root';
        $password = '';
        $dsn      = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options  = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "✅ Database connected!\n\n";
        echo "Tables found:\n";
        foreach ($tables as $table) {
            echo "  ✅ $table\n";
        }
        
        // Count records
        echo "\nData summary:\n";
        $mechanics = $pdo->query("SELECT COUNT(*) as cnt FROM mechanics")->fetch();
        $appointments = $pdo->query("SELECT COUNT(*) as cnt FROM appointments")->fetch();
        $admins = $pdo->query("SELECT COUNT(*) as cnt FROM admins")->fetch();
        
        echo "  - Mechanics: " . $mechanics['cnt'] . "\n";
        echo "  - Appointments: " . $appointments['cnt'] . "\n";
        echo "  - Admins: " . $admins['cnt'] . "\n";
        
        echo "\n✅ All systems operational!\n";
        
    } catch (PDOException $e) {
        echo "❌ Database not ready: " . $e->getMessage() . "\n\n";
        echo "Next steps:\n";
        echo "1. Make sure MySQL is running\n";
        echo "2. Visit: http://localhost:8080/api/init.php?action=setup\n";
    }
    
    echo "</pre>";
}
?>
