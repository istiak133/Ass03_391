-- ============================================================
--  CAR WORKSHOP — InfinityFree Import File (Clean)
--  Shudhu table create + seed data — kono DROP/CREATE DATABASE nai
-- ============================================================


-- TABLE: mechanics
CREATE TABLE IF NOT EXISTS mechanics (
    id                      INT             AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100)    NOT NULL,
    phone                   VARCHAR(20)     DEFAULT NULL,
    specialization          VARCHAR(100)    DEFAULT NULL,
    max_daily_appointments  INT             NOT NULL DEFAULT 4,
    is_active               TINYINT(1)      NOT NULL DEFAULT 1,
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_mechanic_name (name)
) ENGINE=InnoDB;


-- TABLE: appointments
CREATE TABLE IF NOT EXISTS appointments (
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
    UNIQUE KEY uk_phone_date_mechanic_car (client_phone, appointment_date, mechanic_id, car_license_number),
    CONSTRAINT fk_appointment_mechanic
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- INDEXES
CREATE INDEX idx_mechanic_date    ON appointments (mechanic_id, appointment_date, status);
CREATE INDEX idx_appointment_date ON appointments (appointment_date);
CREATE INDEX idx_client_phone     ON appointments (client_phone);
CREATE INDEX idx_status           ON appointments (status);


-- TABLE: admins
CREATE TABLE IF NOT EXISTS admins (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    full_name   VARCHAR(100)    DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- SEED DATA: 5 Mechanics
INSERT INTO mechanics (name, phone, specialization, max_daily_appointments) VALUES
    ('Md. Rahim Uddin',    '01711000001', 'Engine Specialist',        4),
    ('Karim Hossain',      '01711000002', 'Transmission & Gear',      4),
    ('Jahangir Alam',      '01711000003', 'Electrical Systems',       4),
    ('Mamunur Rashid',     '01711000004', 'Brake & Suspension',       4),
    ('Shahidul Islam',     '01711000005', 'AC & Cooling Systems',     4);


-- SEED DATA: Default Admin (password: admin123)
INSERT INTO admins (username, password, full_name) VALUES
    ('admin', '$2y$12$6ns7fDeA6UleeXtx6K0rZ.Er3pXL.ZYu/SemkBTME/4JC84nlZ5/i', 'Workshop Manager');
