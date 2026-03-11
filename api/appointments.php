<?php
// ============================================================
//  APPOINTMENTS API (appointments.php)
//  Appointment booking, lookup, cancel handle kore
//
//  Actions:
//    POST ?action=book     → Notun appointment create
//    GET  ?action=lookup    → Client er appointments dekhao (Feature 12)
//    POST ?action=cancel    → Appointment cancel koro (Feature 13)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';


// -----------------------------------------
// ACTION ROUTING
// -----------------------------------------
$action = $_GET['action'] ?? '';


// =========================================================
//  ACTION: BOOK — Notun Appointment Create
//  Method: POST
//  Body: JSON {client_name, client_address, client_phone,
//              car_license_number, car_engine_number,
//              mechanic_id, appointment_date}
// =========================================================

if ($action === 'book') {

    // POST method check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    // ----- Step 1: Request body theke data nao -----
    $body = getRequestBody();

    if (!$body) {
        jsonResponse(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }

    // ----- Step 2: Required fields validate koro -----
    $requiredFields = [
        'client_name', 'client_address', 'client_phone',
        'car_license_number', 'car_engine_number',
        'mechanic_id', 'appointment_date'
    ];

    $missingFields = validateRequired($body, $requiredFields);
    if (!empty($missingFields)) {
        jsonResponse(['success' => false, 'error' => 'Missing: ' . implode(', ', $missingFields)], 400);
    }

    // ----- Step 3: Individual field validation -----

    // Sanitize — XSS protection
    $clientName    = sanitize($body['client_name']);
    $clientAddress = sanitize($body['client_address']);
    $clientPhone   = sanitize($body['client_phone']);
    $carLicense    = sanitize($body['car_license_number']);
    $carEngine     = sanitize($body['car_engine_number']);
    $mechanicId    = (int)$body['mechanic_id'];
    $appointmentDate = sanitize($body['appointment_date']);

    // Phone validation (validatePhone returns true/false)
    if (!validatePhone($clientPhone)) {
        jsonResponse(['success' => false, 'error' => 'Phone number must contain only digits'], 400);
    }

    // Date validation
    $dateCheck = validateDate($appointmentDate);
    if (!$dateCheck['valid']) {
        jsonResponse(['success' => false, 'error' => $dateCheck['error']], 400);
    }

    // Name empty check (after sanitize)
    if (strlen($clientName) === 0) {
        jsonResponse(['success' => false, 'error' => 'Client name cannot be empty'], 400);
    }

    // Address empty check
    if (strlen($clientAddress) === 0) {
        jsonResponse(['success' => false, 'error' => 'Client address cannot be empty'], 400);
    }

    // Car license empty check
    if (strlen($carLicense) === 0) {
        jsonResponse(['success' => false, 'error' => 'Car license number cannot be empty'], 400);
    }

    // Car engine empty check
    if (strlen($carEngine) === 0) {
        jsonResponse(['success' => false, 'error' => 'Car engine number cannot be empty'], 400);
    }

    // Mechanic ID valid check
    if ($mechanicId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid mechanic selected'], 400);
    }


    // ----- Step 4: Transaction start -----
    // Shob database check + insert ekshonge — kichu gol hole shob undo
    $pdo->beginTransaction();

    try {

        // ----- Step 5: Mechanic exists + active check -----
        $stmt = $pdo->prepare('SELECT id, name, max_daily_appointments FROM mechanics WHERE id = ? AND is_active = 1');
        $stmt->execute([$mechanicId]);
        $mechanic = $stmt->fetch();

        if (!$mechanic) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'error' => 'Selected mechanic not found or inactive'], 404);
        }

        // ----- Step 6: Slot availability check -----
        // Oi mechanic er oi date e koto ta active appointment ache
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS booked 
            FROM appointments 
            WHERE mechanic_id = ? AND appointment_date = ? AND status = \'active\'
        ');
        $stmt->execute([$mechanicId, $appointmentDate]);
        $slotInfo = $stmt->fetch();

        $booked = (int)$slotInfo['booked'];
        $maxSlots = (int)$mechanic['max_daily_appointments'];

        if ($booked >= $maxSlots) {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'error' => $mechanic['name'] . ' has no available slots on this date. All ' . $maxSlots . ' slots are booked.'
            ], 409);  // 409 = Conflict
        }

        // ----- Step 7: Duplicate check -----
        // Same phone + same date + same mechanic + same car = duplicate
        $stmt = $pdo->prepare('
            SELECT id FROM appointments 
            WHERE client_phone = ? 
              AND appointment_date = ? 
              AND mechanic_id = ? 
              AND car_license_number = ?
              AND status = \'active\'
        ');
        $stmt->execute([$clientPhone, $appointmentDate, $mechanicId, $carLicense]);

        if ($stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'error' => 'You already have an active appointment with this mechanic on this date for the same car.'
            ], 409);
        }

        // ----- Step 8: INSERT — Appointment create! -----
        $stmt = $pdo->prepare('
            INSERT INTO appointments 
                (client_name, client_address, client_phone, car_license_number, car_engine_number, mechanic_id, appointment_date)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $clientName,
            $clientAddress,
            $clientPhone,
            $carLicense,
            $carEngine,
            $mechanicId,
            $appointmentDate
        ]);

        // lastInsertId() = MySQL e shesh INSERT er auto-increment ID
        $appointmentId = $pdo->lastInsertId();

        // ----- Step 9: COMMIT — shob save koro permanently -----
        $pdo->commit();

        // Success response
        jsonResponse([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'data' => [
                'appointment_id' => (int)$appointmentId,
                'client_name' => $clientName,
                'mechanic_name' => $mechanic['name'],
                'appointment_date' => $appointmentDate,
                'slots_remaining' => $maxSlots - $booked - 1  // -1 karon eita booked hoye geche
            ]
        ], 201);  // 201 = Created

    } catch (Exception $e) {
        // Kichu gol hole — shob undo
        $pdo->rollBack();

        // MySQL duplicate key error (UNIQUE constraint violation)
        if ($e->getCode() == 23000) {
            jsonResponse([
                'success' => false,
                'error' => 'Duplicate booking: This car already has an appointment with this mechanic on this date.'
            ], 409);
        }

        jsonResponse(['success' => false, 'error' => 'Booking failed. Please try again.'], 500);
    }
}


// =========================================================
//  ACTION: LOOKUP — Client er Shob Appointment Dekhao
//  Method: GET
//  URL: ?action=lookup&phone=01711111111
// =========================================================

if ($action === 'lookup') {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'error' => 'GET method required'], 405);
    }

    $phone = $_GET['phone'] ?? '';

    if (empty($phone)) {
        jsonResponse(['success' => false, 'error' => 'Phone number is required'], 400);
    }

    if (!validatePhone($phone)) {
        jsonResponse(['success' => false, 'error' => 'Phone must contain only digits'], 400);
    }

    // INNER JOIN — appointments + mechanics table jokhon kori
    // a.* = appointments er shob column
    // m.name AS mechanic_name = mechanic er naam ke "mechanic_name" naam e pai
    $stmt = $pdo->prepare('
        SELECT 
            a.id,
            a.client_name,
            a.client_phone,
            a.car_license_number,
            a.car_engine_number,
            a.appointment_date,
            a.status,
            a.created_at,
            m.name AS mechanic_name,
            m.specialization AS mechanic_specialization
        FROM appointments a
        INNER JOIN mechanics m ON a.mechanic_id = m.id
        WHERE a.client_phone = ?
        ORDER BY a.appointment_date DESC, a.created_at DESC
    ');

    $stmt->execute([$phone]);
    $appointments = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => $appointments,
        'count' => count($appointments)
    ]);
}


// =========================================================
//  ACTION: CANCEL — Client Nijei Appointment Cancel Kore
//  Method: POST
//  Body: JSON {appointment_id, client_phone}
// =========================================================

if ($action === 'cancel') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $appointmentId = (int)($body['appointment_id'] ?? 0);
    $phone = sanitize($body['client_phone'] ?? '');

    if ($appointmentId <= 0 || empty($phone)) {
        jsonResponse(['success' => false, 'error' => 'Appointment ID and phone number are required'], 400);
    }

    // Appointment ta exist kore kina + oi client er kina + active kina check
    $stmt = $pdo->prepare('
        SELECT id, status FROM appointments 
        WHERE id = ? AND client_phone = ?
    ');
    $stmt->execute([$appointmentId, $phone]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        jsonResponse(['success' => false, 'error' => 'Appointment not found or phone does not match'], 404);
    }

    if ($appointment['status'] !== 'active') {
        jsonResponse(['success' => false, 'error' => 'Only active appointments can be cancelled'], 400);
    }

    // Status update: active → cancelled
    $stmt = $pdo->prepare('UPDATE appointments SET status = \'cancelled\' WHERE id = ?');
    $stmt->execute([$appointmentId]);

    jsonResponse([
        'success' => true,
        'message' => 'Appointment cancelled successfully.'
    ]);
}


// =========================================================
//  UNKNOWN ACTION
// =========================================================
jsonResponse(['success' => false, 'error' => 'Unknown action: ' . sanitize($action)], 400);
