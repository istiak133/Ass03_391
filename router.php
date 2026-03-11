<?php
// ============================================================
//  ROUTER (router.php)
//  PHP built-in server er jonno traffic controller
//  Prottek request ke correct file e pathay
// ============================================================
//
//  Start command: php -S localhost:8080 router.php
//
//  Request flow:
//    Browser request → router.php → correct file
//
//  Route map:
//    /                    → public/index.html (frontend)
//    /api/mechanics.php   → api/mechanics.php
//    /api/appointments.php → api/appointments.php
//    /api/admin.php       → api/admin.php
//    static files         → PHP server handles directly
// ============================================================


// -----------------------------------------
// 1. Request URL Theke Path Ber Koro
// -----------------------------------------
// Browser er URL theke shudhu path ta nao (query string chodai)
// Example: '/api/mechanics.php?action=list' → '/api/mechanics.php'

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


// -----------------------------------------
// 2. API Requests Handle Koro (/api/...)
// -----------------------------------------
// Jodi URL '/api/' diye shuru hoy → API request
// strpos() check kore '/api/' shuru te (position 0) ache kina

if (strpos($uri, '/api/') === 0) {

    // '/api/mechanics.php' → 'api/mechanics.php' (leading slash remove)
    // substr($uri, 1) mane 1 number character theke baki tuku nao
    // '/api/mechanics.php' er 0 number e '/' ache, 1 theke 'api/mechanics.php'
    $apiFile = __DIR__ . '/' . substr($uri, 1);

    // File ta actually exist kore kina check
    if (file_exists($apiFile)) {
        require $apiFile;   // File include koro — oi file er code run hobe
        return true;        // Router ke bolo "ami handle korlam"
    }

    // API file exist kore na → 404 error
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
    return true;
}


// -----------------------------------------
// 3. Homepage Request Handle Koro (/)
// -----------------------------------------
// Jodi URL exactly '/' ba '/index.html' → homepage dekhao

if ($uri === '/' || $uri === '/index.html') {
    $indexFile = __DIR__ . '/public/index.html';

    if (file_exists($indexFile)) {
        // Content-Type header set kori — browser ke boli "eta HTML"
        header('Content-Type: text/html');

        // readfile() = file er content poore pathay browser e
        readfile($indexFile);
        return true;
    }

    // index.html na thakle error
    http_response_code(404);
    echo 'index.html not found';
    return true;
}


// -----------------------------------------
// 4. Static Files (CSS, JS, Images)
// -----------------------------------------
// return false = PHP built-in server ke bolo
// "ami handle korbo na — tumi nijei static file serve koro"
// PHP server automatically public/ folder theke file serve korbe

return false;
