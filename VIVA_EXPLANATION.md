# 📝 DETAILED MODIFICATIONS EXPLANATION FOR VIVA

## Project: Car Workshop Online Appointment System
### Three Main Features Implementation

---

## 🎯 FEATURE 1: WAITING LIST SYSTEM
**When all slots are booked, customer gets added to waiting list (not rejected)**

### 1.1 Database Modification - `database.sql`

**File**: `/Users/istiakahmed/ass03_391/database.sql`

**Change Location**: After `admins` table definition (around line 140)

**What Was Added**:
```sql
CREATE TABLE waiting_list (
    id                  INT             AUTO_INCREMENT PRIMARY KEY,
    client_name         VARCHAR(100)    NOT NULL,
    client_phone        VARCHAR(20)     NOT NULL,
    client_address      VARCHAR(255)    NOT NULL,
    car_license_number  VARCHAR(50)     NOT NULL,
    car_engine_number   VARCHAR(50)     NOT NULL,
    appointment_date    DATE            NOT NULL,
    mechanic_id         INT             NOT NULL,
    status              ENUM('waiting', 'confirmed', 'cancelled', 'expired')
                                        NOT NULL DEFAULT 'waiting',
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_waiting_mechanic
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_waiting_status (status, appointment_date),
    INDEX idx_waiting_mechanic (mechanic_id, appointment_date),
    INDEX idx_waiting_phone (client_phone)
) ENGINE=InnoDB;
```

**Why This**: 
- নতুন টেবিল customers কে store করে যখন সব slots full থাকে
- `status` enum field = waiting/confirmed/cancelled/expired
- `mechanic_id`, `appointment_date` দিয়ে admin filter করতে পারবে
- Indexes দ্রুত search এর জন্য

**How to Create**: 
- Script: `/Users/istiakahmed/ass03_391/apply_waiting_list.php` এ গিয়ে সরাসরি execute করা হয়েছে

---

## 🎯 FEATURE 2: PHONE VALIDATION (Last 3 Digits Sum = 21)
**Only customers whose phone's last 3 digits sum to 21 can be confirmed from waiting**

### 2.1 Helper Function - `api/helpers.php`

**File**: `/Users/istiakahmed/ass03_391/api/helpers.php`

**Change Location**: শেষ এ (line 200+ area)

**What Was Added**:
```php
// -----------------------------------------
// 7. validatePhoneSum() — Phone Last 3 Digits Sum Check
// -----------------------------------------
// Ki kore: Phone number er last 3 digit sum = 21 kina check kore
// USED FOR: Waiting list confirmation validation

function validatePhoneSum($phone) {
    // Last 3 character extract
    $last3 = substr($phone, -3);
    
    // Each digit sum
    $sum = 0;
    for ($i = 0; $i < 3; $i++) {
        $sum += (int)$last3[$i];
    }
    
    // Sum = 21 kina check
    return $sum === 21;
}
```

**How It Works**:
```
Input: "01711111696"
Step 1: Last 3 chars = "696"
Step 2: Convert to digits: 6, 9, 6
Step 3: Sum = 6+9+6 = 21
Step 4: Return true ✓

Input: "01711111111"
Step 1: Last 3 chars = "111"
Step 2: Convert to digits: 1, 1, 1
Step 3: Sum = 1+1+1 = 3
Step 4: Return false ✗
```

**ব্যবহার**: Admin confirmation এর সময় এই function call হয়

---

## 🎯 FEATURE 3: WAITING LIST BOOKING LOGIC
**Booking logic change: Instead of error, add to waiting_list**

### 3.1 Appointment Booking Logic - `api/appointments.php`

**File**: `/Users/istiakahmed/ass03_391/api/appointments.php`

**Change Location**: Line 103-144 (Slot availability check area)

**Original Code (Before)**:
```php
// ----- Step 6: Slot availability check -----
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
    ], 409);  // ← এখানে error দেওয়া হতো
}
```

**Modified Code (After)**:
```php
// ----- Step 6: Slot availability check -----
$stmt = $pdo->prepare('
    SELECT COUNT(*) AS booked 
    FROM appointments 
    WHERE mechanic_id = ? AND appointment_date = ? AND status = \'active\'
');
$stmt->execute([$mechanicId, $appointmentDate]);
$slotInfo = $stmt->fetch();

$booked = (int)$slotInfo['booked'];
$maxSlots = (int)$mechanic['max_daily_appointments'];

// ===== CHECK IF ALL SLOTS ARE BOOKED =====
// Jodi sab slot book thake, customer ke waiting list e add koro
if ($booked >= $maxSlots) {
    // ✓ CHANGE 1: Instead of error, add to waiting_list
    
    $stmt = $pdo->prepare('
        INSERT INTO waiting_list 
            (client_name, client_address, client_phone, car_license_number, 
             car_engine_number, mechanic_id, appointment_date, status)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, \'waiting\')
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

    $waitingId = $pdo->lastInsertId();
    $pdo->commit();

    // ✓ CHANGE 2: Response সাথে status = 'WAITING_LIST' এবং 202 HTTP code
    jsonResponse([
        'success' => true,
        'status' => 'WAITING_LIST',
        'message' => 'All slots are currently booked! You have been added to the waiting list.',
        'data' => [
            'waiting_id' => (int)$waitingId,
            'client_name' => $clientName,
            'mechanic_name' => $mechanic['name'],
            'appointment_date' => $appointmentDate,
            'notification' => 'You are in the waiting zone. If another customer cancels their booking, admin can confirm your appointment.',
            'phone_requirement' => 'Your phone number\'s last 3 digits must sum to 21 for confirmation eligibility.'
        ]
    ], 202);  // 202 = Accepted (pending confirmation)
}
```

**Key Changes**:
1. ❌ No error thrown
2. ✅ INSERT into waiting_list table instead
3. ✅ Return status 202 (Accepted) instead of 409 (Conflict)
4. ✅ Notify customer about waiting zone
5. ✅ Explain phone requirement

**ফ্লোচার্ট**:
```
Customer books appointment
    ↓
Count active appointments for mechanic on that date
    ↓
If count < max_slots:
    → Create appointment ✓ (201)
    
If count >= max_slots:
    → Add to waiting_list ✓ (202)  ← NEW BEHAVIOR
```

---

## 🎯 FEATURE 4: ADMIN AUTHENTICATION FIX
**Admin login working properly + Authentication check fixed**

### 4.1 Admin Login Endpoint - `api/admin.php`

**File**: `/Users/istiakahmed/ass03_391/api/admin.php`

**Change Location**: Line 14-56 (login action)

**What Was Changed**:

**Original Code**:
```php
if ($action === 'login') {
    // ... validation code ...
    
    // Admin na thakle ba password match na korle
    if (!$admin || !password_verify($password, $admin['password'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid username or password'], 401);
    }

    // Login success!
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'admin_id' => $admin['id'],
            'username' => $admin['username']
        ]
    ]);
}
```

**Modified Code**:
```php
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
    // ✓ CHANGE: Added auth_token for frontend to use
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'auth_token' => base64_encode($admin['id'] . ':' . time())  // ← নতুন
        ]
    ]);
}
```

**Key Changes**:
- ✓ Added `auth_token` to response
- ✓ Token ব্যবহার করে frontend authentication maintain করতে পারবে

### 4.2 Auth Check Fix - `api/admin.php`

**File**: `/Users/istiakahmed/ass03_391/api/admin.php`

**Change Location**: Line 58-70 (Auth check)

**Original Code**:
```php
// =========================================================
//  ADMIN AUTH CHECK — Login chara baki actions e lagbe
// =========================================================
$isAdmin = isset($_SERVER['HTTP_X_ADMIN']) && $_SERVER['HTTP_X_ADMIN'] === 'true';

if (!$isAdmin) {
    jsonResponse(['success' => false, 'error' => 'Admin authentication required'], 401);
}
// ← এখানে সমস্যা: login action এ auth check হয় যা ভুল
```

**Modified Code**:
```php
// =========================================================
//  ADMIN AUTH CHECK — Login chara baki actions e lagbe
//  Skip auth check if action is login
// =========================================================
// Simple approach: Frontend header e 'X-Admin: true' pathabe
// Production e JWT/session use korte hobe

if ($action !== 'login') {  // ✓ CHANGE: Skip for login
    $isAdmin = isset($_SERVER['HTTP_X_ADMIN']) && $_SERVER['HTTP_X_ADMIN'] === 'true';

    if (!$isAdmin) {
        jsonResponse(['success' => false, 'error' => 'Admin authentication required. Please login first.'], 401);
    }
}
```

**Key Changes**:
- ✓ `if ($action !== 'login')` দিয়ে login endpoint এ auth check skip করা হয়
- ✓ অন্যান্য endpoints এ `X-Admin: true` header check করা হয়

**Why**: Login এ auth check করলে login করতে পারবে না, তাই এটি fix করা হয়েছে

---

## 🎯 FEATURE 5: ADMIN WAITING LIST MANAGEMENT ENDPOINTS
**3টি নতুন endpoints admin এর জন্য**

### 5.1 GET Waiting List - `api/admin.php`

**File**: `/Users/istiakahmed/ass03_391/api/admin.php`

**Change Location**: Line 145-220 (নতুন action)

**Code**:
```php
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

    // ✓ CHANGE: Phone validation check এর জন্য eligibility add করা
    // Add eligibility check for each customer
    $waitingWithEligibility = [];
    foreach ($waitingList as $waiting) {
        $isEligible = validatePhoneSum($waiting['client_phone']);  // ← validatePhoneSum() call
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
```

**Key Features**:
- ✓ waiting_list থেকে সব customers fetch করে
- ✓ mechanic, date, status, phone দিয়ে filter করা যায়
- ✓ প্রতিটি customer এর জন্য `eligible_for_confirmation` check করা হয়
- ✓ Phone digits এবং sum দেখায়

---

### 5.2 POST Confirm From Waiting - `api/admin.php`

**File**: `/Users/istiakahmed/ass03_391/api/admin.php`

**Change Location**: Line 224-340 (নতুন action)

**Code**:
```php
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

    // ✓ VALIDATION 1: PHONE VALIDATION
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
        // ✓ VALIDATION 2: SLOT CHECK
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

        // ✓ STEP 3: MOVE TO APPOINTMENTS
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

        // ✓ STEP 4: UPDATE WAITING STATUS
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
```

**ফ্লোচার্ট - Confirmation Process**:
```
Admin calls confirm_waiting with waiting_id
    ↓
Step 1: Get waiting customer from waiting_list
    ↓
Step 2: Validate phone (last 3 digits sum = 21)
    ✗ If not → Return error 400
    ✓ If yes → Continue
    ↓
Step 3: Check slot availability
    ✗ If no slots → Return error 409
    ✓ If slots available → Continue
    ↓
Step 4: Create appointment from waiting data
    ↓
Step 5: Update waiting_list status = 'confirmed'
    ↓
Step 6: Commit transaction
    ↓
Return success 201 ✓
```

**Key Validations**:
1. ✓ Phone last 3 digits sum = 21?
2. ✓ Slot available এখন?
3. ✓ Mechanic active?
4. ✓ Transaction rollback যদি কিছু fail হয়

---

### 5.3 POST Cancel Waiting - `api/admin.php`

**File**: `/Users/istiakahmed/ass03_391/api/admin.php`

**Change Location**: Line 344-375 (নতুন action)

**Code**:
```php
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
```

**Purpose**: Admin waiting list থেকে কোনো customer কে cancel করতে পারে

---

## 📊 SUMMARY OF ALL CHANGES

### Files Modified: 4টি

| File | Changes | Feature |
|------|---------|---------|
| **database.sql** | ✓ Added `waiting_list` table | Feature 1 |
| **api/helpers.php** | ✓ Added `validatePhoneSum()` function | Feature 2 |
| **api/appointments.php** | ✓ Modified booking logic (line 103-144) | Feature 1 & 3 |
| **api/admin.php** | ✓ Fixed login auth (line 14-70) | Feature 4 |
| **api/admin.php** | ✓ Added 3 new endpoints (line 145-375) | Feature 5 |

---

## 🔄 COMPLETE BOOKING FLOW

```
CUSTOMER BOOKING:

1. Customer submits form with:
   - client_name, phone, address
   - car_license, car_engine
   - mechanic_id, appointment_date

2. Server validation:
   ✓ Phone format check (digits only)
   ✓ Date format & future date check
   ✓ Mechanic exists & active check

3. Count active appointments for mechanic on date:
   
   IF count < max_slots (e.g., 3 < 4):
       → Create appointment ✓
       → Return 201 (Created)
       
   IF count >= max_slots (e.g., 4 >= 4):
       → Add to waiting_list ✓ (NEW)
       → Return 202 (Accepted, pending)
       → Customer notified: "You are in waiting zone"

---

ADMIN CONFIRMATION (FROM WAITING):

1. Admin sees waiting_list
   ✓ Shows phone's last 3 digits
   ✓ Shows sum of digits
   ✓ Shows eligible_for_confirmation = true/false

2. Admin clicks "Confirm" for customer:
   
   IF phone last 3 digits sum ≠ 21:
       → Return error 400 (Not eligible)
       
   IF phone last 3 digits sum = 21:
       ✓ Check if slot available
       
       IF no slot available:
           → Return error 409 (No slots)
           
       IF slot available:
           ✓ Create appointment from waiting data
           ✓ Update waiting_list status = 'confirmed'
           ✓ Return 201 (Created)
```

---

## 💻 VIVA QUESTIONS LIKELY TO ASK

### Q1: Waiting List কিভাবে কাজ করে?
**Answer**: 
- Booking এর সময় count করা হয় যে ঐ mechanic এর ঐ date এ কয়টা active appointment আছে
- যদি count >= max_slots হয় (সব slot full), তখন:
  - appointment table এ insert করা হয় না
  - waiting_list table এ insert করা হয় যে customer এর সব data সহ
  - Response এ status = 'WAITING_LIST' এবং 202 HTTP code return হয়
  - Customer notified হয়

### Q2: Phone validation কোথায় হয়?
**Answer**:
- Phone validation ২ জায়গায় হয়:
  1. **Booking এর সময** (appointments.php): `validatePhone()` - শুধু digits কিনা check
  2. **Admin confirmation এর সময** (admin.php): `validatePhoneSum()` - last 3 digits sum = 21 check

### Q3: Admin login সমস্যা কীভাবে fix হয়েছে?
**Answer**:
- Original problem: Auth check সব endpoints এ run হতো, login endpoint এও
- Fix: `if ($action !== 'login')` দিয়ে login action skip করা হয়েছে
- এখন: login এ কোনো auth check নেই, অন্যান্য actions এ `X-Admin: true` header থাকতে হয়

### Q4: Waiting list এর default credentials কি?
**Answer**:
- Username: `admin`
- Password: `admin123`
- Password is hashed in database using bcrypt

### Q5: Confirmation এর পুরো process কি?
**Answer**:
```
1. Admin sends waiting_id
2. Get waiting customer from waiting_list
3. Validate phone (validatePhoneSum)
4. Check slot availability
5. Insert into appointments
6. Update waiting_list status = 'confirmed'
7. Return success
```

### Q6: কেন response codes ভিন্ন? (201 vs 202 vs 400)
**Answer**:
- **201 Created**: Appointment successfully created (normal booking or confirmation)
- **202 Accepted**: Customer added to waiting list (pending confirmation)
- **400 Bad Request**: Phone validation failed or data invalid
- **409 Conflict**: No slots available

---

## 🎯 KEY TECHNICAL POINTS

### Point 1: Database Transactions
```php
$pdo->beginTransaction();
try {
    // Multiple operations
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();  // ← All changes undo হয় যদি error হয়
}
```
**Why**: যদি appointment create হয় কিন্তু waiting_list update fail হয়, সবকিছু undo হয়

### Point 2: Foreign Key Constraint
```sql
CONSTRAINT fk_waiting_mechanic
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
    ON DELETE RESTRICT
```
**Why**: mechanic delete করতে পারবে না যদি waiting_list এ তার record আছে

### Point 3: Prepared Statements
```php
$stmt = $pdo->prepare('SELECT * FROM waiting_list WHERE id = ? AND status = ?');
$stmt->execute([$waitingId, 'waiting']);
```
**Why**: SQL injection prevention - `?` placeholder safe রাখে

### Point 4: Enum Field
```sql
status ENUM('waiting', 'confirmed', 'cancelled', 'expired')
```
**Why**: শুধু এই ৪টি value only allowed, অন্য কোনো value insert হতে পারবে না

---

## 🚀 DATABASE QUERY EXAMPLES

### Query 1: Count slots for mechanic on date
```sql
SELECT COUNT(*) AS booked 
FROM appointments 
WHERE mechanic_id = 2 
  AND appointment_date = '2026-05-20' 
  AND status = 'active';
```

### Query 2: Get all waiting customers for a mechanic
```sql
SELECT w.*, m.name as mechanic_name
FROM waiting_list w
JOIN mechanics m ON w.mechanic_id = m.id
WHERE w.mechanic_id = 2 
  AND w.status = 'waiting'
ORDER BY w.created_at ASC;
```

### Query 3: Get eligible customers (phone sum = 21)
```sql
SELECT * FROM waiting_list
WHERE (
    CAST(SUBSTRING(client_phone, -3, 1) AS UNSIGNED) +
    CAST(SUBSTRING(client_phone, -2, 1) AS UNSIGNED) +
    CAST(SUBSTRING(client_phone, -1, 1) AS UNSIGNED) = 21
)
AND status = 'waiting';
```

---

## ✅ TESTING CHECKLIST

- [ ] Book appointment with available slots → 201
- [ ] Book appointment when full → 202
- [ ] Admin login → Success
- [ ] View waiting list → All customers shown
- [ ] Check eligible/non-eligible → Correct flag
- [ ] Confirm eligible customer → Success, moved to appointments
- [ ] Try confirm non-eligible → Error
- [ ] Cancel waiting entry → Success

---

## 🎓 FINAL SUMMARY FOR VIVA

**3 Main Features**:
1. **Waiting List**: When slots full → Add to waiting_list (202) instead of error
2. **Phone Validation**: Admin can only confirm if phone last 3 digits sum = 21
3. **Admin Management**: New endpoints to view, confirm, cancel waiting customers

**Key Files**:
- `database.sql` - waiting_list table
- `api/helpers.php` - validatePhoneSum() function
- `api/appointments.php` - booking logic modified
- `api/admin.php` - auth fixed + 3 new endpoints

**Technical**: Transactions, Foreign keys, Prepared statements, HTTP status codes, Database queries
