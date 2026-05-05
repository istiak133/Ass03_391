# 🧪 Car Workshop API Testing Guide

## Quick Test Scenarios

### Scenario 1: Normal Booking (With Available Slots)

**Test Phone**: `01799999111` (1+1+1=3, not eligible)

**Step 1: Check Slots**
```bash
curl http://localhost:8080/api/mechanics.php?action=slots&mechanic_id=1&date=2026-05-15
```

**Step 2: Book Appointment**
```bash
curl -X POST http://localhost:8080/api/appointments.php?action=book \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "Ahmed Khan",
    "client_phone": "01799999111",
    "client_address": "Mirpur, Dhaka",
    "car_license_number": "Dhaka-12-3456",
    "car_engine_number": "ENG0001",
    "mechanic_id": 1,
    "appointment_date": "2026-05-15"
  }'
```

**Expected Response** (201 Created):
```json
{
  "success": true,
  "message": "Appointment booked successfully!",
  "data": {
    "appointment_id": 123,
    "client_name": "Ahmed Khan",
    "mechanic_name": "Md. Rahim Uddin",
    "appointment_date": "2026-05-15",
    "slots_remaining": 2
  }
}
```

---

### Scenario 2: Booking Into Waiting List (All Slots Full)

**Prerequisites**: 4 appointments already booked for mechanic_id=2 on 2026-05-20

**Test Phone**: `01711111696` (6+9+6=21, ELIGIBLE for confirmation!)

**Step 1: Try to Book When Full**
```bash
curl -X POST http://localhost:8080/api/appointments.php?action=book \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "Md. Rahim",
    "client_phone": "01711111696",
    "client_address": "Dhaka City",
    "car_license_number": "Dhaka-99-9999",
    "car_engine_number": "ENG9999",
    "mechanic_id": 2,
    "appointment_date": "2026-05-20"
  }'
```

**Expected Response** (202 Accepted - WAITING LIST):
```json
{
  "success": true,
  "status": "WAITING_LIST",
  "message": "All slots are currently booked! You have been added to the waiting list.",
  "data": {
    "waiting_id": 5,
    "client_name": "Md. Rahim",
    "mechanic_name": "Karim Hossain",
    "appointment_date": "2026-05-20",
    "notification": "You are in the waiting zone. If another customer cancels their booking, admin can confirm your appointment.",
    "phone_requirement": "Your phone number's last 3 digits must sum to 21 for confirmation eligibility."
  }
}
```

---

### Scenario 3: Admin Login

**Step 1: Login**
```bash
curl -X POST http://localhost:8080/api/admin.php?action=login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin_id": 1,
    "username": "admin",
    "auth_token": "MToxNjY3MzAwODQ5"
  }
}
```

---

### Scenario 4: View Waiting List (Admin)

**Step 1: Get All Waiting Customers**
```bash
curl "http://localhost:8080/api/admin.php?action=waiting_list" \
  -H "X-Admin: true"
```

**Step 2: Filter by Mechanic & Date**
```bash
curl "http://localhost:8080/api/admin.php?action=waiting_list?mechanic_id=2&date=2026-05-20&status=waiting" \
  -H "X-Admin: true"
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "client_name": "Md. Rahim",
      "client_phone": "01711111696",
      "client_address": "Dhaka City",
      "car_license_number": "Dhaka-99-9999",
      "car_engine_number": "ENG9999",
      "appointment_date": "2026-05-20",
      "status": "waiting",
      "created_at": "2026-05-05 15:30:45",
      "mechanic_id": 2,
      "mechanic_name": "Karim Hossain",
      "mechanic_specialization": "Transmission & Gear",
      "eligible_for_confirmation": true,
      "phone_last_3_digits": "696",
      "phone_digit_sum": 21
    }
  ],
  "count": 1,
  "note": "Only customers with last 3 phone digits sum = 21 can be confirmed"
}
```

---

### Scenario 5: Admin Confirms from Waiting (Eligible Customer)

**Prerequisites**: 
- A waiting customer with eligible phone (sum=21)
- At least 1 slot now available

**Step 1: Confirm from Waiting**
```bash
curl -X POST http://localhost:8080/api/admin.php?action=confirm_waiting \
  -H "Content-Type: application/json" \
  -H "X-Admin: true" \
  -d '{
    "waiting_id": 5
  }'
```

**Expected Response** (201 Created):
```json
{
  "success": true,
  "message": "Customer confirmed and appointment created!",
  "data": {
    "waiting_id": 5,
    "appointment_id": 124,
    "client_name": "Md. Rahim",
    "client_phone": "01711111696",
    "mechanic_id": 2,
    "appointment_date": "2026-05-20"
  }
}
```

---

### Scenario 6: Admin Tries to Confirm Non-Eligible Phone

**Test Phone**: `01711111111` (1+1+1=3, NOT eligible)

**Step 1: Waiting List Entry Exists**
Assume waiting_id=6 has phone `01711111111`

**Step 2: Try to Confirm**
```bash
curl -X POST http://localhost:8080/api/admin.php?action=confirm_waiting \
  -H "Content-Type: application/json" \
  -H "X-Admin: true" \
  -d '{
    "waiting_id": 6
  }'
```

**Expected Response** (400 Bad Request):
```json
{
  "success": false,
  "error": "Phone number does not meet eligibility criteria",
  "details": "Last 3 digits: 111, Sum: 3. Required: 21"
}
```

---

### Scenario 7: Admin Cancels Waiting Entry

**Step 1: Cancel from Waiting**
```bash
curl -X POST http://localhost:8080/api/admin.php?action=cancel_waiting \
  -H "Content-Type: application/json" \
  -H "X-Admin: true" \
  -d '{
    "waiting_id": 5
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Waiting list entry cancelled",
  "data": {
    "waiting_id": 5,
    "client_name": "Md. Rahim"
  }
}
```

---

## 🔍 Phone Number Validation Reference

### Valid Phone Numbers for Confirmation (Sum = 21)

| Phone | Last 3 Digits | Calculation | Sum | Eligible? |
|-------|---------------|-------------|-----|-----------|
| 01711111696 | 696 | 6+9+6 | 21 | ✅ YES |
| 01799999555 | 555 | 5+5+5 | 15 | ❌ NO |
| 01712349999 | 999 | 9+9+9 | 27 | ❌ NO |
| 01700000789 | 789 | 7+8+9 | 24 | ❌ NO |
| 01700000678 | 678 | 6+7+8 | 21 | ✅ YES |
| 01711111159 | 159 | 1+5+9 | 15 | ❌ NO |

### How to Calculate Valid Phone Numbers

Need: Last 3 digits sum to 21

**Formula**: Last digit 1 + Last digit 2 + Last digit 3 = 21

**Examples**:
- 696: 6+9+6 = 21 ✓
- 678: 6+7+8 = 21 ✓
- 777: 7+7+7 = 21 ✓
- 789: 7+8+9 = 24 ✗
- 699: 6+9+9 = 24 ✗

**Quick Generator**:
```
Valid combinations:
9+9+3, 9+8+4, 9+7+5, 9+6+6
8+8+5, 8+7+6
7+7+7
... (many more)
```

---

## 🚨 Error Responses

### Error 1: Phone Invalid
```json
{
  "success": false,
  "error": "Phone number must contain only digits",
  "status": 400
}
```

### Error 2: Date in Past
```json
{
  "success": false,
  "error": "Date cannot be in the past",
  "status": 400
}
```

### Error 3: Duplicate Booking
```json
{
  "success": false,
  "error": "You already have an active appointment with this mechanic on this date for the same car.",
  "status": 409
}
```

### Error 4: No Admin Auth
```json
{
  "success": false,
  "error": "Admin authentication required. Please login first.",
  "status": 401
}
```

### Error 5: No Slots Available (Before Waiting List)
```json
{
  "success": false,
  "error": "Karim Hossain has no available slots on this date. All 4 slots are booked.",
  "status": 409
}
```

---

## 📊 SQL Queries for Testing

### Check Waiting List
```sql
SELECT id, client_name, client_phone, mechanic_id, status 
FROM waiting_list 
ORDER BY created_at DESC;
```

### Check Appointments
```sql
SELECT a.id, a.client_name, a.client_phone, m.name, a.appointment_date, a.status
FROM appointments a
JOIN mechanics m ON a.mechanic_id = m.id
ORDER BY a.appointment_date DESC;
```

### Count Slots for Mechanic on Date
```sql
SELECT COUNT(*) as booked FROM appointments 
WHERE mechanic_id=2 AND appointment_date='2026-05-20' AND status='active';
```

### Check Eligible Waiting Customers (Phone Sum = 21)
```sql
SELECT * FROM waiting_list 
WHERE status='waiting' 
  AND (
    CAST(SUBSTRING(client_phone, -3, 1) AS UNSIGNED) +
    CAST(SUBSTRING(client_phone, -2, 1) AS UNSIGNED) +
    CAST(SUBSTRING(client_phone, -1, 1) AS UNSIGNED) = 21
  );
```

---

## 🎯 Testing Checklist

- [ ] Create normal booking with available slots → 201
- [ ] Create booking when full → 202 (WAITING_LIST)
- [ ] Admin login with correct credentials → Success
- [ ] Admin login with wrong password → 401
- [ ] View waiting list (no filter) → All customers
- [ ] View waiting list (by mechanic) → Filtered
- [ ] Confirm eligible customer → 201 (moved to appointments)
- [ ] Try confirm non-eligible → 400 (error)
- [ ] Cancel waiting entry → Success
- [ ] Phone validation (non-digits) → Error
- [ ] Date in past → Error
- [ ] Duplicate booking → Error

---

## 💡 Pro Tips

1. **Test with Valid Phone**: Use `01711111696` for easy testing (6+9+6=21)

2. **Generate Eligible Phones**: Combine digits that sum to 21
   - `017` + `111` + `696` = `01711111696` ✓
   - `017` + `999` + `123` = No, 1+2+3=6 ✗

3. **Fill 4 Slots**: Book 4 customers first to test waiting list

4. **Use Postman/Insomnia**: For easier API testing
   - Import endpoints
   - Save auth header
   - Create test scenarios

5. **Check Database Directly**: Verify data was inserted correctly

---

## 🔗 Related Files

- `README.md` - Full project documentation
- `api/admin.php` - Admin endpoints
- `api/appointments.php` - Booking endpoints
- `api/helpers.php` - Utility functions
- `database.sql` - Database schema
