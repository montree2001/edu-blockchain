<?php
// register.php - หน้าลงทะเบียน
require_once 'config.php';

// ถ้าเข้าสู่ระบบแล้ว ให้ไปหน้า dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$errors = [];

// ตรวจสอบการ submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $user_type = $_POST['user_type'] ?? 'api_user';
    
    // ตรวจสอบข้อมูล
    if (empty($username)) {
        $errors['username'] = 'กรุณากรอกชื่อผู้ใช้';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
    }
    
    if (empty($email)) {
        $errors['email'] = 'กรุณากรอกอีเมล';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    
    if (empty($password)) {
        $errors['password'] = 'กรุณากรอกรหัสผ่าน';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'รหัสผ่านไม่ตรงกัน';
    }
    
    if (empty($full_name)) {
        $errors['full_name'] = 'กรุณากรอกชื่อ-นามสกุล';
    }
    
    if (empty($institution)) {
        $errors['institution'] = 'กรุณากรอกชื่อสถาบัน';
    }
    
    // ตรวจสอบว่าชื่อผู้ใช้หรืออีเมลซ้ำหรือไม่
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
            } else {
                // สร้างบัญชีใหม่
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $api_key = generateApiKey();
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, institution, user_type, api_key) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$username, $email, $hashed_password, $full_name, $institution, $user_type, $api_key])) {
                    header("Location: login.php?message=registered");
                    exit();
                } else {
                    $error_message = 'เกิดข้อผิดพลาดในการสร้างบัญชี';
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .register-card {
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
            <div class="col-md-8 col-lg-6">
                <div class="card register-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="brand-logo">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <h2 class="card-title mb-1">ลงทะเบียน</h2>
                            <p class="text-muted">สร้างบัญชีสำหรับเข้าใช้งานระบบ</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person"></i>
                                        </span>
                                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                               id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                        <?php if (isset($errors['username'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">อีเมล</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                               id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-badge"></i>
                                    </span>
                                    <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                           id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="institution" class="form-label">ชื่อสถาบัน</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-building"></i>
                                    </span>
                                    <input type="text" class="form-control <?php echo isset($errors['institution']) ? 'is-invalid' : ''; ?>" 
                                           id="institution" name="institution" value="<?php echo htmlspecialchars($_POST['institution'] ?? ''); ?>" required>
                                    <?php if (isset($errors['institution'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['institution']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="user_type" class="form-label">ประเภทผู้ใช้</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-shield-check"></i>
                                    </span>
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="api_user" <?php echo ($_POST['user_type'] ?? 'api_user') === 'api_user' ? 'selected' : ''; ?>>
                                            ผู้ใช้ API ทั่วไป
                                        </option>
                                        <option value="institution" <?php echo ($_POST['user_type'] ?? '') === 'institution' ? 'selected' : ''; ?>>
                                            สถาบันการศึกษา
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">รหัสผ่าน</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                               id="password" name="password" required>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="confirm_password" name="confirm_password" required>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-person-plus me-2"></i>
                                    สร้างบัญชี
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-0">มีบัญชีแล้ว? 
                                <a href="login.php" class="text-decoration-none">เข้าสู่ระบบ</a>
                            </p>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                การลงทะเบียนจะสร้าง API Key อัตโนมัติสำหรับการเชื่อมต่อระบบ
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ตรวจสอบรหัสผ่านตรงกันหรือไม่
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword !== '') {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (confirmPassword !== '') {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>