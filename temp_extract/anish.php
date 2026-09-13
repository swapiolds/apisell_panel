<?php
// anish.php - Number Info API Management
session_start();
ini_set('display_errors', 0);

$ADMIN_USER = 'anish';
$ADMIN_PASS = 'anish123';

// Login
if(isset($_POST['login_user']) && isset($_POST['login_pass'])){
    if($_POST['login_user'] === $ADMIN_USER && $_POST['login_pass'] === $ADMIN_PASS){
        $_SESSION['admin_logged'] = true;
        header('Location: anish.php');
        exit;
    } else {
        $login_error = 'Invalid credentials';
    }
}

// Logout
if(isset($_GET['action']) && $_GET['action'] === 'logout'){
    session_destroy();
    header('Location: anish.php');
    exit;
}

// Check login
if(empty($_SESSION['admin_logged'])){
    ?>
    <!doctype html>
    <html>
    <head>
        <title>Admin Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            *{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;}
            body{background:#f2f4f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#222;}
            .login-box{width:100%;max-width:400px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:36px 32px;}
            .login-box .logo{width:52px;height:52px;background:#333;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:22px;margin:0 auto 16px;}
            .login-box h1{font-size:20px;font-weight:600;text-align:center;margin-bottom:6px;color:#222;}
            .login-box p.sub{font-size:13px;color:#777;text-align:center;margin-bottom:26px;}
            .form-group{margin-bottom:14px;}
            .form-group label{display:block;font-size:13px;font-weight:500;color:#333;margin-bottom:5px;}
            .form-group input{width:100%;padding:10px 12px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:14px;color:#222;font-family:inherit;}
            .form-group input:focus{outline:none;border-color:#555;}
            .login-btn{width:100%;padding:11px;background:#333;color:#fff;border:none;border-radius:4px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;}
            .login-btn:hover{background:#111;}
            .login-btn i{margin-right:6px;}
            .error-msg{background:#fdecea;border:1px solid #f5c6c2;color:#c0392b;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
            .login-footer{text-align:center;font-size:12px;color:#999;margin-top:18px;}
        </style>
    </head>
    <body>

        <!-- ✅ DISCLAIMER POPUP (Login) -->
        <div id="disclaimerPopup" style="position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;opacity:1;transition:opacity 0.4s;">
            <div style="background:#fff;max-width:560px;width:100%;border-radius:8px;padding:26px 24px;border:1px solid #ddd;box-shadow:0 10px 40px rgba(0,0,0,0.25);">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #eee;">
                    <span style="font-size:22px;">⚠️</span>
                    <strong style="font-size:15px;color:#c0392b;">DISCLAIMER</strong>
                </div>
                <p style="font-size:13px;color:#333;line-height:1.65;">
                    This website is strictly for <strong>educational, informational, and demonstration purposes only</strong>. The API is demonstrated using sample/dummy data. We do not support unauthorized access, privacy violations, stalking, harassment, fraud, or misuse of personal information. Do not use this API to target, identify, track, harass, or harm any individual. Users are responsible for complying with applicable laws, privacy regulations, API terms, and Google policies. No real person's private information is intentionally disclosed.
                </p>
                <div style="margin-top:16px;text-align:right;font-size:11px;color:#999;">
                    <i class="fas fa-clock"></i> Auto closing in <span id="discCount">2</span>s...
                </div>
            </div>
        </div>
        <script>
            (function(){
                let c = 2;
                const el = document.getElementById('discCount');
                const t = setInterval(()=>{ c--; if(el) el.textContent = c; if(c<=0) clearInterval(t); }, 1000);
                setTimeout(()=>{
                    const p = document.getElementById('disclaimerPopup');
                    if(p){ p.style.opacity='0'; setTimeout(()=>p.remove(), 400); }
                }, 2000);
            })();
        </script>

        <div class="login-box">
            <div class="logo">V</div>
            <h1>Admin Login</h1>
            <p class="sub">Sign in to manage the API system</p>
            <?php if(!empty($login_error)) echo '<div class="error-msg"><i class="fas fa-exclamation-circle"></i> '.$login_error.'</div>'; ?>
            <form method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input name="login_user" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input name="login_pass" type="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="login-btn"><i class="fas fa-lock"></i> Sign In</button>
            </form>
            <div class="login-footer">Secure Administration</div>
        </div>
    </body>
    </html>
    <?php exit;
}

// Database
$pdo = new PDO('sqlite:'.__DIR__.'/osint_api.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_text TEXT UNIQUE,
    service_type TEXT DEFAULT 'number',
    daily_limit INTEGER DEFAULT 100,
    used_today INTEGER DEFAULT 0,
    total_used INTEGER DEFAULT 0,
    last_used TEXT DEFAULT '',
    expiry_date TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now')),
    active INTEGER DEFAULT 1
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS usage_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    api_key_id INTEGER,
    query TEXT,
    used_at TEXT DEFAULT (datetime('now'))
)");

// Create Key
$msg = ''; $msgType = 'success';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create'){
    $key_text = trim($_POST['key_text'] ?? '');
    $daily_limit = (int)($_POST['daily_limit'] ?? 100);
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    if($key_text === ''){
        $msg = 'Key text required'; $msgType = 'error';
    } else {
        $st = $pdo->prepare('INSERT OR IGNORE INTO api_keys (key_text, service_type, daily_limit, expiry_date, active) VALUES (:k, :s, :d, :e, 1)');
        $st->execute([':k'=>$key_text, ':s'=>'number', ':d'=>$daily_limit, ':e'=>$expiry_date]);
        $msg = 'API Key created successfully!';
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $new_limit = (int)($_POST['daily_limit'] ?? 0);
    $new_expiry = trim($_POST['expiry_date'] ?? '');
    $pdo->prepare('UPDATE api_keys SET daily_limit = :limit, expiry_date = :expiry WHERE id = :id')->execute([':limit'=>$new_limit, ':expiry'=>$new_expiry, ':id'=>$id]);
    $msg = 'API Key updated successfully!';
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle' && isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $st = $pdo->prepare('SELECT active FROM api_keys WHERE id = :id');
    $st->execute([':id'=>$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if($r){
        $new = $r['active'] ? 0 : 1;
        $pdo->prepare('UPDATE api_keys SET active = :a WHERE id = :id')->execute([':a'=>$new, ':id'=>$id]);
        $msg = 'Key status updated!';
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM usage_logs WHERE api_key_id = :id')->execute([':id'=>$id]);
    $pdo->prepare('DELETE FROM api_keys WHERE id = :id')->execute([':id'=>$id]);
    $msg = 'Key deleted successfully!';
}

if(isset($_GET['reset_daily'])){
    $pdo->exec("UPDATE api_keys SET used_today = 0");
    $msg = 'Daily usage reset successfully!';
}

$keys = $pdo->query('SELECT * FROM api_keys ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$totalUsage = $pdo->query("SELECT COUNT(*) FROM usage_logs")->fetchColumn();
$todayUsage = $pdo->query("SELECT COUNT(*) FROM usage_logs WHERE date(used_at) = date('now')")->fetchColumn();
$activeKeys = count(array_filter($keys, fn($k)=>$k['active']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;}
        body{background:#f2f4f7;color:#222;line-height:1.5;font-size:14px;}
        a{text-decoration:none;color:inherit;}
        button{cursor:pointer;font-family:inherit;}
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:230px;background:#fff;border-right:1px solid #ddd;display:flex;flex-direction:column;z-index:1000;transition:transform 0.25s;}
        .sidebar .brand{padding:18px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px;}
        .sidebar .brand .icon{width:36px;height:36px;background:#333;color:#fff;border-radius:5px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;}
        .sidebar .brand .text strong{font-size:14px;color:#222;display:block;}
        .sidebar .brand .text small{font-size:11px;color:#888;}
        .sidebar nav{flex:1;padding:14px 10px;overflow-y:auto;}
        .sidebar nav .nav-label{font-size:10px;color:#999;text-transform:uppercase;letter-spacing:0.5px;padding:10px 10px 4px;font-weight:600;}
        .sidebar nav a{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:4px;font-size:13px;color:#555;margin-bottom:2px;}
        .sidebar nav a i{width:16px;font-size:14px;text-align:center;}
        .sidebar nav a:hover{background:#f2f4f7;color:#222;}
        .sidebar nav a.active{background:#eef1f5;color:#222;font-weight:600;}
        .sidebar .foot{padding:14px 18px;border-top:1px solid #eee;font-size:11px;color:#999;}
        .menu-btn{display:none;position:fixed;top:12px;left:12px;z-index:1100;background:#fff;border:1px solid #ddd;border-radius:4px;width:38px;height:38px;font-size:16px;color:#333;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;}
        .overlay.active{display:block;}
        .main{margin-left:230px;min-height:100vh;display:flex;flex-direction:column;}
        .topbar{background:#fff;border-bottom:1px solid #ddd;padding:0 26px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
        .topbar .crumb{font-size:12px;color:#888;}
        .topbar .crumb strong{color:#222;}
        .topbar .right{display:flex;align-items:center;gap:12px;}
        .topbar .secure{font-size:11px;color:#060;background:#eafaf0;border:1px solid #c8ebd6;padding:3px 10px;border-radius:12px;}
        .topbar .logout{font-size:12px;color:#555;border:1px solid #ddd;padding:5px 12px;border-radius:4px;background:#fff;}
        .topbar .logout:hover{background:#fdecea;border-color:#f5c6c2;color:#c0392b;}
        .topbar .logout i{margin-right:4px;}
        .content{padding:24px 26px 40px;flex:1;}
        .page-title{margin-bottom:22px;padding-bottom:14px;border-bottom:1px solid #e6e6e6;}
        .page-title h1{font-size:22px;font-weight:600;color:#222;}
        .page-title p{font-size:13px;color:#777;margin-top:2px;}
        .alert{padding:11px 16px;border-radius:4px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:8px;}
        .alert.success{background:#eafaf0;border:1px solid #c8ebd6;color:#0a7a3d;}
        .alert.error{background:#fdecea;border:1px solid #f5c6c2;color:#c0392b;}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px;}
        .stat{background:#fff;border:1px solid #ddd;border-radius:5px;padding:16px 18px;display:flex;align-items:center;gap:14px;}
        .stat .ico{width:40px;height:40px;border-radius:5px;background:#eef1f5;color:#333;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
        .stat .num{font-size:22px;font-weight:700;color:#222;line-height:1.1;}
        .stat .lbl{font-size:12px;color:#777;margin-top:2px;}
        .card{background:#fff;border:1px solid #ddd;border-radius:5px;padding:20px 22px;margin-bottom:22px;}
        .card-head{display:flex;align-items:center;gap:10px;padding-bottom:12px;border-bottom:1px solid #eee;margin-bottom:18px;}
        .card-head .ico{width:32px;height:32px;border-radius:5px;background:#eef1f5;color:#333;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .card-head h3{font-size:15px;font-weight:600;color:#222;}
        .card-head .sub{font-size:12px;color:#888;margin-left:auto;}
        .form-group{margin-bottom:14px;}
        .form-group label{display:block;font-size:12px;font-weight:600;color:#333;margin-bottom:4px;}
        .form-group label .req{color:#c0392b;}
        .form-group input{width:100%;padding:9px 12px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:13px;color:#222;font-family:inherit;}
        .form-group input:focus{outline:none;border-color:#555;}
        .form-group small{font-size:11px;color:#999;display:block;margin-top:3px;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .service-fixed{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;background:#eef1f5;border:1px solid #ddd;border-radius:4px;font-size:12px;font-weight:600;color:#333;margin-bottom:16px;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 20px;border:none;border-radius:4px;font-size:13px;font-weight:500;font-family:inherit;}
        .btn-primary{background:#333;color:#fff;}
        .btn-primary:hover{background:#111;}
        .btn-secondary{background:#f2f4f7;color:#555;border:1px solid #ddd;}
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:13px;}
        table thead th{text-align:left;padding:9px 10px;font-size:10px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:0.4px;border-bottom:1px solid #ddd;background:#f2f4f7;}
        table tbody td{padding:9px 10px;border-bottom:1px solid #eee;color:#222;vertical-align:middle;}
        table tbody tr:hover{background:#fafbfc;}
        table tbody td .key{font-weight:600;font-size:12px;font-family:monospace;}
        .badge{display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:500;}
        .badge-ok{background:#eafaf0;border:1px solid #c8ebd6;color:#0a7a3d;}
        .badge-bad{background:#fdecea;border:1px solid #f5c6c2;color:#c0392b;}
        .badge-info{background:#eaf2fb;border:1px solid #c7dcf4;color:#2358a0;}
        .badge-neutral{background:#f2f4f7;border:1px solid #ddd;color:#555;}
        .action-group{display:flex;gap:4px;flex-wrap:wrap;}
        .abtn{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border:none;border-radius:3px;font-size:11px;font-weight:500;font-family:inherit;}
        .abtn-edit{background:#eaf2fb;color:#2358a0;}
        .abtn-toggle{background:#fff7e6;color:#a86a00;}
        .abtn-del{background:#fdecea;color:#c0392b;}
        .endpoint{background:#2b2b2b;color:#e8e8e8;border-radius:5px;padding:14px 16px;font-family:monospace;font-size:12px;line-height:1.7;overflow-x:auto;}
        .endpoint .lbl{color:#aaa;font-weight:600;margin-bottom:6px;display:block;font-family:-apple-system,sans-serif;}
        .endpoint .line{display:flex;gap:10px;}
        .endpoint .line .m{color:#f0a04b;font-weight:600;min-width:50px;}
        .endpoint .line .p{color:#e8e8e8;}
        .endpoint-note{font-size:11px;color:#888;margin-top:10px;}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center;padding:20px;}
        .modal-box{background:#fff;border:1px solid #ddd;border-radius:5px;padding:24px 26px;max-width:440px;width:100%;}
        .modal-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #eee;margin-bottom:18px;}
        .modal-head h3{font-size:16px;font-weight:600;color:#222;}
        .modal-head .close{font-size:22px;color:#999;cursor:pointer;line-height:1;background:none;border:none;}
        .modal-head .close:hover{color:#222;}
        .modal-actions{display:flex;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid #eee;}
        .modal-actions .btn{flex:1;justify-content:center;}
        .foot-bar{margin-top:14px;padding-top:16px;border-top:1px solid #eee;display:flex;justify-content:space-between;font-size:11px;color:#999;}
        @media (max-width: 1024px){
            .stats{grid-template-columns:repeat(2,1fr);}
            .form-row{grid-template-columns:1fr;}
        }
        @media (max-width: 768px){
            .sidebar{transform:translateX(-100%);}
            .sidebar.open{transform:translateX(0);}
            .menu-btn{display:flex;align-items:center;justify-content:center;}
            .main{margin-left:0;}
            .topbar{padding:0 16px;}
            .content{padding:16px 16px 30px;}
            .stats{grid-template-columns:1fr 1fr;gap:10px;}
            .stat{padding:12px 14px;}
            .stat .num{font-size:18px;}
            .card{padding:16px 16px;}
            .page-title h1{font-size:18px;}
            .foot-bar{flex-direction:column;gap:4px;text-align:center;}
        }
        @media (max-width: 480px){
            .stats{grid-template-columns:1fr;}
            table thead th,table tbody td{padding:6px 8px;font-size:12px;}
            .topbar .secure{display:none;}
        }
    </style>
</head>
<body>

    <!-- ✅ DISCLAIMER POPUP (Dashboard) -->
    <div id="disclaimerPopup" style="position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;opacity:1;transition:opacity 0.4s;">
        <div style="background:#fff;max-width:560px;width:100%;border-radius:8px;padding:26px 24px;border:1px solid #ddd;box-shadow:0 10px 40px rgba(0,0,0,0.25);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #eee;">
                <span style="font-size:22px;">⚠️</span>
                <strong style="font-size:15px;color:#c0392b;">DISCLAIMER</strong>
            </div>
            <p style="font-size:13px;color:#333;line-height:1.65;">
                This website is strictly for <strong>educational, informational, and demonstration purposes only</strong>. The API is demonstrated using sample/dummy data. We do not support unauthorized access, privacy violations, stalking, harassment, fraud, or misuse of personal information. Do not use this API to target, identify, track, harass, or harm any individual. Users are responsible for complying with applicable laws, privacy regulations, API terms, and Google policies. No real person's private information is intentionally disclosed.
            </p>
            <div style="margin-top:16px;text-align:right;font-size:11px;color:#999;">
                <i class="fas fa-clock"></i> Auto closing in <span id="discCount">2</span>s...
            </div>
        </div>
    </div>
    <script>
        (function(){
            let c = 2;
            const el = document.getElementById('discCount');
            const t = setInterval(()=>{ c--; if(el) el.textContent = c; if(c<=0) clearInterval(t); }, 1000);
            setTimeout(()=>{
                const p = document.getElementById('disclaimerPopup');
                if(p){ p.style.opacity='0'; setTimeout(()=>p.remove(), 400); }
            }, 2000);
        })();
    </script>

    <div class="overlay" id="overlay"></div>
    <button class="menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="icon">V</div>
            <div class="text">
                <strong>Admin Panel</strong>
                <small>Number Info API</small>
            </div>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#create-section"><i class="fas fa-plus-circle"></i> Create Key</a>
            <a href="#keys-section"><i class="fas fa-key"></i> API Keys</a>
            <a href="#endpoint-section"><i class="fas fa-code"></i> Endpoint</a>
            <div class="nav-label">System</div>
            <a href="?reset_daily=1"><i class="fas fa-sync-alt"></i> Reset Daily</a>
            <a href="?action=logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </nav>
        <div class="foot"><i class="fas fa-shield-alt"></i> Secure Session</div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="crumb">Admin / <strong>Dashboard</strong></div>
            <div class="right">
                <span class="secure"><i class="fas fa-circle" style="font-size:8px;"></i> Secure</span>
                <a href="?action=logout" class="logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </header>

        <div class="content">

            <div class="page-title">
                <h1>API Management</h1>
                <p>Manage API keys, limits, expiry and access control for the Number Info service.</p>
            </div>

            <?php if($msg): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <i class="fas <?php echo $msgType==='error'?'fa-exclamation-circle':'fa-check-circle'; ?>"></i>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat">
                    <div class="ico"><i class="fas fa-key"></i></div>
                    <div><div class="num"><?php echo count($keys); ?></div><div class="lbl">Total Keys</div></div>
                </div>
                <div class="stat">
                    <div class="ico"><i class="fas fa-calendar-day"></i></div>
                    <div><div class="num"><?php echo $todayUsage; ?></div><div class="lbl">Today</div></div>
                </div>
                <div class="stat">
                    <div class="ico"><i class="fas fa-chart-bar"></i></div>
                    <div><div class="num"><?php echo $totalUsage; ?></div><div class="lbl">Total Requests</div></div>
                </div>
                <div class="stat">
                    <div class="ico"><i class="fas fa-check-circle"></i></div>
                    <div><div class="num"><?php echo $activeKeys; ?></div><div class="lbl">Active</div></div>
                </div>
            </div>

            <div class="card" id="create-section">
                <div class="card-head">
                    <div class="ico"><i class="fas fa-plus-circle"></i></div>
                    <h3>Create New API Key</h3>
                    <span class="sub">Generate a new access credential</span>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label>API Key Text <span class="req">*</span></label>
                        <input type="text" name="key_text" placeholder="Enter a unique API key" required>
                    </div>
                    <label style="font-size:12px;font-weight:600;color:#333;display:block;margin-bottom:6px;">Service Type</label>
                    <div class="service-fixed"><i class="fas fa-phone"></i> NUMBER INFO (Fixed)</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Daily Limit</label>
                            <input type="number" name="daily_limit" value="100" min="0">
                            <small>0 = unlimited</small>
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date">
                            <small>Leave blank for no expiry</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Create Key</button>
                </form>
            </div>

            <div class="card" id="keys-section">
                <div class="card-head">
                    <div class="ico"><i class="fas fa-list-alt"></i></div>
                    <h3>API Key Registry</h3>
                    <span class="sub">Manage all issued credentials</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>API Key</th><th>Limit</th><th>Today</th><th>Total</th><th>Created</th><th>Expiry</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($keys as $k):
                                $st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE api_key_id = ? AND date(used_at) = date('now')");
                                $st->execute([$k['id']]);
                                $uToday = (int)$st->fetchColumn();
                                $st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE api_key_id = ?");
                                $st->execute([$k['id']]);
                                $tUsed = (int)$st->fetchColumn();
                                $isExpired = ($k['expiry_date'] && strtotime($k['expiry_date']) < time());
                                $hasExpiry = !empty($k['expiry_date']);
                            ?>
                            <tr>
                                <td><span style="font-weight:600;color:#999;">#<?php echo $k['id']; ?></span></td>
                                <td><span class="key"><?php echo htmlspecialchars($k['key_text']); ?></span></td>
                                <td><?php echo $k['daily_limit']==0 ? '∞' : $k['daily_limit']; ?></td>
                                <td><span class="badge <?php echo $uToday>0?'badge-ok':'badge-neutral'; ?>"><?php echo $uToday; ?></span></td>
                                <td><?php echo $tUsed; ?></td>
                                <td><span class="badge badge-neutral"><?php echo date('d M Y', strtotime($k['created_at'])); ?></span></td>
                                <td>
                                    <?php if($isExpired): ?><span class="badge badge-bad">Expired</span>
                                    <?php elseif($hasExpiry): ?><span class="badge badge-info"><?php echo date('d M Y', strtotime($k['expiry_date'])); ?></span>
                                    <?php else: ?><span class="badge badge-neutral">Never</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if($k['active']): ?><span class="badge badge-ok"><i class="fas fa-check"></i> Active</span>
                                    <?php else: ?><span class="badge badge-bad"><i class="fas fa-ban"></i> Revoked</span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="abtn abtn-edit" onclick="openEdit(<?php echo $k['id']; ?>, <?php echo $k['daily_limit']; ?>, '<?php echo $k['expiry_date']; ?>')"><i class="fas fa-edit"></i> Edit</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                                            <button class="abtn abtn-toggle" type="submit"><i class="fas <?php echo $k['active']?'fa-ban':'fa-check'; ?>"></i> <?php echo $k['active']?'Revoke':'Activate'; ?></button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this API key permanently?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                                            <button class="abtn abtn-del" type="submit"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($keys)): ?>
                            <tr><td colspan="9" style="text-align:center;padding:26px;color:#999;">No API keys yet. Create one above.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" id="endpoint-section">
                <div class="card-head">
                    <div class="ico"><i class="fas fa-code"></i></div>
                    <h3>API Endpoint</h3>
                    <span class="sub">Number Info service</span>
                </div>
                <?php
                $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
                $base = rtrim($base, '/');
                ?>
                <div class="endpoint">
                    <span class="lbl">Base URL: <?php echo $base; ?></span>
                    <div class="line">
                        <span class="m">GET</span>
                        <span class="p"><?php echo $base; ?>/api.php?key=YOUR_KEY&num=9876543210</span>
                    </div>
                </div>
                <div class="endpoint-note"><i class="fas fa-info-circle"></i> Replace YOUR_KEY with a valid API key. Number must be 10 digits.</div>
            </div>

            <div class="foot-bar">
                <span>&copy; 2026 Number Info API</span>
                <span><i class="fas fa-shield-alt"></i> Secure Administration Panel</span>
            </div>

        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-box">
            <div class="modal-head">
                <h3><i class="fas fa-edit" style="color:#555;margin-right:8px;"></i> Edit API Key</h3>
                <button class="close" onclick="closeEdit()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Daily Limit</label>
                    <input type="number" name="daily_limit" id="edit_limit" min="0">
                    <small>0 = unlimited</small>
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry">
                    <small>Leave blank for no expiry</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEdit(id, limit, expiry) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_limit').value = limit;
            document.getElementById('edit_expiry').value = expiry || '';
            document.getElementById('editModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeEdit() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        window.onclick = function(e) {
            if (e.target == document.getElementById('editModal')) closeEdit();
        }
        document.getElementById('menuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('active');
        });
        document.getElementById('overlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('active');
        });
        document.querySelectorAll('.sidebar nav a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if(target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    document.getElementById('sidebar').classList.remove('open');
                    document.getElementById('overlay').classList.remove('active');
                }
            });
        });
    </script>

</body>
</html>