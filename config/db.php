<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ctcrm');
define('TABLE_NAME', 'ctcrm_vitor_');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto-migration: ensure required columns exist on clients table
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM " . TABLE_NAME . "clients")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('uf', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN uf VARCHAR(2) NULL");
        }
        if (!in_array('city', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN city VARCHAR(100) NULL");
        }
        if (!in_array('status', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN status VARCHAR(50) DEFAULT 'Ativo'");
        }
        if (!in_array('is_potential', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN is_potential TINYINT(1) DEFAULT 0");
        }
        if (!in_array('payment_condition', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN payment_condition VARCHAR(100) NULL");
        }
        if (!in_array('breed_interests', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN breed_interests VARCHAR(255) NULL");
        }
        if (!in_array('is_milk_producer', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN is_milk_producer VARCHAR(10) NULL");
        }
        if (!in_array('acquisition_reason', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN acquisition_reason VARCHAR(150) NULL");
        }
        if (!in_array('animal_count_range', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN animal_count_range VARCHAR(100) NULL");
        }
        if (!in_array('milk_production_range', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN milk_production_range VARCHAR(100) NULL");
        }
        if (!in_array('farm_name', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN farm_name VARCHAR(255) NULL");
        }
        if (!in_array('purchase_animal_count', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN purchase_animal_count VARCHAR(100) NULL");
        }
        if (!in_array('animal_categories', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN animal_categories TEXT NULL");
        }
        if (!in_array('production_system', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN production_system VARCHAR(255) NULL");
        }

        // Auto-migration: ensure notifications_enabled column exists on users table
        try {
            $userColumns = $pdo->query("SHOW COLUMNS FROM " . TABLE_NAME . "users")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('notifications_enabled', $userColumns)) {
                $pdo->exec("ALTER TABLE " . TABLE_NAME . "users ADD COLUMN notifications_enabled TINYINT(1) DEFAULT 1");
            }
        } catch (Exception $e) {}

        // Auto-migration: ensure notifications table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . TABLE_NAME . "notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            schedule_id INT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'meeting_reminder',
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME NULL,
            link VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_schedule (schedule_id),
            UNIQUE KEY uk_user_schedule_type (user_id, schedule_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Auto-migration: ensure push_subscriptions table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . TABLE_NAME . "push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash VARCHAR(64) NOT NULL,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            UNIQUE KEY uk_user_endpoint (user_id, endpoint_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Auto-migration: ensure interactions table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . TABLE_NAME . "interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            user_id INT NOT NULL,
            interaction_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client (client_id),
            INDEX idx_user (user_id),
            INDEX idx_date (interaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table may not exist yet or connection error during setup
    }
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>