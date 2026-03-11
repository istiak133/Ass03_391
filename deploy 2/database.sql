-- ============================================================
--  CAR WORKSHOP ONLINE APPOINTMENT SYSTEM
--  DATABASE DESIGN (Updated)
-- ============================================================
--  3 Tables: mechanics, appointments, admins
--  No users table — clients identified by phone number
--  Same client can book multiple mechanics on same date
--  Admin can change mechanic max slots
-- ============================================================


-- -----------------------------------------
-- 1. NOTE FOR INFINITYFREE HOSTING:
-- -----------------------------------------
-- InfinityFree e database auto-create hoy cPanel theke.
-- DROP DATABASE / CREATE DATABASE command USE KORO NA.
-- Shudhu table create + seed data import korbe phpMyAdmin diye.
-- -----------------------------------------


-- -----------------------------------------
-- 2. TABLE: mechanics
-- -----------------------------------------
-- 5 jon fixed senior mechanic er info store kore.
-- max_daily_appointments default 4, but ADMIN change korte parbe.
-- is_active flag — future e kono mechanic inactive korte chaile.
-- -----------------------------------------
CREATE TABLE mechanics (
    id                      INT             AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100)    NOT NULL,
    phone                   VARCHAR(20)     DEFAULT NULL,
    specialization          VARCHAR(100)    DEFAULT NULL,
    max_daily_appointments  INT             NOT NULL DEFAULT 4,
    is_active               TINYINT(1)      NOT NULL DEFAULT 1,
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_mechanic_name (name)
) ENGINE=InnoDB;


-- -----------------------------------------
-- 3. TABLE: appointments
-- -----------------------------------------
-- Central table — shob feature eita read/write kore.
--
-- KEY DESIGN DECISIONS:
--
-- (a) UNIQUE KEY (client_phone, appointment_date, mechanic_id)
--     Same client + same date + same mechanic = BLOCKED (duplicate)
--     Same client + same date + DIFFERENT mechanic = ALLOWED
--     Eta database level e enforce hoy — PHP code e bug thakleo safe.
--
-- (b) NO user_id / NO users table
--     Client registration/login nai. Client ke phone number diye
--     identify kori. Phone number diyei client nijer appointments
--     dekhte/cancel korte parbe.
--
-- (c) status ENUM ('active', 'completed', 'cancelled')
--     Row delete kori na — status change kori (soft delete).
--     Shudhu 'active' appointments mechanic er daily limit e count hoy.
--     Cancelled/completed hole slot automatically free.
--
-- (d) mechanic_id → Foreign Key to mechanics(id)
--     ON UPDATE CASCADE: mechanic er id change hole appointment follow korbe.
--     ON DELETE RESTRICT: mechanic delete korte dibe na jodi tar appointment thake.
--
-- (e) created_at / updated_at
--     Kobe booking hoyeche, kobe last edit hoyeche — admin audit er jonno.
-- -----------------------------------------
CREATE TABLE appointments (
    id                  INT             AUTO_INCREMENT PRIMARY KEY,
    client_name         VARCHAR(100)    NOT NULL,
    client_phone        VARCHAR(20)     NOT NULL,
    client_address      VARCHAR(255)    NOT NULL,
    car_license_number  VARCHAR(50)     NOT NULL,
    car_engine_number   VARCHAR(50)     NOT NULL,
    appointment_date    DATE            NOT NULL,
    mechanic_id         INT             NOT NULL,
    status              ENUM('active', 'completed', 'cancelled')
                                        NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    -- DUPLICATE CHECK:
    -- Same car + same date + same mechanic = NOT ALLOWED (true duplicate)
    -- Different car + same date + same mechanic = ALLOWED (multiple cars)
    -- Same car + same date + different mechanic = ALLOWED
    UNIQUE KEY uk_phone_date_mechanic_car (client_phone, appointment_date, mechanic_id, car_license_number),

    -- Mechanic must exist in mechanics table
    CONSTRAINT fk_appointment_mechanic
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- -----------------------------------------
-- 4. INDEXES (Performance)
-- -----------------------------------------
-- idx_mechanic_date:
--   Mechanic er koto appointment ache specific date e — slot count
--
-- idx_appointment_date:
--   Date-wise appointment list (admin filter)
--
-- idx_client_phone:
--   Client phone diye appointment lookup + cancel
--
-- idx_status:
--   Status filter (active/completed/cancelled)
-- -----------------------------------------
CREATE INDEX idx_mechanic_date    ON appointments (mechanic_id, appointment_date, status);
CREATE INDEX idx_appointment_date ON appointments (appointment_date);
CREATE INDEX idx_client_phone     ON appointments (client_phone);
CREATE INDEX idx_status           ON appointments (status);


-- -----------------------------------------
-- 5. TABLE: admins
-- -----------------------------------------
-- Admin panel login er jonno.
-- Password HASHED store hoy (PHP password_hash diye).
-- Plain text password NEVER store koro na!
-- -----------------------------------------
CREATE TABLE admins (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    full_name   VARCHAR(100)    DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ============================================================
-- 6. SEED DATA
-- ============================================================

-- -----------------------------------------
-- 6a. 5 Senior Mechanics (Fixed)
-- -----------------------------------------
INSERT INTO mechanics (name, phone, specialization, max_daily_appointments) VALUES
    ('Md. Rahim Uddin',    '01711000001', 'Engine Specialist',        4),
    ('Karim Hossain',      '01711000002', 'Transmission & Gear',      4),
    ('Jahangir Alam',      '01711000003', 'Electrical Systems',       4),
    ('Mamunur Rashid',     '01711000004', 'Brake & Suspension',       4),
    ('Shahidul Islam',     '01711000005', 'AC & Cooling Systems',     4);

-- -----------------------------------------
-- 6b. Default Admin
-- -----------------------------------------
-- Password: admin123
-- Hash generate: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- Niche dewa hash ta 'admin123' er bcrypt hash:
INSERT INTO admins (username, password, full_name) VALUES
    ('admin', '$2y$12$6ns7fDeA6UleeXtx6K0rZ.Er3pXL.ZYu/SemkBTME/4JC84nlZ5/i', 'Workshop Manager');


-- ============================================================
-- 7. REFERENCE QUERIES (PHP development er somoy use korbo)
-- ============================================================

-- -----------------------------------------
-- Q1: Specific date e prottek mechanic er free slot count
-- -----------------------------------------
-- SELECT
--     m.id,
--     m.name,
--     m.specialization,
--     m.max_daily_appointments,
--     m.max_daily_appointments - COALESCE(booked.cnt, 0) AS free_slots
-- FROM mechanics m
-- LEFT JOIN (
--     SELECT mechanic_id, COUNT(*) AS cnt
--     FROM appointments
--     WHERE appointment_date = :selected_date
--       AND status = 'active'
--     GROUP BY mechanic_id
-- ) AS booked ON m.id = booked.mechanic_id
-- WHERE m.is_active = 1
-- ORDER BY m.name;


-- -----------------------------------------
-- Q2: Specific mechanic er specific date e slot info
--     (mechanic button click korle ei query run hobe)
-- -----------------------------------------
-- SELECT
--     m.max_daily_appointments,
--     COUNT(a.id) AS booked,
--     m.max_daily_appointments - COUNT(a.id) AS free_slots
-- FROM mechanics m
-- LEFT JOIN appointments a
--     ON m.id = a.mechanic_id
--     AND a.appointment_date = :date
--     AND a.status = 'active'
-- WHERE m.id = :mechanic_id
-- GROUP BY m.id;


-- -----------------------------------------
-- Q3: Duplicate check — same client + same date + same mechanic + same car?
-- -----------------------------------------
-- SELECT COUNT(*) AS existing
-- FROM appointments
-- WHERE client_phone = :phone
--   AND appointment_date = :date
--   AND mechanic_id = :mechanic_id
--   AND car_license_number = :car_license
--   AND status = 'active';


-- -----------------------------------------
-- Q4: Mechanic full check — oi date e koto appointment ache?
-- -----------------------------------------
-- SELECT COUNT(*) AS booked
-- FROM appointments
-- WHERE mechanic_id = :mechanic_id
--   AND appointment_date = :date
--   AND status = 'active';
-- -- Compare with mechanics.max_daily_appointments


-- -----------------------------------------
-- Q5: Insert new appointment
-- -----------------------------------------
-- INSERT INTO appointments
--     (client_name, client_phone, client_address,
--      car_license_number, car_engine_number,
--      appointment_date, mechanic_id)
-- VALUES
--     (:name, :phone, :address,
--      :car_license, :car_engine,
--      :date, :mechanic_id);


-- -----------------------------------------
-- Q6: Client er shob appointments (phone diye lookup)
-- -----------------------------------------
-- SELECT a.*, m.name AS mechanic_name, m.specialization
-- FROM appointments a
-- JOIN mechanics m ON a.mechanic_id = m.id
-- WHERE a.client_phone = :phone
-- ORDER BY a.appointment_date DESC;


-- -----------------------------------------
-- Q7: Admin — shob appointments with mechanic name
-- -----------------------------------------
-- SELECT
--     a.id, a.client_name, a.client_phone,
--     a.car_license_number, a.appointment_date,
--     m.name AS mechanic_name,
--     a.status, a.created_at
-- FROM appointments a
-- JOIN mechanics m ON a.mechanic_id = m.id
-- ORDER BY a.appointment_date DESC, a.created_at DESC;


-- -----------------------------------------
-- Q8: Admin — appointment date update
-- -----------------------------------------
-- UPDATE appointments
-- SET appointment_date = :new_date
-- WHERE id = :appointment_id;
-- (Aage Q3 + Q4 run kore validate korbo notun date er jonno)


-- -----------------------------------------
-- Q9: Admin — mechanic change
-- -----------------------------------------
-- UPDATE appointments
-- SET mechanic_id = :new_mechanic_id
-- WHERE id = :appointment_id;
-- (Aage Q4 run kore notun mechanic er slot check korbo)


-- -----------------------------------------
-- Q10: Admin — mechanic er max slot update
-- -----------------------------------------
-- UPDATE mechanics
-- SET max_daily_appointments = :new_max
-- WHERE id = :mechanic_id;


-- -----------------------------------------
-- Q11: Next available date for a specific mechanic
--      (Jodi full thake, next kon date e free ache)
-- -----------------------------------------
-- Loop through next 30 days from :start_date
-- For each date, run Q4
-- First date where booked < max_daily_appointments = answer


-- -----------------------------------------
-- Q12: Cancel appointment (soft delete)
-- -----------------------------------------
-- UPDATE appointments
-- SET status = 'cancelled'
-- WHERE id = :appointment_id
--   AND client_phone = :phone
--   AND status = 'active';


-- ============================================================
-- END OF DATABASE SCHEMA
-- ============================================================
