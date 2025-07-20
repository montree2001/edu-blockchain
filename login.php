<?php
// login.php - หน้าเข้าสู่ระบบ
require_once 'config.php';

// ถ้าเข้าสู่ระบบแล้ว ให้ไปหน้า dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

// ตรวจสอบการ submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // เข้าสู่ระบบสำเร็จ
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['institution'] = $user['institution'];
                
                // บันทึก log
                logActivity($user['id'], 'login', 'User logged in successfully');
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ';
        }
    }
}

// ตรวจสอบข้อความจาก URL parameters
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'registered') {
        $success_message = 'ลงทะเบียนสำเร็จ กรุณาเข้าสู่ระบบ';
    } elseif ($_GET['message'] === 'logout') {
        $success_message = 'ออกจากระบบสำเร็จ';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="brand-logo">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h2 class="card-title mb-1">เข้าสู่ระบบ</h2>
                            <p class="text-muted"><?php echo SITE_NAME; ?></p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    เข้าสู่ระบบ
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-0">ยังไม่มีบัญชี? 
                                <a href="register.php" class="text-decoration-none">สมัครสมาชิก</a>
                            </p>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                ระบบบล็อกเชนสำหรับป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษา
                            </small>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลการใช้งานตัวอย่าง -->
                <div class="card mt-3" style="background: rgba(255, 255, 255, 0.9);">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-person-circle me-2"></i>
                            บัญชีทดสอบ
                        </h6>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">ผู้ดูแลระบบ:</small><br>
                                <small><strong>admin@system.com</strong></small><br>
                                <small>รหัส: password</small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">สถาบันการศึกษา:</small><br>
                                <small><strong>contact@universitya.ac.th</strong></small><br>
                                <small>รหัส: password</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
    </script>
</body>
</html>