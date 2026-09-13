<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE api_keys ADD COLUMN rpm_limit INTEGER DEFAULT 60");
    echo "Column rpm_limit added.\n";
} catch (Exception $e) {
    echo "Error (might exist already): " . $e->getMessage() . "\n";
}
?>
