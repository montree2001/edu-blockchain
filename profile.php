<?php
// profile.php - หน้าโปรไฟล์ผู้ใช้
require_once 'config.php';

checkPermission();

$success_message = '';
$error_message = '';

// อัปเดตโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $institution = trim($_POST['institution'] ?? '');
        
        // ตรวจสอบข้อมูล
        $errors = [];
        if (empty($full_name)) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (empty($email)) $errors[] = 'กรุณากรอกอีเมล';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        
        // ตรวจสอบอีเมลซ้ำ
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $errors[] = 'อีเมลนี้ถูกใช้แล้ว';
                }
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, institution = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $institution, $_SESSION['user_id']]);
                
                // อัปเดต session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['institution'] = $institution;
                
                $success_message = 'อัปเดตโปรไฟล์สำเร็จ';
                logActivity($_SESSION['user_id'], 'profile_updated', 'Profile information updated');
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในการอัปเดตโปรไฟล์';
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // ตรวจสอบข้อมูล
        $errors = [];
        if (empty($current_password)) $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
        if (empty($new_password)) $errors[] = 'กรุณากรอกรหัสผ่านใหม่';
        if (strlen($new_password) < 6) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        if ($new_password !== $confirm_password) $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';
        
        // ตรวจสอบรหัสผ่านปัจจุบัน
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($current_password, $user['password'])) {
                    $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาดในการตรวจสอบรหัสผ่าน';
            }
        }
        
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                $success_message = 'เปลี่ยนรหัสผ่านสำเร็จ';
                logActivity($_SESSION['user_id'], 'password_changed', 'Password changed successfully');
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
}

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(er.id) as record_count,
               COUNT(al.id) as api_usage_count,
               MAX(al.created_at) as last_api_usage
        FROM users u
        LEFT JOIN educational_records er ON u.id = er.institution_id
        LEFT JOIN api_logs al ON u.id = al.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // ดึงข้อมูล API usage ล่าสุด
    $stmt = $pdo->prepare("
        SELECT endpoint, method, response_status, created_at
        FROM api_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_api_usage = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูลผู้ใช้';
    $user = null;
    $recent_api_usage = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 0;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar text-white">
        <div class="p-4">
            <h4 class="mb-0">
                <i class="bi bi-shield-check me-2"></i>
                Blockchain EDU
            </h4>
            <small class="opacity-75">Educational Data Protection</small>
        </div>
        
        <nav class="nav flex-column px-3">
            <a class="nav-link text-white" href="dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>
                หน้าหลัก
            </a>
            <a class="nav-link text-white" href="records.php">
                <i class="bi bi-file-earmark-text me-2"></i>
                ข้อมูลการศึกษา
            </a>
            <a class="nav-link text-white" href="blockchain-page.php">
                <i class="bi bi-diagram-3 me-2"></i>
                บล็อกเชน
            </a>
            <a class="nav-link text-white" href="api_management.php">
                <i class="bi bi-gear me-2"></i>
                จัดการ API
            </a>
            
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <hr class="border-light opacity-25">
            <a class="nav-link text-white" href="users.php">
                <i class="bi bi-people me-2"></i>
                จัดการผู้ใช้
            </a>
            <a class="nav-link text-white" href="system_logs.php">
                <i class="bi bi-list-ul me-2"></i>
                ระบบ Logs
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="mt-auto p-3">
            <div class="card bg-dark bg-opacity-25 border-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-light bg-opacity-25 rounded-circle p-2 me-3">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <small class="opacity-75"><?php echo htmlspecialchars($_SESSION['user_type']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white">
            <div class="container-fluid">
                <span class="navbar-brand mb-0 h1">โปรไฟล์</span>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php">
                                <i class="bi bi-person me-2"></i>โปรไฟล์
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="bi bi-gear me-2"></i>ตั้งค่า
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Profile Content -->
        <div class="container-fluid p-4">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($user): ?>
                <!-- Profile Header -->
                <div class="row mb-4">
                    <div class="col-lg-4">
                        <div class="card profile-card">
                            <div class="card-body text-center">
                                <div class="profile-avatar mb-3">
                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                </div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                                
                                <?php
                                $type_badges = [
                                    'admin' => 'bg-danger',
                                    'institution' => 'bg-primary',
                                    'api_user' => 'bg-secondary'
                                ];
                                $type_names = [
                                    'admin' => 'ผู้ดูแลระบบ',
                                    'institution' => 'สถาบันการศึกษา',
                                    'api_user' => 'ผู้ใช้ API'
                                ];
                                ?>
                                <span class="badge <?php echo $type_badges[$user['user_type']] ?? 'bg-secondary'; ?> mb-3">
                                    <?php echo $type_names[$user['user_type']] ?? $user['user_type']; ?>
                                </span>
                                
                                <div class="text-muted small">
                                    <div><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></div>
                                    <?php if ($user['institution']): ?>
                                        <div><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($user['institution']); ?></div>
                                    <?php endif; ?>
                                    <div><i class="bi bi-calendar me-2"></i>สมาชิกตั้งแต่ <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="bi bi-graph-up me-2"></i>
                                    สถิติการใช้งาน
                                </h5>
                                
                                <div class="row text-center">
                                    <div class="col-md-4 mb-3">
                                        <div>
                                            <h2 class="mb-1"><?php echo number_format($user['record_count']); ?></h2>
                                            <p class="mb-0">ข้อมูลการศึกษา</p>
                                            <small class="opacity-75">ที่บันทึกในระบบ</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div>
                                            <h2 class="mb-1"><?php echo number_format($user['api_usage_count']); ?></h2>
                                            <p class="mb-0">การใช้งาน API</p>
                                            <small class="opacity-75">ครั้งทั้งหมด</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div>
                                            <h2 class="mb-1">
                                                <?php echo $user['is_active'] ? 'เปิด' : 'ปิด'; ?>
                                            </h2>
                                            <p class="mb-0">สถานะบัญชี</p>
                                            <small class="opacity-75">
                                                <?php if ($user['last_api_usage']): ?>
                                                    ล่าสุด: <?php echo date('d/m/Y', strtotime($user['last_api_usage'])); ?>
                                                <?php else: ?>
                                                    ยังไม่เคยใช้ API
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Forms -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card profile-card">
                            <div class="card-header bg-transparent">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-gear me-2"></i>
                                    แก้ไขโปรไฟล์
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                        <input type="text" class="form-control" id="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                        <div class="form-text">ไม่สามารถเปลี่ยนแปลงชื่อผู้ใช้ได้</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">อีเมล</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="institution" class="form-label">สถาบัน</label>
                                        <input type="text" class="form-control" id="institution" name="institution" 
                                               value="<?php echo htmlspecialchars($user['institution'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="user_type" class="form-label">ประเภทผู้ใช้</label>
                                        <input type="text" class="form-control" id="user_type" 
                                               value="<?php echo $type_names[$user['user_type']] ?? $user['user_type']; ?>" readonly>
                                        <div class="form-text">ติดต่อผู้ดูแลระบบเพื่อเปลี่ยนประเภทผู้ใช้</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>บันทึกการเปลี่ยนแปลง
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card profile-card">
                            <div class="card-header bg-transparent">
                                <h5 class="mb-0">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    เปลี่ยนรหัสผ่าน
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-shield-check me-2"></i>เปลี่ยนรหัสผ่าน
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent API Usage -->
                <div class="card profile-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>
                            การใช้งาน API ล่าสุด
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_api_usage)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-journal-x display-4 text-muted"></i>
                                <p class="text-muted mt-2">ยังไม่มีการใช้งาน API</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Endpoint</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>วันที่/เวลา</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_api_usage as $usage): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($usage['endpoint']); ?></code></td>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($usage['method']); ?></span></td>
                                            <td>
                                                <?php
                                                $status = $usage['response_status'];
                                                $badge_class = 'bg-secondary';
                                                if ($status >= 200 && $status < 300) $badge_class = 'bg-success';
                                                elseif ($status >= 400 && $status < 500) $badge_class = 'bg-warning';
                                                elseif ($status >= 500) $badge_class = 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td><small><?php echo date('d/m/Y H:i:s', strtotime($usage['created_at'])); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="api_management.php" class="btn btn-outline-primary">
                                    ดู API Logs ทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    ไม่สามารถโหลดข้อมูลโปรไฟล์ได้
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>