<?php
require_once __DIR__ . '/db.php';
session_start();

$msg = ''; $msgType = 'error';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare("SELECT * FROM users WHERE mobile = ? OR email = ?");
    $st->execute([$mobile, $mobile]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if($user && password_verify($password, $user['password_hash'])){
        if($user['active'] != 1){
            $msg = 'Your account is disabled. Please contact support.';
        } else {
            // Update last login IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $update = $pdo->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
            $update->execute([$ip, $user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $msg = 'Login successful. (User panel disabled)';
            // Removed redirect as user dashboard is deleted
        }
    } else {
        $msg = 'Invalid mobile number or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Premium API Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f0f4f8; display: flex; min-height: 100vh; }
        
        .split-layout { display: flex; width: 100%; }
        
        .left-side { flex: 1; background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); display: flex; flex-direction: column; justify-content: center; padding: 4rem; }
        .brand-badge { display: inline-flex; align-items: center; gap: 8px; background: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; color: #ff6b6b; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 32px; font-size: 14px; width: max-content; }
        .hero-title { font-size: 48px; color: #1a202c; line-height: 1.2; margin-bottom: 24px; font-weight: 800; }
        .hero-title span { color: #f25719; }
        .hero-title span.green { color: #038a39; }
        .hero-subtitle { color: #4a5568; font-size: 18px; line-height: 1.6; margin-bottom: 48px; max-width: 480px; }
        
        .feature-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); max-width: 480px; }
        .feature-icon { width: 48px; height: 48px; border-radius: 12px; background: #fff5f5; color: #f56565; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .feature-icon.blue { background: #ebf8ff; color: #3182ce; }
        .feature-icon.green { background: #f0fff4; color: #38a169; }
        .feature-text h4 { color: #2d3748; margin-bottom: 4px; font-size: 16px; }
        .feature-text p { color: #718096; font-size: 13px; }

        .right-side { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; background: #fff; }
        .form-card { background: white; width: 100%; max-width: 420px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-top: 5px solid #d35400; position: relative; }
        .icon-top { position: absolute; top: -24px; left: 50%; transform: translateX(-50%); width: 48px; height: 48px; background: #000080; color: white; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 20px; box-shadow: 0 4px 10px rgba(0,0,128,0.3); }
        .form-header { margin-bottom: 32px; text-align: center; margin-top: 10px; }
        .form-header h2 { color: #1a202c; font-size: 28px; margin-bottom: 8px; }
        .form-header p { color: #f25719; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; background: #fff5f5; color: #c53030; border-left: 4px solid #e53e3e; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #4a5568; text-transform: uppercase; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #a0aec0; }
        .form-control { width: 100%; padding: 14px 14px 14px 42px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; color: #2d3748; transition: all 0.3s; background: #f8fafc; }
        .form-control:focus { outline: none; border-color: #d35400; background: #fff; box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.1); }
        
        .btn-primary { width: 100%; padding: 16px; background: #038a39; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-primary:hover { background: #026b2c; }
        
        .login-link { text-align: center; margin-top: 24px; font-size: 14px; color: #718096; }
        .login-link a { color: #000080; font-weight: bold; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .split-layout { flex-direction: column; }
            .left-side { padding: 2rem; }
        }
    </style>
</head>
<body>
    <div class="split-layout">
        <div class="left-side">
            <div class="brand-badge">
                <i class="fas fa-star"></i> PREMIUM API PORTAL
            </div>
            <h1 class="hero-title">Empowering<br><span class="green">Digital</span> <span>India</span></h1>
            <p class="hero-subtitle">Experience India's most trusted API network. Get lightning-fast verification, highly secure endpoints, and instant wallet funding for your business.</p>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                <div class="feature-text">
                    <h4>FAST APIS</h4>
                    <p>99.9% Uptime SLA</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon blue"><i class="fas fa-shield-alt"></i></div>
                <div class="feature-text">
                    <h4>100% SECURE</h4>
                    <p>Encrypted Network</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon green"><i class="fas fa-wallet"></i></div>
                <div class="feature-text">
                    <h4>SMART WALLET</h4>
                    <p>Instant Auto-Refunds</p>
                </div>
            </div>
        </div>
        
        <div class="right-side">
            <div class="form-card">
                <div class="icon-top">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>LOGIN TO PREMIUM API</p>
                </div>
                
                <?php if($msg): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
                </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Mobile Number / Email</label>
                        <div class="input-group">
                            <i class="fas fa-phone-alt"></i>
                            <input type="text" name="mobile" class="form-control" placeholder="Enter mobile or email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between;">
                            <label>Password</label>
                            <label style="color: #000080; cursor: pointer; text-transform: none;">Forgot Password?</label>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">SECURE LOGIN <i class="fas fa-arrow-right"></i></button>
                    
                    <div class="login-link">
                        Naye user hain? <a href="register.php">ACCOUNT BANAYEIN</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
