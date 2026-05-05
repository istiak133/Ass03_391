<?php
// ============================================================
//  DATABASE CONNECTION (config.php)
//  Ei file shob API file include korbe — foundation file
// ============================================================


// -----------------------------------------
// 1. Database Credentials (Login Info)
// -----------------------------------------
// Ei 4 ta info MySQL e connect korte lagbe:
// LOCAL SETUP FOR LOCALHOST

$host     = 'localhost';                    // ← Local MySQL Host
$dbname   = 'car_workshop';                 // ← Database Name
$username = 'root';                         // ← MySQL Username  
$password = '';                             // ← MySQL Password (empty for local setup)


// -----------------------------------------
// 2. DSN (Data Source Name)
// -----------------------------------------
// DSN = Database er full address ekta string e
// Format: "mysql:host=ADDRESS;dbname=DB_NAME;charset=utf8mb4"
// charset=utf8mb4 mane Bangla/emoji shob character support korbe

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";


// -----------------------------------------
// 3. PDO Options (Connection Settings)
// -----------------------------------------
// Ei settings PDO ke bole KIVABE behave korte hobe

$options = [
    // ERRMODE_EXCEPTION:
    // Jodi kono SQL error hoy → PHP Exception throw korbe
    // Mane try-catch e oke dhora jabe
    // Na dile error chupiye rakhe — debug korte parba na!
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // FETCH_ASSOC:
    // Query result ke associative array hisabe return korbe
    // Mane: $row['client_name'] diye access korte parba
    // (column naam diye, number diye na)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // EMULATE_PREPARES = false:
    // Real prepared statements use korbe (fake na)
    // Mane SQL injection protection GENUINE hobe
    PDO::ATTR_EMULATE_PREPARES => false,
];


// -----------------------------------------
// 4. Create Connection (Try-Catch)
// -----------------------------------------
// try = "try koro connect korte"
// catch = "jodi fail kore, error dhoro — crash koro na"

try {
    // new PDO(...) = notun connection banao
    // $dsn = kothay connect korbo
    // $username = kon user hisabe
    // $password = ki password diye
    // $options = kivabe behave korbo
    $pdo = new PDO($dsn, $username, $password, $options);

    // Jodi ekhane ashte pare — connection SUCCESSFUL!
    // $pdo variable e ekhon connection stored ache
    // Ei $pdo diye pore shob query korbo

} catch (PDOException $e) {
    // Jodi connection FAIL kore — ekhane ashbe
    // $e->getMessage() = ki error hoyeche seta bole

    // Client ke beshi info dekhabo na (security risk)
    // Shudhu generic message debo
    http_response_code(500);  // 500 = Server Error
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Please try again later.'
    ]);
    exit;  // Baki code ar run hobe na — ekhane stop
}
