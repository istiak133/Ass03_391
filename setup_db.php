<?php
/**
 * DATABASE SETUP SCRIPT
 * Run locally to create the car_workshop database
 * Usage: php setup_db.php
 */

echo "🔧 Car Workshop Database Setup\n";
echo "================================\n\n";

// Try different MySQL connection approaches
$attempts = [
    ['localhost', 'root', '', 'localhost'],
    ['127.0.0.1', 'root', '', '127.0.0.1'],
    ['localhost', 'root', 'root', 'localhost'],
    ['/tmp/mysql.sock', 'root', '', 'localhost'],
];

$pdo = null;
$lastError = '';

foreach ($attempts as [$host, $user, $pass, $displayHost]) {
    try {
        echo "Attempting: user=$user@$displayHost ... ";
        $dsn = $host === '/tmp/mysql.sock' 
            ? "mysql:unix_socket=$host;charset=utf8mb4"
            : "mysql:host=$host;charset=utf8mb4";
        
        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        echo "✅ Connected!\n\n";
        break;
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        echo "❌ Failed\n";
    }
}

if (!$pdo) {
    echo "\n❌ Could not connect to MySQL:\n";
    echo "Error: $lastError\n\n";
    echo "Troubleshooting:\n";
    echo "1. Make sure MySQL is running: brew services list\n";
    echo "2. Check MySQL status: /opt/homebrew/opt/mysql/bin/mysql --version\n";
    echo "3. Try: brew services restart mysql\n";
    die();
}

// Read and execute database.sql
$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("❌ database.sql not found!\n");
}

$sql = file_get_contents($sqlFile);

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
);

echo "Executing SQL statements...\n";

$count = 0;
$errors = [];

foreach ($statements as $idx => $statement) {
    try {
        $pdo->exec($statement);
        $count++;
        if ($count % 5 === 0) {
            echo ".";
        }
    } catch (PDOException $e) {
        $errors[] = "Statement " . ($idx + 1) . ": " . $e->getMessage();
    }
}

echo "\n\n✅ Database setup complete!\n";
echo "📊 Executed $count SQL statements\n\n";

if (!empty($errors)) {
    echo "⚠️  Warnings:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    echo "\n";
}

// Verify tables exist
echo "Verifying tables:\n";
try {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'car_workshop'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "  ✅ $table\n";
    }
    
    // Count records
    echo "\nData verification:\n";
    $mechanics = $pdo->query("SELECT COUNT(*) FROM mechanics")->fetchColumn();
    $appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $admins = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    
    echo "  - Mechanics: $mechanics\n";
    echo "  - Appointments: $appointments\n";
    echo "  - Admins: $admins\n";
} catch (PDOException $e) {
    echo "⚠️  Could not verify tables: " . $e->getMessage() . "\n";
}

echo "\n✅ Setup complete! Database is ready.\n";
echo "🚀 Start server: php -S localhost:8080 router.php\n";
?>
