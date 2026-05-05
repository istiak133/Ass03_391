<?php
require_once __DIR__ . '/api/config.php';

try {
    echo "Creating waiting_list table...\n";
    
    // Create table directly
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS waiting_list (
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
    ";
    
    $pdo->exec($createTableSQL);
    echo "✅ waiting_list table created!\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "✅ waiting_list table already exists!\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>
