<?php
// register.php - หน้าลงทะเบียน
define('SECURE_ACCESS', true);
require_once 'settings.php';

// ตรวจสอบว่าล็อกอินแล้วหรือยัง
if (checkAuthentication()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$success_message = '';
$form_data = [];

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
        'organization' => sanitizeInput($_POST['organization'] ?? ''),
        'user_type' => sanitizeInput($_POST['user_type'] ?? 'api_user')
    ];
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $terms_accepted = isset($_POST['terms_accepted']);
    
    // ตรวจสอบ CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Invalid security token. Please try again.';
    } else if (empty($form_data['username']) || empty($form_data['email']) || empty($form_data['full_name']) || empty($password)) {
        $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else if (strlen($form_data['username']) < 3 || strlen($form_data['username']) > 50) {
        $error_message = 'ชื่อผู้ใช้ต้องมีความยาว 3-50 ตัวอักษร';
    } else if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message = 'รูปแบบอีเมลไม่ถูกต้อง';
    } else if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'รหัสผ่านต้องมีความยาวอย่างน้อย ' . PASSWORD_MIN_LENGTH . ' ตัวอักษร';
    } else if ($password !== $confirm_password) {
        $error_message = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } else if (!$terms_accepted) {
        $error_message = 'กรุณายอมรับข้อกำหนดและเงื่อนไขการใช้งาน';
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $error_message = 'ชื่อผู้ใช้สามารถใช้ได้เฉพาะตัวอักษร ตัวเลข และ _ เท่านั้น';
    } else {
        // ตรวจสอบความแข็งแรงของรหัสผ่าน
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            $error_message = 'รหัสผ่านต้องประกอบด้วยตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ และตัวเลข';
        } else {
            try {
                $pdo = getDBConnection();
                
                // ตรวจสอบว่าชื่อผู้ใช้หรืออีเมลมีอยู่แล้วหรือไม่
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$form_data['username'], $form_data['email']]);
                
                if ($stmt->fetch()) {
                    $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
                } else {
                    // สร้างรหัสยืนยันอีเมล
                    $email_verification_token = generateSecureToken();
                    
                    // เข้ารหัสรหัสผ่าน
                    $password_hash = hashPassword($password);
                    
                    // บันทึกข้อมูลผู้ใช้ใหม่
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            username, email, password_hash, full_name, organization, 
                            user_type, email_verification_token, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    
                    $result = $stmt->execute([
                        $form_data['username'],
                        $form_data['email'],
                        $password_hash,
                        $form_data['full_name'],
                        $form_data['organization'],
                        $form_data['user_type'],
                        $email_verification_token
                    ]);
                    
                    if ($result) {
                        $new_user_id = $pdo->lastInsertId();
                        
                        // บันทึก log
                        writeLog('INFO', "New user registered", [
                            'user_id' => $new_user_id, 
                            'username' => $form_data['username'],
                            'email' => $form_data['email']
                        ]);
                        
                        // ส่งอีเมลยืนยัน (ถ้าเปิดใช้งาน)
                        if (NOTIFICATION_EMAIL_ENABLED) {
                            $verification_link = SITE_URL . "/verify_email.php?token=" . $email_verification_token;
                            $email_subject = "ยืนยันอีเมลสำหรับ " . SITE_NAME;
                            $email_message = "
                                <h2>ยินดีต้อนรับสู่ " . SITE_NAME . "</h2>
                                <p>สวัสดี " . htmlspecialchars($form_data['full_name']) . ",</p>
                                <p>กรุณาคลิกลิงก์ด้านล่างเพื่อยืนยันอีเมลของคุณ:</p>
                                <p><a href='{$verification_link}' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ยืนยันอีเมล</a></p>
                                <p>หรือคัดลอกลิงก์นี้ไปที่เบราว์เซอร์: {$verification_link}</p>
                                <p>ขอบคุณที่ใช้บริการของเรา</p>
                            ";
                            
                            sendNotificationEmail($form_data['email'], $email_subject, $email_message);
                        }
                        
                        $success_message = 'สมัครสมาชิกสำเร็จ! กรุณาตรวจสอบอีเมลเพื่อยืนยันบัญชี';
                        $form_data = []; // ล้างข้อมูลฟอร์ม
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการสมัครสมาชิก';
                    }
                }
            } catch (Exception $e) {
                writeLog('ERROR', "Registration error: " . $e->getMessage());
                $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 600px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
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
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }
        
        .form-floating > label {
            padding: 1rem 1.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid register-container">
        <div class="row w-100 justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card">
                    <div class="register-header">
                        <i class="bi bi-person-plus blockchain-icon"></i>
                        <h4 class="mb-0">สมัครสมาชิก</h4>
                        <p class="mb-0 opacity-75">เข้าร่วมระบบบล็อกเชนป้องกันข้อมูลการศึกษา</p>
                    </div>
                    
                    <div class="register-body">
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
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success btn-sm">ไปที่หน้าเข้าสู่ระบบ</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" id="registerForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="username"
                                                   name="username" 
                                                   placeholder="ชื่อผู้ใช้"
                                                   required 
                                                   pattern="[a-zA-Z0-9_]{3,50}"
                                                   value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>">
                                            <label for="username">ชื่อผู้ใช้ *</label>
                                            <div class="form-text">ใช้ได้เฉพาะ a-z, A-Z, 0-9, _ (3-50 ตัวอักษร)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email"
                                                   name="email" 
                                                   placeholder="อีเมล"
                                                   required 
                                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                                            <label for="email">อีเมล *</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           id="full_name"
                                           name="full_name" 
                                           placeholder="ชื่อ-สกุล"
                                           required 
                                           value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>">
                                    <label for="full_name">ชื่อ-สกุล *</label>
                                </div>
                                
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           id="organization"
                                           name="organization" 
                                           placeholder="หน่วยงาน/สถาบัน"
                                           value="<?php echo htmlspecialchars($form_data['organization'] ?? ''); ?>">
                                    <label for="organization">หน่วยงาน/สถาบัน</label>
                                </div>
                                
                                <div class="form-floating">
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="api_user" <?php echo ($form_data['user_type'] ?? 'api_user') === 'api_user' ? 'selected' : ''; ?>>ผู้ใช้ API</option>
                                        <option value="institution" <?php echo ($form_data['user_type'] ?? '') === 'institution' ? 'selected' : ''; ?>>สถาบันการศึกษา</option>
                                    </select>
                                    <label for="user_type">ประเภทผู้ใช้ *</label>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password"
                                                   name="password" 
                                                   placeholder="รหัสผ่าน"
                                                   required 
                                                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                            <label for="password">รหัสผ่าน *</label>
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <div class="form-text">อย่างน้อย <?php echo PASSWORD_MIN_LENGTH; ?> ตัวอักษร ประกอบด้วย a-z, A-Z, 0-9</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password"
                                                   name="confirm_password" 
                                                   placeholder="ยืนยันรหัสผ่าน"
                                                   required>
                                            <label for="confirm_password">ยืนยันรหัสผ่าน *</label>
                                            <div class="invalid-feedback" id="passwordMismatch">
                                                รหัสผ่านไม่ตรงกัน
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                                    <label class="form-check-label" for="terms_accepted">
                                        ฉันยอมรับ <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">ข้อกำหนดและเงื่อนไขการใช้งาน</a> *
                                    </label>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-register">
                                        <i class="bi bi-person-plus me-2"></i>
                                        สมัครสมาชิก
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>
                                กลับไปหน้าเข้าสู่ระบบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal สำหรับข้อกำหนดและเงื่อนไข -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ข้อกำหนดและเงื่อนไขการใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. การใช้งานระบบ</h6>
                    <p>ผู้ใช้ต้องใช้ระบบในทางที่ถูกต้องและไม่ทำลายระบบหรือข้อมูลของผู้อื่น</p>
                    
                    <h6>2. ความปลอดภัยของข้อมูล</h6>
                    <p>ผู้ใช้มีหน้าที่รักษาความปลอดภัยของข้อมูลเข้าสู่ระบบและ API Key ของตนเอง</p>
                    
                    <h6>3. การใช้งาน API</h6>
                    <p>การใช้งาน API ต้องเป็นไปตามขีดจำกัดที่กำหนดและไม่ส่งข้อมูลที่ผิดกฎหมาย</p>
                    
                    <h6>4. ความรับผิดชอบ</h6>
                    <p>ผู้ให้บริการไม่รับผิดชอบต่อความเสียหายที่เกิดจากการใช้งานระบบ</p>
                    
                    <h6>5. การเปลี่ยนแปลงข้อกำหนด</h6>
                    <p>ผู้ให้บริการสงวนสิทธิ์ในการเปลี่ยนแปลงข้อกำหนดโดยไม่ต้องแจ้งล่วงหน้า</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary" onclick="acceptTerms()">ยอมรับ</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ตรวจสอบความแข็งแรงของรหัสผ่าน
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const colors = ['#dc3545', '#fd7e14', '#ffc107', '#198754'];
            const widths = ['20%', '40%', '60%', '80%', '100%'];
            
            strengthBar.style.width = widths[strength - 1] || '0%';
            strengthBar.style.backgroundColor = colors[Math.min(strength - 1, 3)] || 'transparent';
        });
        
        // ตรวจสอบรหัสผ่านที่ยืนยัน
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // ป้องกันการส่งฟอร์มซ้ำ
        document.getElementById('registerForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>กำลังสมัครสมาชิก...';
        });
        
        // ฟังก์ชันยอมรับข้อกำหนด
        function acceptTerms() {
            document.getElementById('terms_accepted').checked = true;
            bootstrap.Modal.getInstance(document.getElementById('termsModal')).hide();
        }
        
        // ตรวจสอบชื่อผู้ใช้ในขณะพิมพ์
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const input = this;
            
            clearTimeout(usernameTimeout);
            
            if (username.length >= 3) {
                usernameTimeout = setTimeout(() => {
                    fetch('check_username.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'username=' + encodeURIComponent(username)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            input.classList.add('is-invalid');
                            input.nextElementSibling.nextElementSibling.textContent = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
                            input.nextElementSibling.nextElementSibling.style.color = '#dc3545';
                        } else {
                            input.classList.remove('is-invalid');
                            input.nextElementSibling.nextElementSibling.textContent = 'ใช้ได้เฉพาะ a-z, A-Z, 0-9, _ (3-50 ตัวอักษร)';
                            input.nextElementSibling.nextElementSibling.style.color = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }, 500);
            }
        });
    </script>
</body>
</html>