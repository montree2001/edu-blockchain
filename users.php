<?php
// users.php - หน้าจัดการผู้ใช้ (เฉพาะ admin)
require_once 'config.php';

checkPermission('admin');

$success_message = '';
$error_message = '';

// การจัดการผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $new_status = intval($_POST['new_status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND id != ?");
            $stmt->execute([$new_status, $user_id, $_SESSION['user_id']]);
            
            $success_message = $new_status ? 'เปิดใช้งานผู้ใช้สำเร็จ' : 'ปิดใช้งานผู้ใช้สำเร็จ';
            logActivity($_SESSION['user_id'], 'user_status_changed', "User ID: $user_id, New Status: $new_status");
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะผู้ใช้';
        }
    } 
    elseif ($action === 'regenerate_api_key') {
        $user_id = intval($_POST['user_id']);
        
        try {
            $new_api_key = generateApiKey();
            $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ? AND id != ?");
            $stmt->execute([$new_api_key, $user_id, $_SESSION['user_id']]);
            
            $success_message = 'สร้าง API Key ใหม่สำเร็จ';
            logActivity($_SESSION['user_id'], 'api_key_regenerated_by_admin', "User ID: $user_id");
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการสร้าง API Key ใหม่';
        }
    }
    elseif ($action === 'update_user_type') {
        $user_id = intval($_POST['user_id']);
        $new_type = $_POST['new_type'] ?? '';
        
        if (in_array($new_type, ['admin', 'institution', 'api_user'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET user_type = ? WHERE id = ? AND id != ?");
                $stmt->execute([$new_type, $user_id, $_SESSION['user_id']]);
                
                $success_message = 'เปลี่ยนประเภทผู้ใช้สำเร็จ';
                logActivity($_SESSION['user_id'], 'user_type_changed', "User ID: $user_id, New Type: $new_type");
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในการเปลี่ยนประเภทผู้ใช้';
            }
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
try {
    $search = $_GET['search'] ?? '';
    $user_type_filter = $_GET['user_type'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR institution LIKE ?)";
        $search_term = "%{$search}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    if (!empty($user_type_filter)) {
        $where_conditions[] = "user_type = ?";
        $params[] = $user_type_filter;
    }
    
    if ($status_filter !== '') {
        $where_conditions[] = "is_active = ?";
        $params[] = intval($status_filter);
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(er.id) as record_count,
               COUNT(al.id) as api_usage_count,
               MAX(al.created_at) as last_api_usage
        FROM users u
        LEFT JOIN educational_records er ON u.id = er.institution_id
        LEFT JOIN api_logs al ON u.id = al.user_id
        {$where_clause}
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // สถิติผู้ใช้
    $stmt = $pdo->prepare("
        SELECT 
            user_type,
            COUNT(*) as count,
            SUM(is_active) as active_count
        FROM users 
        GROUP BY user_type
    ");
    $stmt->execute();
    $user_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูลผู้ใช้';
    $users = [];
    $user_stats = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ - <?php echo SITE_NAME; ?></title>
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
        .users-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
            <a class="nav-link text-white" href="blockchain.php">
                <i class="bi bi-diagram-3 me-2"></i>
                บล็อกเชน
            </a>
            <a class="nav-link text-white" href="api_management.php">
                <i class="bi bi-gear me-2"></i>
                จัดการ API
            </a>
            
            <hr class="border-light opacity-25">
            <a class="nav-link text-white active" href="users.php">
                <i class="bi bi-people me-2"></i>
                จัดการผู้ใช้
            </a>
            <a class="nav-link text-white" href="system_logs.php">
                <i class="bi bi-list-ul me-2"></i>
                ระบบ Logs
            </a>
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
                <span class="navbar-brand mb-0 h1">จัดการผู้ใช้</span>
                
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

        <!-- Users Management Content -->
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

            <!-- User Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-graph-up me-2"></i>
                                สถิติผู้ใช้งาน
                            </h5>
                            
                            <div class="row">
                                <?php foreach ($user_stats as $stat): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <h4 class="mb-1"><?php echo $stat['count']; ?></h4>
                                        <p class="mb-1">
                                            <?php
                                            $type_names = [
                                                'admin' => 'ผู้ดูแลระบบ',
                                                'institution' => 'สถาบันการศึกษา',
                                                'api_user' => 'ผู้ใช้ API'
                                            ];
                                            echo $type_names[$stat['user_type']] ?? $stat['user_type'];
                                            ?>
                                        </p>
                                        <small class="opacity-75">
                                            ใช้งานจริง: <?php echo $stat['active_count']; ?> คน
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card users-card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">ค้นหา</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="ชื่อผู้ใช้, อีเมล, ชื่อ-นามสกุล, สถาบัน"
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="user_type" class="form-label">ประเภทผู้ใช้</label>
                                    <select class="form-select" id="user_type" name="user_type">
                                        <option value="">ทั้งหมด</option>
                                        <option value="admin" <?php echo $user_type_filter === 'admin' ? 'selected' : ''; ?>>ผู้ดูแลระบบ</option>
                                        <option value="institution" <?php echo $user_type_filter === 'institution' ? 'selected' : ''; ?>>สถาบันการศึกษา</option>
                                        <option value="api_user" <?php echo $user_type_filter === 'api_user' ? 'selected' : ''; ?>>ผู้ใช้ API</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">สถานะ</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">ทั้งหมด</option>
                                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>ใช้งาน</option>
                                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>ปิดใช้งาน</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search me-1"></i>ค้นหา
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card users-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>
                        รายการผู้ใช้งาน
                        <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                    </h5>
                </div>
                
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">ไม่พบผู้ใช้</h5>
                            <p class="text-muted">ลองปรับเงื่อนไขการค้นหา</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ผู้ใช้</th>
                                        <th>ประเภท</th>
                                        <th>สถาบัน</th>
                                        <th>สถิติการใช้งาน</th>
                                        <th>สถานะ</th>
                                        <th>วันที่สร้าง</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <small class="text-muted">
                                                        @<?php echo htmlspecialchars($user['username']); ?>
                                                    </small><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
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
                                            <span class="badge <?php echo $type_badges[$user['user_type']] ?? 'bg-secondary'; ?>">
                                                <?php echo $type_names[$user['user_type']] ?? $user['user_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['institution'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted">ข้อมูลการศึกษา:</small>
                                                <strong><?php echo number_format($user['record_count']); ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">การใช้ API:</small>
                                                <strong><?php echo number_format($user['api_usage_count']); ?></strong>
                                            </div>
                                            <?php if ($user['last_api_usage']): ?>
                                                <small class="text-muted">
                                                    ล่าสุด: <?php echo date('d/m/Y', strtotime($user['last_api_usage'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>ใช้งาน
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-x-circle me-1"></i>ปิดใช้งาน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" 
                                                            data-bs-toggle="dropdown">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <button class="dropdown-item" onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                                                <i class="bi bi-<?php echo $user['is_active'] ? 'x-circle' : 'check-circle'; ?> me-2"></i>
                                                                <?php echo $user['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item" onclick="regenerateApiKey(<?php echo $user['id']; ?>)">
                                                                <i class="bi bi-arrow-clockwise me-2"></i>สร้าง API Key ใหม่
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item" onclick="changeUserType(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['user_type']); ?>')">
                                                                <i class="bi bi-person-gear me-2"></i>เปลี่ยนประเภทผู้ใช้
                                                            </button>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button class="dropdown-item" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                                                <i class="bi bi-eye me-2"></i>ดูรายละเอียด
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning">ตัวคุณเอง</span>
                                            <?php endif; ?>
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

    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="user_id" id="actionUserId">
        <input type="hidden" name="new_status" id="actionNewStatus">
        <input type="hidden" name="new_type" id="actionNewType">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
            if (confirm(`คุณแน่ใจหรือไม่ที่จะ${action}ผู้ใช้นี้?`)) {
                document.getElementById('actionType').value = 'toggle_status';
                document.getElementById('actionUserId').value = userId;
                document.getElementById('actionNewStatus').value = newStatus;
                document.getElementById('actionForm').submit();
            }
        }
        
        function regenerateApiKey(userId) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะสร้าง API Key ใหม่? API Key เดิมจะไม่สามารถใช้งานได้อีก')) {
                document.getElementById('actionType').value = 'regenerate_api_key';
                document.getElementById('actionUserId').value = userId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function changeUserType(userId, currentType) {
            const types = {
                'admin': 'ผู้ดูแลระบบ',
                'institution': 'สถาบันการศึกษา',
                'api_user': 'ผู้ใช้ API'
            };
            
            let options = '';
            for (let type in types) {
                const selected = type === currentType ? 'selected' : '';
                options += `<option value="${type}" ${selected}>${types[type]}</option>`;
            }
            
            const newType = prompt('เลือกประเภทผู้ใช้ใหม่:\n\n' + 
                'admin = ผู้ดูแลระบบ\n' +
                'institution = สถาบันการศึกษา\n' +
                'api_user = ผู้ใช้ API\n\n' +
                'กรอกประเภทใหม่:');
            
            if (newType && types[newType] && newType !== currentType) {
                if (confirm(`เปลี่ยนประเภทผู้ใช้เป็น "${types[newType]}" หรือไม่?`)) {
                    document.getElementById('actionType').value = 'update_user_type';
                    document.getElementById('actionUserId').value = userId;
                    document.getElementById('actionNewType').value = newType;
                    document.getElementById('actionForm').submit();
                }
            }
        }
        
        function viewUserDetails(userId) {
            // TODO: Implement user details modal
            alert('ดูรายละเอียดผู้ใช้ ID: ' + userId);
        }
    </script>
</body>
</html>