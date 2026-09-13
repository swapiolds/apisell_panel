<?php
require 'db.php';
$pdo->exec("INSERT INTO users (name, wallet_balance, is_vip) VALUES ('test', 100, 1)");
$uid = $pdo->lastInsertId();
$pdo->exec("INSERT INTO api_keys (key_text, service_type, user_id) VALUES ('test_key', 'number,vehicle,lpg', $uid)");
echo 'Key created: test_key';
