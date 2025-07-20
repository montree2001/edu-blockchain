<?php
// api_management.php - หน้าจัดการ API
require_once 'config.php';

checkPermission();

$success_message = '';
$error_message = '';

// การสร้าง API Key ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate_api_key') {
    try {
        $new_api_key = generateApiKey();
        $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
        $stmt->execute([$new_api_key, $_SESSION['user_id']]);
        
        $success_message = 'สร้าง API Key ใหม่สำเร็จ';
        logActivity($_SESSION['user_id'], 'api_key_regenerated', 'API Key regenerated');
    } catch (Exception $e) {
        $error_message = 'เกิดข้อผิดพลาดในการสร้าง API Key ใหม่';
    }
}

// ดึงข้อมูล API Key ปัจจุบัน
try {
    $stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    $current_api_key = $user_data['api_key'];
    
    // ดึง API Logs
    $stmt = $pdo->prepare("
        SELECT * FROM api_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $api_logs = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ API - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" rel="stylesheet">
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
        .api-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.9rem;
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
            <a class="nav-link text-white" href="blockchain.php">
                <i class="bi bi-diagram-3 me-2"></i>
                บล็อกเชน
            </a>
            <a class="nav-link text-white active" href="api_management.php">
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
                <span class="navbar-brand mb-0 h1">จัดการ API</span>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
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

        <!-- API Management Content -->
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

            <!-- API Key Management -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card api-card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="bi bi-key me-2"></i>
                                API Key Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">API Key ของคุณ:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control font-monospace" 
                                               id="apiKeyInput" value="<?php echo htmlspecialchars($current_api_key); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()">
                                            <i class="bi bi-eye" id="toggleIcon"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" type="button" onclick="copyApiKey()">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        เก็บ API Key นี้ไว้อย่างปลอดภัย อย่าแชร์ให้ผู้อื่น
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="regenerate_api_key">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะสร้าง API Key ใหม่? API Key เดิมจะไม่สามารถใช้งานได้อีก')">
                                            <i class="bi bi-arrow-clockwise me-2"></i>
                                            สร้าง API Key ใหม่
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Documentation -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card api-card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="bi bi-book me-2"></i>
                                API Documentation
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <h6>1. บันทึกข้อมูลการศึกษา</h6>
                                    <p class="text-muted">เพิ่มข้อมูลการศึกษาใหม่เข้าสู่บล็อกเชน</p>
                                    
                                    <div class="code-block">
                                        <strong>POST</strong> <?php echo SITE_URL; ?>/api/store_record.php
                                    </div>
                                    
                                    <h6 class="mt-3">Headers:</h6>
                                    <div class="code-block">
Content-Type: application/json<br>
X-API-Key: YOUR_API_KEY
                                    </div>
                                    
                                    <h6 class="mt-3">Request Body:</h6>
                                    <pre class="code-block"><code>{
    "student_id": "60001234",
    "student_name": "สมชาย ใจดี",
    "course_code": "CS101",
    "course_name": "Introduction to Computer Science",
    "grade": "A",
    "credits": 3.0,
    "semester": "1/2567",
    "academic_year": "2567"
}</code></pre>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <h6>2. ตรวจสอบข้อมูลการศึกษา</h6>
                                    <p class="text-muted">ตรวจสอบความถูกต้องของข้อมูลการศึกษา</p>
                                    
                                    <div class="code-block">
                                        <strong>GET</strong> <?php echo SITE_URL; ?>/api/verify_record.php?record_id=ID
                                    </div>
                                    
                                    <h6 class="mt-3">Headers:</h6>
                                    <div class="code-block">
X-API-Key: YOUR_API_KEY
                                    </div>
                                    
                                    <h6 class="mt-3">Response:</h6>
                                    <pre class="code-block"><code>{
    "success": true,
    "is_valid": true,
    "record_hash": "...",
    "block_hash": "...",
    "blockchain_integrity": true
}</code></pre>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <h6>3. ดึงข้อมูลการศึกษา</h6>
                                    <p class="text-muted">ดึงรายการข้อมูลการศึกษาทั้งหมด</p>
                                    
                                    <div class="code-block">
                                        <strong>GET</strong> <?php echo SITE_URL; ?>/api/get_records.php
                                    </div>
                                    
                                    <h6 class="mt-3">Query Parameters:</h6>
                                    <div class="code-block">
?student_id=60001234&limit=10&offset=0
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <h6>4. ข้อมูลบล็อกเชน</h6>
                                    <p class="text-muted">ดึงข้อมูลบล็อกเชนและสถิติ</p>
                                    
                                    <div class="code-block">
                                        <strong>GET</strong> <?php echo SITE_URL; ?>/api/blockchain_info.php
                                    </div>
                                    
                                    <h6 class="mt-3">Response:</h6>
                                    <pre class="code-block"><code>{
    "total_blocks": 156,
    "total_records": 2340,
    "blockchain_integrity": true,
    "latest_block": {
        "index": 155,
        "hash": "...",
        "timestamp": "..."
    }
}</code></pre>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>หมายเหตุ:</strong> ข้อมูลทั้งหมดที่ส่งผ่าน API จะถูกเข้ารหัสและจัดเก็บใน Blockchain 
                                เพื่อความปลอดภัยและป้องกันการแก้ไข
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Usage Logs -->
            <div class="row">
                <div class="col-12">
                    <div class="card api-card">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul me-2"></i>
                                API Usage Logs
                            </h5>
                            <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($api_logs)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-journal-x display-4 text-muted"></i>
                                    <p class="text-muted mt-2">ยังไม่มีการใช้งาน API</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>วันที่/เวลา</th>
                                                <th>Endpoint</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                                <th>IP Address</th>
                                                <th>User Agent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($api_logs as $log): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($log['endpoint']); ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($log['method']); ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $log['response_status'];
                                                    $badge_class = 'bg-secondary';
                                                    if ($status >= 200 && $status < 300) $badge_class = 'bg-success';
                                                    elseif ($status >= 400 && $status < 500) $badge_class = 'bg-warning';
                                                    elseif ($status >= 500) $badge_class = 'bg-danger';
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status ?: 'N/A'; ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(substr($log['user_agent'], 0, 50)) . (strlen($log['user_agent']) > 50 ? '...' : ''); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        function toggleApiKey() {
            const apiKeyInput = document.getElementById('apiKeyInput');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                apiKeyInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }

        function copyApiKey() {
            const apiKeyInput = document.getElementById('apiKeyInput');
            const originalType = apiKeyInput.type;
            
            // Show the text temporarily
            apiKeyInput.type = 'text';
            apiKeyInput.select();
            apiKeyInput.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                
                // Show success message
                const toast = document.createElement('div');
                toast.className = 'toast position-fixed top-0 end-0 m-3';
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="toast-header">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong class="me-auto">สำเร็จ</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        คัดลอก API Key แล้ว
                    </div>
                `;
                document.body.appendChild(toast);
                
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                // Remove toast after it's hidden
                toast.addEventListener('hidden.bs.toast', () => {
                    document.body.removeChild(toast);
                });
                
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
            
            // Restore original type
            apiKeyInput.type = originalType;
        }
    </script>
</body>
</html>