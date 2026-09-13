<?php
require 'db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");
    
    // Check if admin_password exists
    $st = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
    if(!$st->fetch()){
        // Insert default password
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_password', 'admin123')");
        echo "Settings table created and default password inserted.";
    } else {
        echo "Settings table already initialized.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
