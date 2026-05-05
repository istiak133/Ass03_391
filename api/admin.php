<?php
// ============================================================
//  ADMIN API (admin.php)
//  Admin panel er shob actions handle kore:
//    - Login (password verify)
//    - Appointments list (with filters)
//    - Update date, mechanic, status
//    - Update mechanic max slots
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';


$action = $_GET['action'] ?? '';


// =========================================================
//  ACTION: LOGIN — Admin authentication
//  Method: POST
//  Body: {username, password}
// =========================================================

if ($action === 'login') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $username = sanitize($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Username and password are required'], 400);
    }

    // Database theke admin khuijo
    $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Admin na thakle ba password match na korle
    if (!$admin || !password_verify($password, $admin['password'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid username or password'], 401);
    }

    // Login success! Return a token or flag that frontend can use
    // For now, frontend will store admin_id and use X-Admin header
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'auth_token' => base64_encode($admin['id'] . ':' . time())  // Simple token
        ]
    ]);
}


// =========================================================
//  ADMIN AUTH CHECK — Login chara baki actions e lagbe
//  Skip auth check if action is login
// =========================================================
// Simple approach: Frontend header e 'X-Admin: true' pathabe
// Production e JWT/session use korte hobe

if ($action !== 'login') {
    $isAdmin = isset($_SERVER['HTTP_X_ADMIN']) && $_SERVER['HTTP_X_ADMIN'] === 'true';

    if (!$isAdmin) {
        jsonResponse(['success' => false, 'error' => 'Admin authentication required. Please login first.'], 401);
    }
}


// =========================================================
//  ACTION: APPOINTMENTS — Shob Appointments + Filters
//  Method: GET
//  Params: ?date=2026-03-12&mechanic_id=1&status=active&phone=017
// =========================================================

if ($action === 'appointments') {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'error' => 'GET method required'], 405);
    }

    // Base query — appointments + mechanic name JOIN
    $sql = '
        SELECT 
            a.id,
            a.client_name,
            a.client_address,
            a.client_phone,
            a.car_license_number,
            a.car_engine_number,
            a.appointment_date,
            a.status,
            a.created_at,
            a.mechanic_id,
            m.name AS mechanic_name,
            m.specialization AS mechanic_specialization
        FROM appointments a
        INNER JOIN mechanics m ON a.mechanic_id = m.id
        WHERE 1=1
    ';

    // WHERE 1=1 trick — porer prottek filter e shudhu "AND ..." add korle hoy
    // 1=1 always true — kono effect nei, kintu AND lagano easy kore dey

    $params = [];

    // Filter: Date
    if (!empty($_GET['date'])) {
        $sql .= ' AND a.appointment_date = ?';
        $params[] = $_GET['date'];
    }

    // Filter: Mechanic
    if (!empty($_GET['mechanic_id'])) {
        $sql .= ' AND a.mechanic_id = ?';
        $params[] = (int)$_GET['mechanic_id'];
    }

    // Filter: Status
    if (!empty($_GET['status'])) {
        $sql .= ' AND a.status = ?';
        $params[] = $_GET['status'];
    }

    // Filter: Phone (partial match — LIKE '%017%')
    if (!empty($_GET['phone'])) {
        $sql .= ' AND a.client_phone LIKE ?';
        $params[] = '%' . $_GET['phone'] . '%';
    }

    $sql .= ' ORDER BY a.appointment_date DESC, a.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => $appointments,
        'count' => count($appointments)
    ]);
}


// =========================================================
//  ACTION: UPDATE_DATE — Appointment er date change
//  Method: POST
//  Body: {appointment_id, new_date}
// =========================================================

if ($action === 'update_date') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $appointmentId = (int)($body['appointment_id'] ?? 0);
    $newDate = sanitize($body['new_date'] ?? '');

    if ($appointmentId <= 0 || empty($newDate)) {
        jsonResponse(['success' => false, 'error' => 'Appointment ID and new date are required'], 400);
    }

    $dateCheck = validateDate($newDate);
    if (!$dateCheck['valid']) {
        jsonResponse(['success' => false, 'error' => $dateCheck['error']], 400);
    }

    // Appointment ta exist kore kina check
    $stmt = $pdo->prepare('SELECT id, mechanic_id, status FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        jsonResponse(['success' => false, 'error' => 'Appointment not found'], 404);
    }

    // Mechanic er new date e slot check
    $mechanicId = $appointment['mechanic_id'];
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS booked FROM appointments 
        WHERE mechanic_id = ? AND appointment_date = ? AND status = \'active\' AND id != ?
    ');
    $stmt->execute([$mechanicId, $newDate, $appointmentId]);
    $slotInfo = $stmt->fetch();

    $stmt2 = $pdo->prepare('SELECT max_daily_appointments FROM mechanics WHERE id = ?');
    $stmt2->execute([$mechanicId]);
    $mechanic = $stmt2->fetch();

    if ((int)$slotInfo['booked'] >= (int)$mechanic['max_daily_appointments']) {
        jsonResponse(['success' => false, 'error' => 'No available slots for this mechanic on the new date'], 409);
    }

    // Update
    $stmt = $pdo->prepare('UPDATE appointments SET appointment_date = ? WHERE id = ?');
    $stmt->execute([$newDate, $appointmentId]);

    jsonResponse(['success' => true, 'message' => 'Appointment date updated successfully']);
}


// =========================================================
//  ACTION: UPDATE_MECHANIC — Appointment er mechanic change
//  Method: POST
//  Body: {appointment_id, new_mechanic_id}
// =========================================================

if ($action === 'update_mechanic') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $appointmentId = (int)($body['appointment_id'] ?? 0);
    $newMechanicId = (int)($body['new_mechanic_id'] ?? 0);

    if ($appointmentId <= 0 || $newMechanicId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Appointment ID and new mechanic are required'], 400);
    }

    // Appointment exist check
    $stmt = $pdo->prepare('SELECT id, appointment_date FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        jsonResponse(['success' => false, 'error' => 'Appointment not found'], 404);
    }

    // New mechanic exist + active check
    $stmt = $pdo->prepare('SELECT id, name, max_daily_appointments FROM mechanics WHERE id = ? AND is_active = 1');
    $stmt->execute([$newMechanicId]);
    $mechanic = $stmt->fetch();

    if (!$mechanic) {
        jsonResponse(['success' => false, 'error' => 'New mechanic not found or inactive'], 404);
    }

    // New mechanic er oi date e slot check
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS booked FROM appointments 
        WHERE mechanic_id = ? AND appointment_date = ? AND status = \'active\' AND id != ?
    ');
    $stmt->execute([$newMechanicId, $appointment['appointment_date'], $appointmentId]);
    $slotInfo = $stmt->fetch();

    if ((int)$slotInfo['booked'] >= (int)$mechanic['max_daily_appointments']) {
        jsonResponse(['success' => false, 'error' => $mechanic['name'] . ' has no available slots on this date'], 409);
    }

    // Update
    $stmt = $pdo->prepare('UPDATE appointments SET mechanic_id = ? WHERE id = ?');
    $stmt->execute([$newMechanicId, $appointmentId]);

    jsonResponse(['success' => true, 'message' => 'Mechanic updated to ' . $mechanic['name']]);
}


// =========================================================
//  ACTION: UPDATE_STATUS — Appointment status change
//  Method: POST
//  Body: {appointment_id, new_status}
// =========================================================

if ($action === 'update_status') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $appointmentId = (int)($body['appointment_id'] ?? 0);
    $newStatus = sanitize($body['new_status'] ?? '');

    if ($appointmentId <= 0 || empty($newStatus)) {
        jsonResponse(['success' => false, 'error' => 'Appointment ID and new status are required'], 400);
    }

    // Allowed statuses
    $allowedStatuses = ['active', 'completed', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses)) {
        jsonResponse(['success' => false, 'error' => 'Invalid status. Allowed: active, completed, cancelled'], 400);
    }

    // Appointment exist check
    $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);

    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Appointment not found'], 404);
    }

    // Update
    $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $appointmentId]);

    jsonResponse(['success' => true, 'message' => 'Status updated to ' . $newStatus]);
}


// =========================================================
//  ACTION: UPDATE_MAX_SLOTS — Mechanic er max daily slots change
//  Method: POST
//  Body: {mechanic_id, max_slots}
// =========================================================

if ($action === 'update_max_slots') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $mechanicId = (int)($body['mechanic_id'] ?? 0);
    $maxSlots = (int)($body['max_slots'] ?? 0);

    if ($mechanicId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Mechanic ID is required'], 400);
    }

    if ($maxSlots < 1 || $maxSlots > 20) {
        jsonResponse(['success' => false, 'error' => 'Max slots must be between 1 and 20'], 400);
    }

    // Mechanic exist check
    $stmt = $pdo->prepare('SELECT id, name FROM mechanics WHERE id = ?');
    $stmt->execute([$mechanicId]);
    $mechanic = $stmt->fetch();

    if (!$mechanic) {
        jsonResponse(['success' => false, 'error' => 'Mechanic not found'], 404);
    }

    // Update
    $stmt = $pdo->prepare('UPDATE mechanics SET max_daily_appointments = ? WHERE id = ?');
    $stmt->execute([$maxSlots, $mechanicId]);

    jsonResponse([
        'success' => true,
        'message' => $mechanic['name'] . '\'s max daily slots updated to ' . $maxSlots
    ]);
}


// =========================================================
//  ACTION: MECHANICS — Admin mechanic list (for settings + dropdown)
//  Method: GET
// =========================================================

if ($action === 'mechanics') {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'error' => 'GET method required'], 405);
    }

    $stmt = $pdo->query('SELECT id, name, specialization, max_daily_appointments, is_active FROM mechanics ORDER BY name');
    $mechanics = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $mechanics]);
}


// =========================================================
//  ACTION: WAITING_LIST — Shob Waiting Customers
//  Method: GET
//  Params: ?mechanic_id=1&date=2026-03-12&status=waiting
// =========================================================

if ($action === 'waiting_list') {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'error' => 'GET method required'], 405);
    }

    // Base query
    $sql = '
        SELECT 
            w.id,
            w.client_name,
            w.client_phone,
            w.client_address,
            w.car_license_number,
            w.car_engine_number,
            w.appointment_date,
            w.status,
            w.created_at,
            w.mechanic_id,
            m.name AS mechanic_name,
            m.specialization AS mechanic_specialization
        FROM waiting_list w
        INNER JOIN mechanics m ON w.mechanic_id = m.id
        WHERE 1=1
    ';

    $params = [];

    // Filter: Mechanic
    if (!empty($_GET['mechanic_id'])) {
        $sql .= ' AND w.mechanic_id = ?';
        $params[] = (int)$_GET['mechanic_id'];
    }

    // Filter: Date
    if (!empty($_GET['date'])) {
        $sql .= ' AND w.appointment_date = ?';
        $params[] = $_GET['date'];
    }

    // Filter: Status
    if (!empty($_GET['status'])) {
        $sql .= ' AND w.status = ?';
        $params[] = $_GET['status'];
    }

    // Filter: Phone (partial match)
    if (!empty($_GET['phone'])) {
        $sql .= ' AND w.client_phone LIKE ?';
        $params[] = '%' . $_GET['phone'] . '%';
    }

    $sql .= ' ORDER BY w.appointment_date ASC, w.created_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $waitingList = $stmt->fetchAll();

    // Add eligibility check for each customer
    $waitingWithEligibility = [];
    foreach ($waitingList as $waiting) {
        $isEligible = validatePhoneSum($waiting['client_phone']);
        $waiting['eligible_for_confirmation'] = $isEligible;
        
        if ($isEligible) {
            $last3 = substr($waiting['client_phone'], -3);
            $sum = 0;
            for ($i = 0; $i < 3; $i++) {
                $sum += (int)$last3[$i];
            }
            $waiting['phone_last_3_digits'] = $last3;
            $waiting['phone_digit_sum'] = $sum;
        }
        
        $waitingWithEligibility[] = $waiting;
    }

    jsonResponse([
        'success' => true,
        'data' => $waitingWithEligibility,
        'count' => count($waitingWithEligibility),
        'note' => 'Only customers with last 3 phone digits sum = 21 can be confirmed'
    ]);
}


// =========================================================
//  ACTION: CONFIRM_WAITING — Move customer from waiting to confirmed
//  Method: POST
//  Body: {waiting_id}
//
//  RULES:
//  - Customer's last 3 phone digits must sum to 21
//  - A slot must be available for that mechanic on that date
//  - Customer will be moved to appointments table
// =========================================================

if ($action === 'confirm_waiting') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $waitingId = (int)($body['waiting_id'] ?? 0);

    if ($waitingId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Waiting ID is required'], 400);
    }

    // Get waiting customer
    $stmt = $pdo->prepare('SELECT * FROM waiting_list WHERE id = ? AND status = \'waiting\'');
    $stmt->execute([$waitingId]);
    $waiting = $stmt->fetch();

    if (!$waiting) {
        jsonResponse(['success' => false, 'error' => 'Waiting record not found or already processed'], 404);
    }

    // VALIDATE: Phone last 3 digits sum = 21?
    if (!validatePhoneSum($waiting['client_phone'])) {
        $last3 = substr($waiting['client_phone'], -3);
        $sum = (int)$last3[0] + (int)$last3[1] + (int)$last3[2];
        
        jsonResponse([
            'success' => false,
            'error' => 'Phone number does not meet eligibility criteria',
            'details' => "Last 3 digits: {$last3}, Sum: {$sum}. Required: 21"
        ], 400);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Check if slot is now available
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS booked FROM appointments 
            WHERE mechanic_id = ? AND appointment_date = ? AND status = \'active\'
        ');
        $stmt->execute([$waiting['mechanic_id'], $waiting['appointment_date']]);
        $slotInfo = $stmt->fetch();

        $stmt2 = $pdo->prepare('SELECT max_daily_appointments FROM mechanics WHERE id = ? AND is_active = 1');
        $stmt2->execute([$waiting['mechanic_id']]);
        $mechanic = $stmt2->fetch();

        if (!$mechanic) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'error' => 'Mechanic not found or inactive'], 404);
        }

        $booked = (int)$slotInfo['booked'];
        $maxSlots = (int)$mechanic['max_daily_appointments'];

        if ($booked >= $maxSlots) {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'error' => 'No available slots for this mechanic on this date',
                'details' => "Booked: $booked / $maxSlots"
            ], 409);
        }

        // Move to appointments
        $stmt = $pdo->prepare('
            INSERT INTO appointments 
                (client_name, client_phone, client_address, car_license_number, car_engine_number, mechanic_id, appointment_date, status)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, \'active\')
        ');

        $stmt->execute([
            $waiting['client_name'],
            $waiting['client_phone'],
            $waiting['client_address'],
            $waiting['car_license_number'],
            $waiting['car_engine_number'],
            $waiting['mechanic_id'],
            $waiting['appointment_date']
        ]);

        $appointmentId = $pdo->lastInsertId();

        // Update waiting list status to confirmed
        $stmt = $pdo->prepare('UPDATE waiting_list SET status = \'confirmed\' WHERE id = ?');
        $stmt->execute([$waitingId]);

        $pdo->commit();

        // Success!
        jsonResponse([
            'success' => true,
            'message' => 'Customer confirmed and appointment created!',
            'data' => [
                'waiting_id' => (int)$waitingId,
                'appointment_id' => (int)$appointmentId,
                'client_name' => $waiting['client_name'],
                'client_phone' => $waiting['client_phone'],
                'mechanic_id' => (int)$waiting['mechanic_id'],
                'appointment_date' => $waiting['appointment_date']
            ]
        ], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}


// =========================================================
//  ACTION: CANCEL_WAITING — Cancel a waiting customer
//  Method: POST
//  Body: {waiting_id}
// =========================================================

if ($action === 'cancel_waiting') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
    }

    $body = getRequestBody();
    $waitingId = (int)($body['waiting_id'] ?? 0);

    if ($waitingId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Waiting ID is required'], 400);
    }

    // Get waiting customer
    $stmt = $pdo->prepare('SELECT id, client_name FROM waiting_list WHERE id = ? AND status = \'waiting\'');
    $stmt->execute([$waitingId]);
    $waiting = $stmt->fetch();

    if (!$waiting) {
        jsonResponse(['success' => false, 'error' => 'Waiting record not found or already processed'], 404);
    }

    // Update status to cancelled
    $stmt = $pdo->prepare('UPDATE waiting_list SET status = \'cancelled\' WHERE id = ?');
    $stmt->execute([$waitingId]);

    jsonResponse([
        'success' => true,
        'message' => 'Waiting list entry cancelled',
        'data' => [
            'waiting_id' => (int)$waitingId,
            'client_name' => $waiting['client_name']
        ]
    ]);
}


// =========================================================
//  UNKNOWN ACTION
// =========================================================
jsonResponse(['success' => false, 'error' => 'Unknown action: ' . sanitize($action)], 400);
