# 🚗 Car Workshop Online Appointment System

## ✅ Project Status: RUNNING & FULLY IMPLEMENTED

**Live URL**: http://localhost:8080  
**Database**: MySQL `car_workshop` ✓  
**Server**: PHP 8.4 Development Server ✓

---

## 📋 What's Implemented

### ✨ Core Features
- ✅ **Customer Booking System** - Simple, no registration required (phone-based)
- ✅ **5 Senior Mechanics** - Pre-configured with specializations
- ✅ **4 Slots Per Mechanic** - Per day, configurable by admin
- ✅ **Waiting List System** - Auto-adds customers when all slots booked
- ✅ **Admin Panel** - Manage appointments, mechanics, waiting list
- ✅ **Phone Validation** - Last 3 digits must sum to 21 for waiting list confirmation

---

## 🎯 Key Features Explained

### 1️⃣ **Normal Booking (When Slots Available)**
```
Customer fills form → Selects mechanic & date → Appointment created ✓
Response: 201 (Created) with appointment ID
```

### 2️⃣ **Waiting List Booking (When All Slots Full)**
```
Customer fills form → All 4 slots booked → Added to waiting_list ✓
Response: 202 (Accepted, pending) with waiting ID
Notification: "You are in the waiting zone..."
```

### 3️⃣ **Admin Confirmation (From Waiting List)**
```
Condition: Last 3 digits of phone must sum to 21
Example: 
  - Phone 01711111696 → Last 3: 6,9,6 → 6+9+6 = 21 ✓ ELIGIBLE
  - Phone 01711111111 → Last 3: 1,1,1 → 1+1+1 = 3  ✗ NOT ELIGIBLE

If eligible + slot available → Customer moved to appointments ✓
```

---

## 🔐 Admin Login Credentials

| Field | Value |
|-------|-------|
| **Username** | `admin` |
| **Password** | `admin123` |
| **URL** | http://localhost:8080 (Admin button in navbar) |

---

## 📊 Database Schema

### Tables Created
```
✓ mechanics        - 5 senior mechanics with specializations
✓ appointments     - Confirmed bookings
✓ waiting_list     - Waiting customers (auto-populated when full)
✓ admins          - Admin credentials (encrypted passwords)
```

### waiting_list Table Fields
```
id                 INT PRIMARY KEY
client_name        VARCHAR(100)
client_phone       VARCHAR(20)         ← Used for validation
client_address     VARCHAR(255)
car_license_number VARCHAR(50)
car_engine_number  VARCHAR(50)
appointment_date   DATE
mechanic_id        INT (Foreign Key)
status             ENUM('waiting', 'confirmed', 'cancelled', 'expired')
created_at         TIMESTAMP
updated_at         TIMESTAMP
```

---

## 🔌 API Endpoints

### 📱 Booking Endpoints
#### `POST /api/appointments.php?action=book`
```json
{
  "client_name": "Md. Rahim",
  "client_phone": "01711111696",
  "client_address": "Dhaka",
  "car_license_number": "Dhaka Metro 12-3456",
  "car_engine_number": "ABC123456",
  "mechanic_id": 1,
  "appointment_date": "2026-05-10"
}
```

**Response (Slots Available)**:
```json
{
  "success": true,
  "status": null,
  "message": "Appointment booked successfully!"
}
```

**Response (All Slots Full - WAITING LIST)**:
```json
{
  "success": true,
  "status": "WAITING_LIST",
  "message": "All slots are currently booked! You have been added to the waiting list.",
  "data": {
    "waiting_id": 5,
    "notification": "You are in the waiting zone. If another customer cancels their booking, admin can confirm your appointment.",
    "phone_requirement": "Your phone number's last 3 digits must sum to 21 for confirmation eligibility."
  }
}
```

---

### 🛡️ Admin Endpoints

#### `POST /api/admin.php?action=login` (No Auth Required)
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin_id": 1,
    "username": "admin",
    "auth_token": "..."
  }
}
```

#### `GET /api/admin.php?action=waiting_list` (Requires: X-Admin: true header)
**Query Params**:
- `mechanic_id` - Filter by mechanic
- `date` - Filter by date
- `status` - Filter by status (waiting, confirmed, cancelled, expired)
- `phone` - Filter by phone (partial match)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "client_name": "Md. Rahim",
      "client_phone": "01711111696",
      "mechanic_id": 1,
      "mechanic_name": "Md. Rahim Uddin",
      "appointment_date": "2026-05-10",
      "status": "waiting",
      "eligible_for_confirmation": true,
      "phone_last_3_digits": "696",
      "phone_digit_sum": 21
    }
  ],
  "count": 1
}
```

#### `POST /api/admin.php?action=confirm_waiting` (Requires: X-Admin: true header)
```json
{
  "waiting_id": 5
}
```

**Response (If Phone Valid & Slot Available)**:
```json
{
  "success": true,
  "message": "Customer confirmed and appointment created!",
  "data": {
    "waiting_id": 5,
    "appointment_id": 12,
    "client_name": "Md. Rahim"
  }
}
```

**Response (If Phone Invalid)**:
```json
{
  "success": false,
  "error": "Phone number does not meet eligibility criteria",
  "details": "Last 3 digits: 111, Sum: 3. Required: 21"
}
```

---

## 🧪 Testing The System

### Test 1: Book a Normal Appointment
1. Go to http://localhost:8080
2. Fill form with details
3. Select mechanic with available slots
4. Click "Review Booking" → Should succeed (201)

### Test 2: Test Waiting List
1. Book 4 appointments for same mechanic/date
2. Book 5th appointment for same date
3. Should get 202 response with "WAITING_LIST" status
4. Customer added to waiting list ✓

### Test 3: Test Admin Confirmation
**Eligible Phone** (6+9+6 = 21):
- Phone: `01711111696`
- Book as waiting → Admin confirms → ✓ Moved to appointments

**Non-Eligible Phone** (1+2+3 = 6):
- Phone: `01711111123`
- Book as waiting → Admin cannot confirm → ✗ Error message

### Test 4: Admin Login
1. Click "Admin" button
2. Enter: `admin` / `admin123`
3. Should login successfully
4. View waiting list with eligibility checks

---

## 🛠️ How It Works Behind The Scenes

### Booking Process Flow
```
1. Customer submits booking form
   ↓
2. Validate phone (digits only)
   ↓
3. Validate date (not past)
   ↓
4. Check mechanic exists + is active
   ↓
5. Count active appointments for mechanic on that date
   ↓
   IF count < max_slots:
      → Create appointment ✓ (Return 201)
   ELSE:
      → Add to waiting_list ✓ (Return 202)
```

### Admin Confirmation Flow
```
1. Admin calls confirm_waiting with waiting_id
   ↓
2. Get waiting customer record
   ↓
3. Validate phone: last 3 digits sum = 21?
   ✗ If not → Return error
   ✓ If yes → Continue
   ↓
4. Check if slot now available for mechanic/date
   ✗ If no → Return "No slots available"
   ✓ If yes → Continue
   ↓
5. Create appointment from waiting data
   ↓
6. Update waiting_list status to 'confirmed'
   ↓
7. Return success ✓
```

---

## 📝 Default Data

### Mechanics (5 Pre-Configured)
| ID | Name | Specialization | Max Slots |
|----|------|-----------------|-----------|
| 1 | Md. Rahim Uddin | Engine Specialist | 4 |
| 2 | Karim Hossain | Transmission & Gear | 4 |
| 3 | Jahangir Alam | Electrical Systems | 4 |
| 4 | Mamunur Rashid | Brake & Suspension | 4 |
| 5 | Shahidul Islam | AC & Cooling Systems | 4 |

### Seed Data
- 5 Mechanics with specializations
- 1 Admin (username: admin, password: admin123)
- 4 Sample appointments (for testing)

---

## 🚀 Running The Server

### Start PHP Server (Already Running)
```bash
cd /Users/istiakahmed/ass03_391
php -S localhost:8080 router.php
```

### Restart MySQL (If Needed)
```bash
brew services restart mysql
```

### Verify Database Setup
```bash
php /Users/istiakahmed/ass03_391/api/init.php?action=status
```

---

## 🔍 Helper Functions Used

### `validatePhoneSum($phone)`
Checks if last 3 digits sum to 21
```php
validatePhoneSum('01711111696')  // 6+9+6=21 → true ✓
validatePhoneSum('01711111111')  // 1+1+1=3  → false ✗
```

### `validatePhone($phone)`
Validates phone contains only digits

### `validateDate($date)`
Validates date is in future and correct format

### `sanitize($input)`
Prevents XSS attacks by escaping HTML

---

## ⚠️ Important Notes

1. **Phone Validation Rule**: Sum of LAST 3 DIGITS must equal 21
   - Example: `01711111696` → digits are 6, 9, 6 → sum = 21 ✓

2. **Admin Auth Header**: Use `X-Admin: true` header for admin endpoints
   - Automatically handled by frontend

3. **Waiting List Status Values**: 
   - `waiting` - Pending confirmation
   - `confirmed` - Moved to appointments
   - `cancelled` - Manually cancelled
   - `expired` - Appointment date passed

4. **Response Status Codes**:
   - `200` - Success (GET requests)
   - `201` - Created (POST successful)
   - `202` - Accepted (Waiting list)
   - `400` - Bad request
   - `401` - Unauthorized
   - `404` - Not found
   - `409` - Conflict (no slots)

---

## 🎓 Example API Usage

### Using cURL to Test
```bash
# Test Booking (Waiting List)
curl -X POST http://localhost:8080/api/appointments.php?action=book \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "Test User",
    "client_phone": "01711111696",
    "client_address": "Test Address",
    "car_license_number": "Test-123",
    "car_engine_number": "ENG123",
    "mechanic_id": 1,
    "appointment_date": "2026-05-20"
  }'

# Test Admin Login
curl -X POST http://localhost:8080/api/admin.php?action=login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }'
```

---

## 📞 Support

**Files to Check**:
- `/api/admin.php` - Admin endpoints
- `/api/appointments.php` - Booking endpoints
- `/api/helpers.php` - Helper functions (validatePhoneSum, etc.)
- `/database.sql` - Database schema
- `router.php` - URL routing
- `public/index.html` - Frontend UI

**Status**: ✅ All systems operational and tested!
