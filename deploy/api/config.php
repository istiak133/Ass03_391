<?php
// ============================================================
//  DATABASE CONNECTION — InfinityFree Hosting Version
//  InfinityFree er cPanel theke ei credentials pabe
// ============================================================

// =============================================
//  IMPORTANT: Niche er 4 ta value InfinityFree
//  er cPanel > MySQL Databases theke copy korbe
// =============================================

$host     = 'sql111.infinityfree.com';
$dbname   = 'if0_41359656_car_workshop';
$username = 'if0_41359656';
$password = 'bRDZR3D2QUoE';


$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed. Please try again later.'
    ]);
    exit;
}
