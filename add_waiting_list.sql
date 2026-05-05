-- Add waiting_list table to existing database

USE car_workshop;

-- Drop if exists (for re-running the script)
DROP TABLE IF EXISTS waiting_list;

-- Create waiting_list table
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

    -- Foreign key to mechanics
    CONSTRAINT fk_waiting_mechanic
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    -- Index for admin lookup (waiting list)
    INDEX idx_waiting_status (status, appointment_date),
    INDEX idx_waiting_mechanic (mechanic_id, appointment_date),
    INDEX idx_waiting_phone (client_phone)
) ENGINE=InnoDB;

-- Verify table created
SELECT "✅ waiting_list table created successfully" as message;
SHOW CREATE TABLE waiting_list;
