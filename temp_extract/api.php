<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');

$pdo = new PDO('sqlite:'.__DIR__.'/osint_api.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

if($key === ''){
    echo json_encode([
        'error' => 'Missing API key',
        'usage' => '?key=YOUR_KEY&num=9876543210',
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Sirf 'number' service ke liye key validate karo
$st = $pdo->prepare('SELECT * FROM api_keys WHERE key_text = :k AND service_type = :t AND active = 1');
$st->execute([':k'=>$key, ':t'=>'number']);
$keyRow = $st->fetch(PDO::FETCH_ASSOC);

if(!$keyRow){
    echo json_encode([
        'error' => 'Invalid API key',
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if(!empty($keyRow['expiry_date']) && strtotime($keyRow['expiry_date']) < time()){
    echo json_encode([
        'error' => 'API Key expired on ' . date('d-m-Y', strtotime($keyRow['expiry_date'])),
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE api_key_id = ? AND date(used_at) = date('now')");
$st->execute([$keyRow['id']]);
$usedToday = (int)$st->fetchColumn();

if($keyRow['daily_limit'] > 0 && $usedToday >= $keyRow['daily_limit']){
    echo json_encode([
        'error' => 'Daily limit of ' . $keyRow['daily_limit'] . ' reached',
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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

    if($debug){
        return [
            'debug' => [
                'url' => $url,
                'http_code' => $httpCode,
                'curl_error' => $error,
                'raw_response' => $response
            ]
        ];
    }

    if($error) return ['error' => 'CURL Error: ' . $error];
    if($httpCode != 200) return ['error' => 'HTTP Error: ' . $httpCode];

    $decoded = json_decode($response, true);
    if(json_last_error() !== JSON_ERROR_NONE) return ['error' => 'JSON Parse Error: ' . json_last_error_msg()];

    return $decoded;
}

// ========== NUMBER INFO ==========
$num = isset($_GET['num']) ? trim($_GET['num']) : '';
if($num === ''){
    echo json_encode([
        'error' => 'Missing num parameter',
        'usage' => '?key=YOUR_KEY&num=9876543210',
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$phone = preg_replace('/[^0-9]/','',$num);
if(strlen($phone) != 10){
    echo json_encode([
        'error' => 'Invalid phone number! Need 10 digits.',
        'BUY_API' => '@Vectraen',
        'SUPPORT' => '@Vectraen'
    ], JSON_PRETTY_PRINT);
    exit;
}

$query = $phone;

// ✅ NAYA API ENDPOINT
$url = 'https://anishexploits.com/api/number.php?exploits=' . urlencode($phone);

$data = call_api($url, $debug);

if($debug){
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if(isset($data['error'])){
    echo json_encode(['error' => $data['error'], 'BUY_API' => '@Vectraen', 'SUPPORT' => '@Vectraen'], JSON_PRETTY_PRINT);
    exit;
}

// Response handle — har format support
if($data && isset($data['data'])) {
    $result = $data['data'];
} elseif($data && isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
    $result = $data['data'];
} elseif($data && isset($data['success']) && $data['success'] === true && isset($data['data'])) {
    $result = $data['data'];
} else {
    $result = $data;
}

// ========== USAGE LOG ==========
$st = $pdo->prepare("INSERT INTO usage_logs (api_key_id, query) VALUES (:id, :query)");
$st->execute([':id' => $keyRow['id'], ':query' => $query]);

// ========== OUTPUT ==========
$output = [
    'username' => $keyRow['key_text'],
    'type' => 'number',
    'data' => $result,
    'BUY_API' => '@Vectraen',
    'SUPPORT' => '@Vectraen'
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>