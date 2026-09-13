<?php
require_once __DIR__ . '/db.php';
session_start();

// --- Authentication ---
if(isset($_POST['admin_login'])){
    $st = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
    $db_pass = $st->fetchColumn();
    if(!$db_pass) $db_pass = 'admin123';
    
    if($_POST['password'] === $db_pass){
        $_SESSION['admin_logged_in'] = true;
        header("Location: swapiadmin.php");
        exit;
    } else {
        $loginErr = "Invalid Password";
    }
}
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: swapiadmin.php");
    exit;
}
if(empty($_SESSION['admin_logged_in'])):
?>
<!DOCTYPE html>
<html>
<head><title>Admin Login</title><style>body{background:#f0f4f8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;} .box{background:#fff;padding:40px;border-radius:10px;box-shadow:0 10px 20px rgba(0,0,0,0.1);text-align:center;} input{padding:10px;margin-bottom:20px;width:100%;} button{background:#4f46e5;color:white;padding:10px;border:none;width:100%;cursor:pointer;border-radius:5px;} </style></head>
<body><div class="box"><h2>Nexus Admin</h2><p style="color:red;"><?= $loginErr ?? '' ?></p><form method="post"><input type="password" name="password" placeholder="Admin Password (admin123)"><button name="admin_login">Login</button></form></div></body>
</html>
<?php exit; endif; ?>
<?php
// --- Handlers ---
$msg = '';

// Create API Key
if(isset($_POST['create_key'])){
    $client_name = trim($_POST['client_name']);
    $key_text = trim($_POST['key_text']);
    $services = isset($_POST['services']) ? implode(',', $_POST['services']) : 'number';
    $daily_limit = (int)$_POST['daily_limit'];
    $rpm_limit = (int)$_POST['rpm_limit'];
    $expiry_date = trim($_POST['expiry_date']);
    
    $st = $pdo->prepare('INSERT INTO api_keys (client_name, key_text, service_type, daily_limit, rpm_limit, expiry_date, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    try {
        $st->execute([$client_name, $key_text, $services, $daily_limit, $rpm_limit, $expiry_date]);
        $msg = "API Key generated securely for ".$client_name."!";
    } catch(Exception $e) {
        $msg = "Error generating API Key (Key might already exist).";
    }
}

// Toggle API Key
if(isset($_POST['toggle_key'])){
    $id = (int)$_POST['key_id'];
    $st = $pdo->prepare('SELECT active FROM api_keys WHERE id = ?'); $st->execute([$id]);
    $r = $st->fetch();
    if($r){
        $new = $r['active'] ? 0 : 1;
        $pdo->prepare('UPDATE api_keys SET active = ? WHERE id = ?')->execute([$new, $id]);
        $msg = "API Key status changed!";
    }
}

// Delete API Key
if(isset($_POST['delete_key'])){
    $id = (int)$_POST['key_id'];
    $pdo->prepare('DELETE FROM api_keys WHERE id = ?')->execute([$id]);
    $msg = "API Key permanently deleted!";
}

// Clear Logs
if(isset($_POST['clear_logs'])){
    $pdo->exec("DELETE FROM usage_logs");
    $msg = "All traffic logs cleared successfully!";
}

// Traffic AJAX
if(isset($_GET['ajax']) && $_GET['ajax'] == 'traffic'){
    $logs = $pdo->query("SELECT u.id, u.query, u.used_at, u.service_type, k.key_text, k.client_name FROM usage_logs u LEFT JOIN api_keys k ON u.api_key_id = k.id ORDER BY u.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    foreach($logs as $log){
        echo '<tr>';
        echo '<td style="background:#1e293b; border-color:#1e293b;"><div style="font-weight: 600; font-size: 14px; color: #94a3b8;"><i class="far fa-clock" style="margin-right:6px;"></i> ' . date('H:i:s.v', strtotime($log['used_at'])) . '</div></td>';
        echo '<td style="background:#1e293b; border-color:#1e293b;"><div style="font-weight: 700; font-size: 15px; color:#e2e8f0;">' . htmlspecialchars($log['client_name'] ?? 'Unknown User') . '</div></td>';
        echo '<td style="background:#1e293b; border-color:#1e293b;"><span class="badge badge-gray" style="background:#0f172a; color:#818cf8; border:1px solid #312e81; text-transform:uppercase; letter-spacing:1px;"><i class="fas fa-microchip" style="font-size:12px;"></i> ' . htmlspecialchars($log['service_type'] ?: 'API') . '</span></td>';
        echo '<td style="background:#1e293b; border-color:#1e293b;"><code style="background: #000; padding: 6px 12px; border-radius: 8px; font-family: monospace; color: #10b981; font-weight: bold; font-size: 14px; border:1px solid #064e3b; box-shadow: 0 0 10px rgba(16,185,129,0.2);">' . htmlspecialchars($log['query']) . '</code></td>';
        echo '<td style="background:#1e293b; border-color:#1e293b;"><span class="badge badge-success" style="background:rgba(16,185,129,0.1); border-color:#10b981; color:#10b981; padding:6px 12px;"><i class="fas fa-check"></i> 200 OK</span></td>';
        echo '</tr>';
    }
    exit;
}

// Reset Daily Limits
if(isset($_GET['reset_daily'])){
    $pdo->exec("UPDATE api_keys SET used_today = 0");
    $msg = "Daily usage counters reset successfully!";
}

// Change Admin Password
if(isset($_POST['change_password'])){
    $new_pass = trim($_POST['new_password']);
    if(strlen($new_pass) < 4){
        $msg = "Error: Password must be at least 4 characters.";
    } else {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_password'")->execute([$new_pass]);
        $msg = "Admin password updated successfully!";
    }
}

// Analytics AJAX
if(isset($_GET['ajax']) && $_GET['ajax'] == 'analytics'){
    header('Content-Type: application/json');
    $id = (int)$_GET['key_id'];
    $data = []; $labels = [];
    for($i=6; $i>=0; $i--){
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime("-$i days"));
        $st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE api_key_id = ? AND date(used_at) = ?");
        $st->execute([$id, $d]);
        $data[] = (int)$st->fetchColumn();
    }
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

// --- Fetch Data ---
$api_keys = $pdo->query("SELECT * FROM api_keys ORDER BY used_today DESC")->fetchAll(PDO::FETCH_ASSOC);
$total_keys = count($api_keys);
$active_keys = count(array_filter($api_keys, function($k){ return $k['active'] == 1; }));

$total_hits = $pdo->query("SELECT COUNT(*) FROM usage_logs")->fetchColumn();
$today_hits = $pdo->query("SELECT COUNT(*) FROM usage_logs WHERE date(used_at) = date('now')")->fetchColumn();

// Logs
$logs = $pdo->query("
    SELECT u.id, u.query, u.used_at, u.service_type, k.key_text, k.client_name
    FROM usage_logs u
    LEFT JOIN api_keys k ON u.api_key_id = k.id
    ORDER BY u.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$activeTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Master Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #10b981;
            --dark: #0f172a;
            --sidebar: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
            --surface: #ffffff;
            --bg: #f4f7fb;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px rgba(79, 70, 229, 0.15);
            --radius: 20px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; display: flex; height: 100vh; overflow: hidden; color: var(--text-main); }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }

        /* Sidebar */
        .sidebar { width: 280px; background: var(--sidebar); color: white; display: flex; flex-direction: column; position: relative; overflow: hidden; box-shadow: 4px 0 20px rgba(0,0,0,0.05); }
        .sidebar::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%); pointer-events: none; }
        .logo { padding: 32px 24px; font-size: 26px; font-weight: 800; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; position: relative; z-index: 1; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .logo i { color: #818cf8; font-size: 28px; }
        .logo span { color: #fff; }
        .logo span.highlight { color: #a5b4fc; }
        
        .nav-links { flex: 1; padding: 24px 0; overflow-y: auto; position: relative; z-index: 1; }
        .nav-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; padding: 10px 32px 4px; font-weight: 700; margin-top: 10px; }
        .nav-item { padding: 12px 32px; color: #cbd5e1; text-decoration: none; display: flex; align-items: center; gap: 16px; font-weight: 500; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-left: 4px solid transparent; font-size: 15px; }
        .nav-item:hover { color: white; background: rgba(255,255,255,0.08); border-left-color: #818cf8; }
        .nav-item.active { background: rgba(79, 70, 229, 0.2); color: white; border-left-color: #818cf8; font-weight: 600; box-shadow: inset 0px 0px 20px rgba(0,0,0,0.1); }
        .nav-item i { width: 20px; font-size: 18px; text-align: center; transition: transform 0.3s; }
        .nav-item:hover i { transform: scale(1.1); }

        .logout-box { padding: 24px; position: relative; z-index: 1; border-top: 1px solid rgba(255,255,255,0.05); }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .topbar { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(16px); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); z-index: 10; }
        .topbar-title { font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 22px; color: var(--text-main); }
        .status-badge { background: #d1fae5; color: #065f46; padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15); border: 1px solid #a7f3d0; }
        .status-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 10px #10b981; animation: pulse 2s infinite; }

        .content-area { padding: 40px; overflow-y: auto; flex: 1; }
        .page-header { margin-bottom: 32px; animation: fadeInUp 0.5s ease; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-title { font-size: 36px; color: var(--text-main); font-weight: 800; letter-spacing: -1px; line-height: 1.1; }
        .page-subtitle { color: var(--text-muted); font-size: 16px; margin-top: 8px; font-weight: 500; }

        /* Dashboard Cards */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .dash-card { background: var(--surface); padding: 32px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid rgba(255,255,255,0.8); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; animation: fadeInUp 0.6s ease both; }
        .dash-card:nth-child(2) { animation-delay: 0.1s; }
        .dash-card:nth-child(3) { animation-delay: 0.2s; }
        .dash-card:nth-child(4) { animation-delay: 0.3s; }
        .dash-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); border-color: #e2e8f0; }
        .dash-icon { position: absolute; top: -15px; right: -15px; font-size: 140px; color: rgba(79, 70, 229, 0.04); transform: rotate(-15deg); transition: transform 0.4s; }
        .dash-card:hover .dash-icon { transform: rotate(0deg) scale(1.1); color: rgba(79, 70, 229, 0.08); }
        .dash-card-title { color: var(--text-muted); font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .dash-card-value { font-size: 48px; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-main); line-height: 1; }
        .text-green { color: var(--secondary); }
        .text-indigo { color: var(--primary); }
        .text-orange { color: #f59e0b; }

        /* Content Cards */
        .card { background: var(--surface); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); margin-bottom: 32px; border: 1px solid rgba(0,0,0,0.02); animation: fadeInUp 0.6s ease both; }
        .card-title { font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700; margin-bottom: 24px; color: var(--text-main); display: flex; align-items: center; gap: 12px; }
        
        /* Tables */
        .table-container { overflow-x: auto; margin: -10px; padding: 10px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { padding: 16px; text-align: left; font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; border-bottom: 2px solid var(--border); }
        td { padding: 18px 16px; background: #fff; font-size: 15px; color: var(--text-main); transition: all 0.2s; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        tr { box-shadow: 0 2px 5px rgba(0,0,0,0.01); transition: transform 0.2s; }
        tr:hover { transform: scale(1.01); }
        tr:hover td { background: #f8fafc; border-color: #cbd5e1; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
        td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-left: 1px solid var(--border); font-weight: 600; }
        tr:hover td:first-child { border-left-color: #cbd5e1; }
        td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-right: 1px solid var(--border); }
        tr:hover td:last-child { border-right-color: #cbd5e1; }

        /* Badges */
        .badge { padding: 8px 14px; border-radius: 30px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .badge-success { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; box-shadow: 0 2px 10px rgba(22, 101, 52, 0.1); }
        .badge-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        /* Forms & Inputs */
        .form-row { display: flex; gap: 24px; margin-bottom: 24px; }
        .form-group { margin-bottom: 24px; flex: 1; }
        .form-label { display: block; font-weight: 700; margin-bottom: 10px; color: var(--text-main); font-size: 14px; }
        .form-control { width: 100%; padding: 16px 20px; border: 2px solid var(--border); border-radius: 12px; font-size: 15px; color: var(--text-main); transition: all 0.3s; font-family: 'Inter', sans-serif; background: #f8fafc; outline: none; }
        .form-control:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .checkbox-group { display: flex; gap: 20px; margin-top: 10px; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; cursor: pointer; background: #f1f5f9; padding: 12px 20px; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .checkbox-label:hover { background: #e2e8f0; }
        .checkbox-label input { width: 18px; height: 18px; accent-color: var(--primary); }

        /* Buttons */
        .btn { padding: 14px 28px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; color: white; font-size: 15px; font-family: 'Inter', sans-serif; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%); box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4); }
        .btn-success { background: linear-gradient(135deg, var(--secondary) 0%, #34d399 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-success:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4); }

        .alert-box { background: #dcfce3; border-left: 5px solid var(--secondary); color: #166534; padding: 20px 24px; border-radius: 12px; margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 16px; animation: fadeInUp 0.4s ease; box-shadow: 0 4px 15px rgba(22, 101, 52, 0.1); font-size: 15px; }
        .alert-box.error { background: #fee2e2; border-left-color: #ef4444; color: #991b1b; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1); }

        .endpoint-box { background: var(--dark); color: #a5b4fc; padding: 20px 24px; border-radius: 12px; font-family: monospace; font-size: 15px; margin-top: 10px; border: 1px solid #334155; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2); }
        .endpoint-box span { color: #fff; font-weight: bold; }

        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #818cf8); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); } 70% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-cube"></i>
            <div>NEXUS <span class="highlight">SAAS</span></div>
        </div>
        <div class="nav-links">
            <div class="nav-label">Main</div>
            <a class="nav-item <?= $activeTab=='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a class="nav-item <?= $activeTab=='users'?'active':'' ?>" href="?tab=users"><i class="fas fa-users-cog"></i> Users & Keys</a>
            <a class="nav-item <?= $activeTab=='traffic'?'active':'' ?>" href="?tab=traffic"><i class="fas fa-bolt"></i> Live Traffic</a>
            
            <div class="nav-label">System</div>
            <a class="nav-item <?= $activeTab=='settings'?'active':'' ?>" href="?tab=settings"><i class="fas fa-cog"></i> Settings</a>
            <a class="nav-item" href="?reset_daily=1"><i class="fas fa-sync-alt"></i> Reset Daily Limits</a>
        </div>
        <div class="logout-box">
            <a href="?logout=1" class="btn btn-primary" style="width:100%;"><i class="fas fa-sign-out-alt"></i> Secure Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Master Control Center</div>
            <div class="status-badge">
                <div class="status-dot"></div> SYSTEMS ONLINE
            </div>
        </div>
        
        <div class="content-area">
            <?php if($msg): ?>
            <div class="alert-box <?= strpos(strtolower($msg), 'error') !== false ? 'error' : '' ?>">
                <i class="fas <?= strpos(strtolower($msg), 'error') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>" style="font-size: 24px;"></i>
                <?= $msg ?>
            </div>
            <?php endif; ?>
            
            <?php if($activeTab == 'dashboard'): ?>
            <div class="page-header">
                <div>
                    <h2 class="page-title">Dashboard Overview</h2>
                    <div class="page-subtitle">Real-time metrics and top user analytics for your API ecosystem.</div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="dash-card">
                    <i class="fas fa-users dash-icon"></i>
                    <div class="dash-card-title"><i class="fas fa-users text-indigo"></i> Total Users (Keys)</div>
                    <div class="dash-card-value text-indigo"><?= $total_keys ?></div>
                </div>
                <div class="dash-card">
                    <i class="fas fa-fire dash-icon"></i>
                    <div class="dash-card-title"><i class="fas fa-fire text-orange"></i> Today's Hits</div>
                    <div class="dash-card-value text-orange"><?= number_format($today_hits) ?></div>
                </div>
                <div class="dash-card">
                    <i class="fas fa-server dash-icon"></i>
                    <div class="dash-card-title"><i class="fas fa-network-wired text-green"></i> Total All-Time Hits</div>
                    <div class="dash-card-value text-green"><?= number_format($total_hits) ?></div>
                </div>
            </div>

            <!-- Top Users Today Widget -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-trophy text-orange"></i> Top Active Users Today</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User (Client Name)</th>
                                <th>API Key Segment</th>
                                <th>Hits Today</th>
                                <th>Usage / Limit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $top_users = array_slice($api_keys, 0, 5); // Already sorted by used_today DESC
                            foreach($top_users as $u): 
                                $percent = $u['daily_limit'] > 0 ? min(100, round(($u['used_today'] / $u['daily_limit']) * 100)) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="user-avatar"><?= strtoupper(substr($u['client_name'] ?: 'U', 0, 1)) ?></div>
                                        <div style="font-weight: 700; font-size: 16px;"><?= htmlspecialchars($u['client_name'] ?: 'Unknown User') ?></div>
                                    </div>
                                </td>
                                <td><code style="color:var(--text-muted); font-weight:bold; background:#f1f5f9; padding:4px 8px; border-radius:6px;"><?= substr($u['key_text'], 0, 8) ?>...</code></td>
                                <td>
                                    <span style="font-size: 18px; font-weight: 800; color: var(--primary);"><?= $u['used_today'] ?></span> hits
                                </td>
                                <td>
                                    <?php if($u['daily_limit'] > 0): ?>
                                        <div style="width: 100%; background: #e2e8f0; border-radius: 10px; height: 10px; margin-bottom: 6px; overflow: hidden;">
                                            <div style="width: <?= $percent ?>%; background: <?= $percent > 90 ? '#ef4444' : 'var(--primary)' ?>; height: 100%; border-radius: 10px;"></div>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted); font-weight: 600; text-align: right;"><?= $percent ?>% used</div>
                                    <?php else: ?>
                                        <span class="badge badge-success">Unlimited Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($u['active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Banned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($top_users)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No users yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($activeTab == 'users'): ?>
            <div class="page-header">
                <div>
                    <h2 class="page-title">Users & API Keys</h2>
                    <div class="page-subtitle">Generate secure keys, monitor usage, and manage your clients.</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 32px;">
                <!-- Create Form -->
                <div class="card" style="margin-bottom: 0; border-top: 5px solid var(--primary);">
                    <h3 class="card-title"><i class="fas fa-user-plus text-indigo"></i> Register New Client</h3>
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Client Name</label>
                                <input type="text" name="client_name" class="form-control" placeholder="e.g. Rahul Sharma" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Secret API Key</label>
                                <input type="text" name="key_text" class="form-control" value="KEY_<?= strtoupper(substr(md5(uniqid()), 0, 12)) ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Daily Limit</label>
                                <input type="number" name="daily_limit" class="form-control" value="100" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Speed Limit (RPM)</label>
                                <input type="number" name="rpm_limit" class="form-control" value="60" min="1" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Allowed Services</label>
                            <div class="checkbox-group" style="flex-wrap: wrap;">
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="number" checked> Number Info</label>
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="vehicle" checked> Vehicle Info</label>
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="lpg" checked> LPG Info</label>
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="num2veh" checked> Num To Vehicle</label>
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="dlinfo" checked> DL Info</label>
                                <label class="checkbox-label"><input type="checkbox" name="services[]" value="dl2num" checked> DL To Number</label>
                            </div>
                        </div>
                        <button type="submit" name="create_key" class="btn btn-primary" style="width: 100%;"><i class="fas fa-magic"></i> Generate User & Key</button>
                    </form>
                </div>

                <!-- API Docs Sidebar -->
                <div class="card" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; margin-bottom: 0;">
                    <h3 class="card-title" style="color: var(--primary);"><i class="fas fa-laptop-code"></i> API Integration</h3>
                    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.6;">Share this endpoint format with your clients so they can integrate the API into their systems.</p>
                    <?php $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); $base = rtrim($base, '/'); ?>
                    
                    <div style="margin-bottom: 16px;">
                        <span style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Endpoint URL</span>
                        <div style="background: #1e293b; color: #a5b4fc; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 13px; margin-top: 6px; word-break: break-all;">
                            GET <span style="color:#fff;"><?= $base ?>/<span style="color:#fde047;">[service]</span>.php?key=<span style="color:#fde047;">YOUR_KEY</span>&num=<span style="color:#fde047;">INPUT</span></span>
                        </div>
                    </div>
                    
                    <div style="background: #e0e7ff; padding: 12px 16px; border-radius: 10px; border-left: 4px solid var(--primary);">
                        <div style="font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 4px;">Service Types:</div>
                        <div style="font-size: 13px; color: #3730a3; font-weight: 600;"><code>num</code> = Number, <code>vk</code> = Vehicle, <code>lpg</code> = LPG, <code>num2veh</code> = Num To Vehicle, <code>dlinfo</code> = DL Info, <code>dl2num</code> = DL To Number</div>
                    </div>
                </div>
            </div>

            <!-- Full User Table -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-users"></i> Client Directory & API Keys</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>API Key</th>
                                <th>Usage / Limit (Today)</th>
                                <th>Status / Expiry</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($api_keys as $k): 
                                $isExpired = (!empty($k['expiry_date']) && strtotime($k['expiry_date']) < time());
                                $percent = $k['daily_limit'] > 0 ? min(100, round(($k['used_today'] / $k['daily_limit']) * 100)) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="user-avatar" style="background: linear-gradient(135deg, var(--primary), #818cf8); width: 44px; height: 44px; font-size: 18px; box-shadow: 0 4px 10px rgba(79,70,229,0.2);"><?= strtoupper(substr($k['client_name'] ?: 'U', 0, 1)) ?></div>
                                        <div>
                                            <div style="font-weight: 800; font-size: 16px; color: var(--text-main); margin-bottom:2px;"><?= htmlspecialchars($k['client_name'] ?: 'Unknown User') ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); font-weight:600;"><i class="fas fa-id-badge"></i> ID: #<?= str_pad($k['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <code id="key_<?= $k['id'] ?>" style="background: #f1f5f9; padding: 8px 12px; border-radius: 8px; color: var(--primary); font-weight: 700; font-size: 13px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($k['key_text']) ?></code>
                                            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($k['key_text']) ?>'); alert('Key Copied!');" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; transition: 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'" title="Copy Key">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </div>
                                        <div>
                                            <?php
                                            $srvs = array_map('trim', explode(',', $k['service_type']));
                                            $copy_text = "API Links:\\n";
                                            foreach($srvs as $srv){
                                                if($srv == 'number') $copy_text .= "- Number Info: {$base}/num.php?key={$k['key_text']}&num=INPUT\\n";
                                                if($srv == 'vehicle') $copy_text .= "- Vehicle Info: {$base}/vk.php?key={$k['key_text']}&num=INPUT\\n";
                                                if($srv == 'lpg') $copy_text .= "- LPG Info: {$base}/lpg.php?key={$k['key_text']}&lpg_id=INPUT\\n";
                                                if($srv == 'num2veh') $copy_text .= "- Num To Vehicle: {$base}/num2veh.php?key={$k['key_text']}&num=INPUT\\n";
                                                if($srv == 'dlinfo') $copy_text .= "- DL Info: {$base}/dlinfo.php?key={$k['key_text']}&dl_no=INPUT&dob=DD-MM-YYYY\\n";
                                                if($srv == 'dl2num') $copy_text .= "- DL To Number: {$base}/dl2num.php?key={$k['key_text']}&dl_no=INPUT&dob=DD-MM-YYYY\\n";
                                            }
                                            $copy_text = addslashes(rtrim($copy_text));
                                            ?>
                                            <button onclick="navigator.clipboard.writeText(`<?= $copy_text ?>`); alert('Allowed API Links Copied!');" style="background: #e0e7ff; color: var(--primary); border: none; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;" onmouseover="this.style.background='#c7d2fe'" onmouseout="this.style.background='#e0e7ff'" title="Copy API Links">
                                                <i class="fas fa-link"></i> Copy API Links
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td style="min-width: 200px;">
                                    <?php if($k['daily_limit'] > 0): ?>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
                                            <span><?= $k['used_today'] ?> used</span>
                                            <span style="color: var(--text-muted);"><?= $k['daily_limit'] ?> limit</span>
                                        </div>
                                        <div style="width: 100%; background: #e2e8f0; border-radius: 10px; height: 10px; overflow: hidden; margin-bottom: 4px;">
                                            <div style="width: <?= $percent ?>%; background: <?= $percent > 90 ? '#ef4444' : 'linear-gradient(90deg, var(--secondary), #34d399)' ?>; height: 100%; border-radius: 10px; transition: width 0.5s ease;"></div>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-weight: 800; font-size: 16px; color: var(--secondary);"><i class="fas fa-infinity"></i> Unlimited</div>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total All-Time: <?= $k['total_used'] ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-start;">
                                        <?php if($k['active']): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><i class="fas fa-ban"></i> Revoked</span>
                                        <?php endif; ?>

                                        <?php if($isExpired): ?>
                                            <span class="badge badge-danger"><i class="fas fa-times"></i> Expired</span>
                                        <?php elseif(!empty($k['expiry_date'])): ?>
                                            <span class="badge badge-warning" style="background:#fffbeb; border-color:#fde68a; color:#b45309;"><i class="fas fa-calendar-alt"></i> <?= date('d M Y', strtotime($k['expiry_date'])) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-gray" style="background:transparent; border:none; color:var(--text-muted); padding:0;"><i class="fas fa-infinity"></i> Lifetime Access</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <form method="post" style="display:inline-flex; gap:8px;">
                                        <button type="button" class="btn btn-primary" style="padding: 10px 14px; box-shadow:none; background:#475569;" onclick="openAnalytics(<?= $k['id'] ?>, '<?= htmlspecialchars(addslashes($k['client_name'] ?: 'Unknown')) ?>')" title="View Analytics">
                                            <i class="fas fa-chart-line"></i>
                                        </button>
                                        <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                                        <button type="submit" name="toggle_key" class="btn <?= $k['active'] ? 'btn-danger' : 'btn-success' ?>" style="padding: 10px 14px; box-shadow:none;" title="<?= $k['active'] ? 'Revoke Key' : 'Activate Key' ?>">
                                            <i class="fas <?= $k['active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
                                        <button type="submit" name="delete_key" class="btn btn-danger" style="padding: 10px 14px; background: #ef4444; box-shadow:none;" onclick="return confirm('Delete this Client & API Key completely? This cannot be undone.');" title="Delete Key">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($api_keys)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 50px; font-size:16px;">
                                <i class="fas fa-users-slash" style="font-size: 40px; color: #cbd5e1; margin-bottom: 16px; display:block;"></i>
                                No Clients or API Keys generated yet. Start by creating one above!
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($activeTab == 'traffic'): ?>
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                    <h2 class="page-title"><i class="fas fa-satellite-dish text-indigo" style="animation: pulse 2s infinite;"></i> Live Traffic Network</h2>
                    <div class="page-subtitle">Monitor real-time API requests globally across your network.</div>
                </div>
                <div style="display:flex; gap:12px; align-items:center;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:14px; cursor:pointer; background:#fff; padding:10px 16px; border-radius:12px; box-shadow:var(--shadow);">
                        <input type="checkbox" id="autoRefreshToggle" checked style="width:16px; height:16px; accent-color:var(--primary);"> Auto-Update (3s)
                    </label>
                    <form method="post" style="margin:0;">
                        <button type="submit" name="clear_logs" class="btn btn-danger" onclick="return confirm('Clear all logs?');" style="padding:10px 16px;"><i class="fas fa-trash"></i> Clear Logs</button>
                    </form>
                </div>
            </div>
            
            <div class="card" style="background:#0f172a; border:1px solid #1e293b; box-shadow:0 20px 40px rgba(0,0,0,0.3);">
                <div class="table-container">
                    <table style="border-spacing: 0 4px;">
                        <thead>
                            <tr>
                                <th style="color:#64748b; border-bottom:1px solid #334155;">Timestamp</th>
                                <th style="color:#64748b; border-bottom:1px solid #334155;">User (Client)</th>
                                <th style="color:#64748b; border-bottom:1px solid #334155;">Microservice</th>
                                <th style="color:#64748b; border-bottom:1px solid #334155;">Query Target</th>
                                <th style="color:#64748b; border-bottom:1px solid #334155;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="liveTrafficBody">
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td style="background:#1e293b; border-color:#1e293b;"><div style="font-weight: 600; font-size: 14px; color: #94a3b8;"><i class="far fa-clock" style="margin-right:6px;"></i> <?= date('H:i:s.v', strtotime($log['used_at'])) ?></div></td>
                                <td style="background:#1e293b; border-color:#1e293b;"><div style="font-weight: 700; font-size: 15px; color:#e2e8f0;"><?= htmlspecialchars($log['client_name'] ?? 'Unknown User') ?></div></td>
                                <td style="background:#1e293b; border-color:#1e293b;"><span class="badge badge-gray" style="background:#0f172a; color:#818cf8; border:1px solid #312e81; text-transform: uppercase; letter-spacing: 1px;"><i class="fas fa-microchip" style="font-size:12px;"></i> <?= htmlspecialchars($log['service_type'] ?: 'API') ?></span></td>
                                <td style="background:#1e293b; border-color:#1e293b;"><code style="background: #000; padding: 6px 12px; border-radius: 8px; font-family: monospace; color: #10b981; font-weight: bold; font-size: 14px; border:1px solid #064e3b; box-shadow: 0 0 10px rgba(16,185,129,0.2);"><?= htmlspecialchars($log['query']) ?></code></td>
                                <td style="background:#1e293b; border-color:#1e293b;"><span class="badge badge-success" style="background:rgba(16,185,129,0.1); border-color:#10b981; color:#10b981; padding:6px 12px;"><i class="fas fa-check"></i> 200 OK</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                setInterval(() => {
                    if(document.getElementById('autoRefreshToggle') && document.getElementById('autoRefreshToggle').checked) {
                        fetch('?ajax=traffic')
                        .then(r => r.text())
                        .then(html => {
                            if(html.trim() !== '') document.getElementById('liveTrafficBody').innerHTML = html;
                        });
                    }
                }, 3000);
            </script>
            <?php endif; ?>
            
            <?php if($activeTab == 'settings'): ?>
            <div class="page-header">
                <div>
                    <h2 class="page-title"><i class="fas fa-shield-alt text-indigo"></i> Security Settings</h2>
                    <div class="page-subtitle">Manage your admin panel security and passwords.</div>
                </div>
            </div>
            
            <div class="card" style="max-width: 500px;">
                <h3 class="card-title"><i class="fas fa-key text-indigo"></i> Change Admin Password</h3>
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="text" name="new_password" class="form-control" placeholder="Enter new password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Update Password</button>
                </form>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- Analytics Modal -->
    <div id="analyticsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; width:600px; max-width:90%; padding:30px; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.2); position:relative; animation:fadeInUp 0.3s ease;">
            <button onclick="document.getElementById('analyticsModal').style.display='none';" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;"><i class="fas fa-times"></i></button>
            <h3 style="font-family:'Outfit',sans-serif; margin-bottom:20px; color:#1e293b;"><i class="fas fa-chart-line text-indigo"></i> <span id="modalClientName">User</span> Analytics (Last 7 Days)</h3>
            <canvas id="analyticsChart" height="250"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    let currentChart = null;
    function openAnalytics(keyId, clientName) {
        document.getElementById('modalClientName').innerText = clientName;
        document.getElementById('analyticsModal').style.display = 'flex';
        
        fetch('?ajax=analytics&key_id=' + keyId)
            .then(res => res.json())
            .then(data => {
                const ctx = document.getElementById('analyticsChart').getContext('2d');
                if(currentChart) currentChart.destroy();
                currentChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'API Hits',
                            data: data.data,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } }
                    }
                });
            });
    }
    </script>
</body>
</html>