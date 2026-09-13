<?php
require_once __DIR__ . '/db.php';
session_start();

$msg = ''; $msgType = 'error';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if($name === '' || $mobile === '' || $password === ''){
        $msg = 'Please fill all required fields.';
    } elseif($password !== $confirm) {
        $msg = 'Passwords do not match.';
    } else {
        $st = $pdo->prepare("SELECT id FROM users WHERE mobile = ? OR email = ?");
        $st->execute([$mobile, $email]);
        if($st->fetch()){
            $msg = 'Mobile number or Email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare("INSERT INTO users (name, mobile, email, password_hash) VALUES (?, ?, ?, ?)");
            $st->execute([$name, $mobile, $email, $hash]);
            
            // Auto login after register
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $msg = 'Registration successful. (User panel disabled)';
            // Removed redirect as user dashboard is deleted
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Premium API Portal</title>
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
        .form-card { background: white; width: 100%; max-width: 500px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-top: 5px solid #038a39; }
        .form-header { margin-bottom: 32px; }
        .form-header h2 { color: #1a202c; font-size: 28px; margin-bottom: 8px; }
        .form-header p { color: #a0aec0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; background: #fff5f5; color: #c53030; border-left: 4px solid #e53e3e; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #4a5568; text-transform: uppercase; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #a0aec0; }
        .form-control { width: 100%; padding: 14px 14px 14px 42px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; color: #2d3748; transition: all 0.3s; background: #f8fafc; }
        .form-control:focus { outline: none; border-color: #038a39; background: #fff; box-shadow: 0 0 0 3px rgba(3, 138, 57, 0.1); }
        
        .btn-primary { width: 100%; padding: 16px; background: #d35400; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-primary:hover { background: #b04300; }
        
        .login-link { text-align: center; margin-top: 24px; font-size: 14px; color: #718096; }
        .login-link a { color: #000080; font-weight: bold; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .split-layout { flex-direction: column; }
            .left-side { padding: 2rem; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="split-layout">
        <div class="left-side">
            <div class="brand-badge">
                <i class="fas fa-star"></i> PREMIUM API PORTAL
            </div>
            <h1 class="hero-title">Join India's<br>Largest <span>Partner</span><br><span class="green">Network</span></h1>
            <p class="hero-subtitle">Start your journey with India's most trusted digital services platform. Access premium APIs and grow your business exponentially.</p>
            
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
                <div class="form-header">
                    <h2>Create Account</h2>
                    <p>FILL DETAILS TO SETUP WORKSPACE</p>
                </div>
                
                <?php if($msg): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
                </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Full Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="name" class="form-control" placeholder="Enter your name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <div class="input-group">
                                <i class="fas fa-phone-alt"></i>
                                <input type="text" name="mobile" class="form-control" placeholder="10-digit number" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="name@example.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Create Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm" class="form-control" placeholder="Re-enter password" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">COMPLETE REGISTRATION <i class="fas fa-arrow-right"></i></button>
                    
                    <div class="login-link">
                        Already have a partner account? <a href="login.php">LOG IN HERE</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
