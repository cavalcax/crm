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
        if (!in_array('created_at', $columns)) {
            $pdo->exec("ALTER TABLE " . TABLE_NAME . "clients ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
    } catch (Exception $e) {
        // Table may not exist yet or connection error during setup
    }
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

session_start();
?>