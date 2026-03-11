<?php
// ============================================================
//  MECHANIC API (mechanics.php)
//  3 ta action handle kore:
//    1. list         → shob mechanic er basic info
//    2. slots        → specific mechanic er specific date e slot info
//    3. next_available → mechanic full hole next free date
// ============================================================

// Config include → $pdo (database connection) pai
require_once __DIR__ . '/config.php';

// Helpers include → jsonResponse(), sanitize() etc. pai
require_once __DIR__ . '/helpers.php';


// -----------------------------------------
// REQUEST METHOD CHECK
// -----------------------------------------
// Ei endpoint e shudhu GET request allowed (data fetch korar jonno)
// POST/PUT/DELETE dile error debo

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Only GET requests allowed'], 405);
}


// -----------------------------------------
// ACTION ROUTING
// -----------------------------------------
// URL e ?action=xxx theke decide kori ki korbo
// Default 'list' — jodi action na dey tahole mechanic list debo

$action = $_GET['action'] ?? 'list';


// -----------------------------------------
// ACTION 1: LIST — Shob Mechanic er Info
// -----------------------------------------
// URL: /api/mechanics.php?action=list
// Return: shob active mechanic er id, name, specialization, max_daily_appointments

if ($action === 'list') {

    // SQL: shob active mechanic select koro, naam diye sort
    $stmt = $pdo->query('
        SELECT id, name, specialization, max_daily_appointments 
        FROM mechanics 
        WHERE is_active = 1 
        ORDER BY name
    ');

    // fetchAll() = shob row niye ay array hisabe
    $mechanics = $stmt->fetchAll();

    // JSON response — success + data
    jsonResponse([
        'success' => true,
        'data' => $mechanics
    ]);
}


// -----------------------------------------
// ACTION 2: SLOTS — Specific Mechanic er Specific Date e Slot Info
// -----------------------------------------
// URL: /api/mechanics.php?action=slots&mechanic_id=3&date=2026-03-15
// Return: mechanic info + booked count + free slots + full kina

if ($action === 'slots') {

    // Required parameters check
    $mechanicId = $_GET['mechanic_id'] ?? null;
    $date = $_GET['date'] ?? null;

    if (!$mechanicId || !$date) {
        jsonResponse([
            'success' => false, 
            'error' => 'mechanic_id and date are required'
        ], 400);
    }

    // Date validation
    $dateCheck = validateDate($date);
    if (!$dateCheck['valid']) {
        jsonResponse(['success' => false, 'error' => $dateCheck['error']], 400);
    }

    // SQL: Mechanic er info + oi date e koto appointment booked ache
    // LEFT JOIN karon — jodi kono appointment NAI taholeo mechanic er info ashbe (booked = 0)
    $stmt = $pdo->prepare('
        SELECT 
            m.id,
            m.name,
            m.specialization,
            m.max_daily_appointments,
            COUNT(a.id) AS booked
        FROM mechanics m
        LEFT JOIN appointments a 
            ON m.id = a.mechanic_id 
            AND a.appointment_date = ?
            AND a.status = \'active\'
        WHERE m.id = ? AND m.is_active = 1
        GROUP BY m.id
    ');

    // execute() — prepared statement e value gula pass kori
    // ? er jaygay value boshbe — 1st ? = date, 2nd ? = mechanic_id
    $stmt->execute([$date, $mechanicId]);

    $mechanic = $stmt->fetch();

    // Mechanic exist kore na
    if (!$mechanic) {
        jsonResponse(['success' => false, 'error' => 'Mechanic not found'], 404);
    }

    // Free slots calculate: max - booked
    $freeSlots = $mechanic['max_daily_appointments'] - $mechanic['booked'];
    $isFull = ($freeSlots <= 0);

    // Response prepare
    $response = [
        'success' => true,
        'data' => [
            'id' => (int)$mechanic['id'],
            'name' => $mechanic['name'],
            'specialization' => $mechanic['specialization'],
            'max_slots' => (int)$mechanic['max_daily_appointments'],
            'booked' => (int)$mechanic['booked'],
            'free_slots' => max(0, $freeSlots),  // Negative hote debo na
            'is_full' => $isFull,
        ]
    ];

    // Jodi full — next available date o diye dii (bonus info)
    if ($isFull) {
        $nextDate = findNextAvailableDate($pdo, $mechanicId, $date);
        $response['data']['next_available_date'] = $nextDate;
    }

    jsonResponse($response);
}


// -----------------------------------------
// ACTION 3: NEXT AVAILABLE — Full Hole Next Free Date Khuijo
// -----------------------------------------
// URL: /api/mechanics.php?action=next_available&mechanic_id=3&date=2026-03-15
// Return: Next date where this mechanic has free slots

if ($action === 'next_available') {

    $mechanicId = $_GET['mechanic_id'] ?? null;
    $date = $_GET['date'] ?? null;

    if (!$mechanicId || !$date) {
        jsonResponse([
            'success' => false, 
            'error' => 'mechanic_id and date are required'
        ], 400);
    }

    $nextDate = findNextAvailableDate($pdo, $mechanicId, $date);

    jsonResponse([
        'success' => true,
        'data' => [
            'mechanic_id' => (int)$mechanicId,
            'searched_from' => $date,
            'next_available_date' => $nextDate
        ]
    ]);
}


// -----------------------------------------
// UNKNOWN ACTION — Error
// -----------------------------------------
jsonResponse(['success' => false, 'error' => 'Unknown action: ' . sanitize($action)], 400);


// ============================================================
//  HELPER FUNCTION: findNextAvailableDate()
//  Specific mechanic er jonno next kon date e slot free ache
// ============================================================

function findNextAvailableDate($pdo, $mechanicId, $fromDate) {

    // Prothome mechanic er max_daily_appointments jene nii
    $stmt = $pdo->prepare('SELECT max_daily_appointments FROM mechanics WHERE id = ?');
    $stmt->execute([$mechanicId]);
    $mechanic = $stmt->fetch();

    if (!$mechanic) {
        return null;
    }

    $maxSlots = (int)$mechanic['max_daily_appointments'];

    // Prepared statement — bar bar use korbo loop e
    // Ekbar prepare, bar bar execute (performance better)
    $countStmt = $pdo->prepare('
        SELECT COUNT(*) AS booked 
        FROM appointments 
        WHERE mechanic_id = ? 
          AND appointment_date = ? 
          AND status = \'active\'
    ');

    // Next 30 days check kori (fromDate er porer din theke)
    for ($i = 1; $i <= 30; $i++) {

        // fromDate + $i days = check korar date
        $checkDate = date('Y-m-d', strtotime($fromDate . " +$i days"));

        $countStmt->execute([$mechanicId, $checkDate]);
        $result = $countStmt->fetch();

        // Jodi booked < max → free slot ache → ei date return koro!
        if ((int)$result['booked'] < $maxSlots) {
            return $checkDate;
        }
    }

    // 30 din er moddhe kono free date pawa jay nai
    return null;
}
