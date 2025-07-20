<?php
// login.php - หน้าเข้าสู่ระบบ
define('SECURE_ACCESS', true);
require_once 'settings.php';

// ตรวจสอบว่าล็อกอินแล้วหรือยัง
if (checkAuthentication()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$success_message = '';

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // ตรวจสอบ CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Invalid security token. Please try again.';
    } else if (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $pdo = getDBConnection();
            
            // ตรวจสอบการล็อกบัญชี
            $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, user_type, status, login_attempts, locked_until FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // ตรวจสอบว่าบัญชีถูกล็อกหรือไม่
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error_message = 'บัญชีถูกล็อก กรุณาลองใหม่ในภายหลัง';
                } else if ($user['status'] !== 'active') {
                    $error_message = 'บัญชีไม่ได้เปิดใช้งาน';
                } else {
                    // ตรวจสอบรหัสผ่าน
                    if (verifyPassword($password, $user['password_hash'])) {
                        // เข้าสู่ระบบสำเร็จ
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        // รีเซ็ตการนับความผิดพลาด
                        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // บันทึก log
                        writeLog('INFO', "User login successful", ['user_id' => $user['id'], 'username' => $user['username']]);
                        
                        // ไปยังหน้าแรก
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        // รหัสผ่านผิด
                        $login_attempts = $user['login_attempts'] + 1;
                        
                        if ($login_attempts >= MAX_LOGIN_ATTEMPTS) {
                            // ล็อกบัญชี
                            $locked_until = date('Y-m-d H:i:s', time() + LOGIN_BLOCK_TIME);
                            $stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                            $stmt->execute([$login_attempts, $locked_until, $user['id']]);
                            $error_message = 'บัญชีถูกล็อกเนื่องจากเข้าสู่ระบบผิดหลายครั้ง';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                            $stmt->execute([$login_attempts, $user['id']]);
                            $remaining = MAX_LOGIN_ATTEMPTS - $login_attempts;
                            $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลือ {$remaining} ครั้ง)";
                        }
                        
                        writeLog('WARNING', "Failed login attempt", ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
                    }
                }
            } else {
                $error_message = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                writeLog('WARNING', "Login attempt with non-existent user", ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
            }
        } catch (Exception $e) {
            writeLog('ERROR', "Login error: " . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .blockchain-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
        }
        
        .input-group {
            margin-bottom: 1.5rem;
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 15px 0 0 15px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 15px 15px 0;
        }
        
        .feature-list {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .feature-item:last-child {
            margin-bottom: 0;
        }
        
        .feature-icon {
            color: #667eea;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid login-container">
        <div class="row w-100 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="bi bi-shield-lock blockchain-icon"></i>
                        <h4 class="mb-0">ระบบบล็อกเชน</h4>
                        <p class="mb-0 opacity-75">ป้องกันการเปลี่ยนแปลงข้อมูลการศึกษา</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="username" 
                                       placeholder="ชื่อผู้ใช้หรืออีเมล" 
                                       required 
                                       autocomplete="username"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       name="password" 
                                       placeholder="รหัสผ่าน" 
                                       required 
                                       autocomplete="current-password">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    เข้าสู่ระบบ
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="register.php" class="text-decoration-none">
                                <i class="bi bi-person-plus me-1"></i>
                                สมัครสมาชิก
                            </a>
                        </div>
                        
                        <div class="feature-list">
                            <h6 class="text-center mb-3">
                                <i class="bi bi-star-fill text-warning me-2"></i>
                                คุณสมบัติของระบบ
                            </h6>
                            
                            <div class="feature-item">
                                <i class="bi bi-shield-check feature-icon"></i>
                                <small>ป้องกันการแก้ไขข้อมูลด้วยบล็อกเชน</small>
                            </div>
                            
                            <div class="feature-item">
                                <i class="bi bi-eye feature-icon"></i>
                                <small>ความโปร่งใสในการตรวจสอบ</small>
                            </div>
                            
                            <div class="feature-item">
                                <i class="bi bi-lightning feature-icon"></i>
                                <small>API สำหรับเชื่อมต่อระบบภายนอก</small>
                            </div>
                            
                            <div class="feature-item">
                                <i class="bi bi-cloud-check feature-icon"></i>
                                <small>เก็บข้อมูลอย่างปลอดภัย</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ป้องกันการส่งฟอร์มซ้ำ
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>กำลังเข้าสู่ระบบ...';
        });
        
        // Auto focus บน username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });
        
        // เอฟเฟกต์พิเศษสำหรับการพิมพ์
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('shadow');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('shadow');
            });
        });
    </script>
</body>
</html>