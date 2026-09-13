<?php
require 'db.php';
// Create test key
$pdo->exec("DELETE FROM api_keys WHERE key_text = 'TEST_LIMIT_KEY'");
$pdo->exec("INSERT INTO api_keys (key_text, service_type, daily_limit, active) VALUES ('TEST_LIMIT_KEY', 'number,vehicle,lpg', 1, 1)");

echo "Test 1: First hit (should succeed and increment)\n";
$res1 = file_get_contents("http://localhost:8000/num.php?key=TEST_LIMIT_KEY&num=9876543210");
echo $res1 . "\n\n";

echo "Test 2: Second hit (should fail due to limit)\n";
$res2 = file_get_contents("http://localhost:8000/num.php?key=TEST_LIMIT_KEY&num=9876543210");
echo $res2 . "\n";
?>
