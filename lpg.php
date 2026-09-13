<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';

$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

if($key === ''){
    echo json_encode([
        'error' => 'Missing API key',
        'usage' => '?key=YOUR_KEY&num=9686677773',
        'BUY_API' => '@swapibhai',
        'SUPPORT' => '@swapibhai'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$type = 'lpg';

// Fetch active key
$st = $pdo->prepare('SELECT * FROM api_keys WHERE key_text = :k AND active = 1');
$st->execute([':k'=>$key]);
$keyRow = $st->fetch(PDO::FETCH_ASSOC);

if(!$keyRow){
    echo json_encode([
        'error' => 'Invalid or inactive API key',
        'BUY_API' => '@swapibhai',
        'SUPPORT' => '@swapibhai'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed_services = array_map('trim', explode(',', $keyRow['service_type']));
if(!in_array($type, $allowed_services)){
    echo json_encode([
        'error' => 'Key is not authorized for LPG Info API',
        'BUY_API' => '@swapibhai',
        'SUPPORT' => '@swapibhai'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Check Expiry Date
if(!empty($keyRow['expiry_date']) && strtotime($keyRow['expiry_date']) < time()){
    echo json_encode([
        'error' => 'API key has expired. Please contact support.',
        'BUY_API' => '@swapibhai',
        'SUPPORT' => '@swapibhai'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Check Daily Limit
if($keyRow['daily_limit'] > 0 && $keyRow['used_today'] >= $keyRow['daily_limit']){
    echo json_encode([
        'error' => 'Daily limit exceeded. Please wait until tomorrow or contact support.',
        'limit' => $keyRow['daily_limit'],
        'used' => $keyRow['used_today'],
        'BUY_API' => '@swapibhai',
        'SUPPORT' => '@swapibhai'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Check RPM limit
if(isset($keyRow['rpm_limit']) && $keyRow['rpm_limit'] > 0){
    $st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE api_key_id = ? AND used_at >= datetime('now', '-1 minute')");
    $st->execute([$keyRow['id']]);
    $rpmUsed = (int)$st->fetchColumn();
    if($rpmUsed >= $keyRow['rpm_limit']){
        echo json_encode([
            'error' => 'Rate limit exceeded. Please wait 60 seconds.',
            'BUY_API' => '@swapibhai',
            'SUPPORT' => '@swapibhai'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Validate input
$num = isset($_GET['num']) ? trim($_GET['num']) : '';
if($num === ''){
    echo json_encode(['error' => 'Missing num parameter', 'usage' => '?key=YOUR_KEY&num=9686677773'], JSON_PRETTY_PRINT);
    exit;
}
$phone = preg_replace('/[^0-9]/','',$num);
if(strlen($phone) != 10){
    echo json_encode(['error' => 'Invalid phone number! Need 10 digits.'], JSON_PRETTY_PRINT);
    exit;
}
$query = $phone;

// ========== HELPER ==========
function call_api($url, $debug = false){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if($debug) return ['debug' => ['url' => $url, 'http_code' => $httpCode, 'curl_error' => $error, 'raw_response' => $response]];
    if($error) return ['error' => 'CURL Error: ' . $error];
    if($httpCode != 200) return ['error' => 'HTTP Error: ' . $httpCode];
    
    $decoded = json_decode($response, true);
    if(json_last_error() !== JSON_ERROR_NONE) return ['error' => 'JSON Parse Error: ' . json_last_error_msg()];
    return $decoded;
}

$url = 'https://digisevapoint.com/abhigyan.php/fetch_gas_details.php?number=' . urlencode($phone) . '&token=abhi';
$data = call_api($url, $debug);

if($debug){
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Analyze API response to see if ANY gas provider found a result
$failed = true;
$result = null;

if (isset($data['found']) && $data['found'] === true) {
    $failed = false;
    $result = $data;
} else if (is_array($data)) {
    foreach ($data as $key_index => $provider_response) {
        if (isset($provider_response['found']) && $provider_response['found'] === true) {
            $failed = false;
            $result = $provider_response;
            break;
        }
    }
}

if($failed){
    echo json_encode(['error' => 'LPG data not found for this number', 'status' => 'failed', 'BUY_API' => '@swapibhai', 'SUPPORT' => '@swapibhai'], JSON_PRETTY_PRINT);
    exit;
}

// Increment Usage since API call succeeded
$pdo->prepare("UPDATE api_keys SET used_today = used_today + 1, total_used = total_used + 1 WHERE id = ?")->execute([$keyRow['id']]);

$output = [
    'username' => $keyRow['key_text'],
    'type' => 'lpg',
    'limit_remaining' => $keyRow['daily_limit'] > 0 ? ($keyRow['daily_limit'] - $keyRow['used_today'] - 1) : 'Unlimited',
    'data' => $result,
    'BUY_API' => '@swapibhai',
    'SUPPORT' => '@swapibhai'
];

// Usage Log
$st = $pdo->prepare("INSERT INTO usage_logs (api_key_id, query, service_type) VALUES (:id, :query, :service)");
$st->execute([':id' => $keyRow['id'], ':query' => $query, ':service' => $type]);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
