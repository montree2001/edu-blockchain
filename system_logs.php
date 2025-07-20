<?php
// system_logs.php - หน้าระบบ Logs (เฉพาะ admin)
require_once 'config.php';

checkPermission('admin');

// รับพารามิเตอร์การกรอง
$log_type = $_GET['log_type'] ?? 'all';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    // สร้างเงื่อนไขการค้นหา
    $where_conditions = [];
    $params = [];
    
    if ($user_id) {
        $where_conditions[] = "al.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(al.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(al.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if ($search) {
        $where_conditions[] = "(al.endpoint LIKE ? OR al.request_data LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $search_term = "%{$search}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    if ($log_type !== 'all') {
        switch ($log_type) {
            case 'api':
                $where_conditions[] = "al.endpoint LIKE '/api/%'";
                break;
            case 'auth':
                $where_conditions[] = "al.endpoint IN ('login', 'logout', 'register')";
                break;
            case 'admin':
                $where_conditions[] = "al.endpoint LIKE '%admin%' OR al.endpoint LIKE '%user%'";
                break;
            case 'errors':
                $where_conditions[] = "al.response_status >= 400";
                break;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // นับจำนวนรายการทั้งหมด
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM api_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        {$where_clause}
    ";
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetch()['total'];
    
    // ดึงข้อมูล logs
    $sql = "
        SELECT al.*, u.username, u.full_name, u.user_type
        FROM api_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        {$where_clause}
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // สถิติ logs
    $stats_sql = "
        SELECT 
            COUNT(*) as total_logs,
            COUNT(CASE WHEN response_status >= 200 AND response_status < 300 THEN 1 END) as success_logs,
            COUNT(CASE WHEN response_status >= 400 THEN 1 END) as error_logs,
            COUNT(CASE WHEN endpoint LIKE '/api/%' THEN 1 END) as api_logs,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_logs
        FROM api_logs al
        LEFT JOIN users u ON al.user_id = u.id
        {$where_clause}
    ";
    
    $stats_params = array_slice($params, 0, -2); // ลบ LIMIT และ OFFSET
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($stats_params);
    $stats = $stmt->fetch();
    
    // ดึงรายการผู้ใช้สำหรับ filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.full_name 
        FROM users u 
        INNER JOIN api_logs al ON u.id = al.user_id 
        ORDER BY u.username
    ");
    $stmt->execute();
    $users_with_logs = $stmt->fetchAll();
    
    // คำนวณ pagination
    $total_pages = ceil($total_logs / $per_page);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล logs';
    $logs = [];
    $stats = [];
    $users_with_logs = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบ Logs - <?php echo SITE_NAME; ?></title>
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
        .logs-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .log-entry {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .log-entry:hover {
            background-color: #f8f9fa;
        }
        .log-success { border-left-color: #28a745; }
        .log-warning { border-left-color: #ffc107; }
        .log-error { border-left-color: #dc3545; }
        .log-info { border-left-color: #17a2b8; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
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
            <a class="nav-link text-white" href="users.php">
                <i class="bi bi-people me-2"></i>
                จัดการผู้ใช้
            </a>
            <a class="nav-link text-white active" href="system_logs.php">
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
                <span class="navbar-brand mb-0 h1">ระบบ Logs</span>
                
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

        <!-- System Logs Content -->
        <div class="container-fluid p-4">
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-graph-up me-2"></i>
                                สถิติ System Logs
                            </h5>
                            
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo number_format($stats['total_logs'] ?? 0); ?></h4>
                                    <p class="mb-0 small">Logs ทั้งหมด</p>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo number_format($stats['success_logs'] ?? 0); ?></h4>
                                    <p class="mb-0 small">สำเร็จ</p>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo number_format($stats['error_logs'] ?? 0); ?></h4>
                                    <p class="mb-0 small">ข้อผิดพลาด</p>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo number_format($stats['api_logs'] ?? 0); ?></h4>
                                    <p class="mb-0 small">API Calls</p>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo number_format($stats['today_logs'] ?? 0); ?></h4>
                                    <p class="mb-0 small">วันนี้</p>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="mb-1"><?php echo $stats['total_logs'] > 0 ? number_format(($stats['success_logs'] / $stats['total_logs']) * 100, 1) : 0; ?>%</h4>
                                    <p class="mb-0 small">อัตราสำเร็จ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card filter-card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-2">
                                    <label for="log_type" class="form-label">ประเภท Log</label>
                                    <select class="form-select" id="log_type" name="log_type">
                                        <option value="all" <?php echo $log_type === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                        <option value="api" <?php echo $log_type === 'api' ? 'selected' : ''; ?>>API Calls</option>
                                        <option value="auth" <?php echo $log_type === 'auth' ? 'selected' : ''; ?>>Authentication</option>
                                        <option value="admin" <?php echo $log_type === 'admin' ? 'selected' : ''; ?>>Admin Actions</option>
                                        <option value="errors" <?php echo $log_type === 'errors' ? 'selected' : ''; ?>>Errors Only</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="user_id" class="form-label">ผู้ใช้</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($users_with_logs as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" 
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="search" class="form-label">ค้นหา</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Endpoint, Request Data, Username..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if ($log_type !== 'all' || $user_id || $date_from || $date_to || $search): ?>
                                <div class="mt-2">
                                    <a href="system_logs.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i>ล้างตัวกรอง
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card logs-card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        System Logs
                        <span class="badge bg-primary ms-2"><?php echo number_format($total_logs); ?></span>
                    </h5>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="refreshLogs()">
                            <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportLogs()">
                            <i class="bi bi-download me-1"></i>ส่งออก
                        </button>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">ไม่พบ Logs</h5>
                            <p class="text-muted">ลองปรับเงื่อนไขการค้นหา</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>วันที่/เวลา</th>
                                        <th>ผู้ใช้</th>
                                        <th>Endpoint</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>IP Address</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <?php
                                    $log_class = 'log-info';
                                    if ($log['response_status'] >= 200 && $log['response_status'] < 300) {
                                        $log_class = 'log-success';
                                    } elseif ($log['response_status'] >= 400 && $log['response_status'] < 500) {
                                        $log_class = 'log-warning';
                                    } elseif ($log['response_status'] >= 500) {
                                        $log_class = 'log-error';
                                    }
                                    ?>
                                    <tr class="log-entry <?php echo $log_class; ?>">
                                        <td>
                                            <small><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                    <?php
                                                    $user_type_badges = [
                                                        'admin' => 'bg-danger',
                                                        'institution' => 'bg-primary',
                                                        'api_user' => 'bg-secondary'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $user_type_badges[$log['user_type']] ?? 'bg-secondary'; ?> ms-1">
                                                        <?php echo $log['user_type']; ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['full_name']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
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
                                            elseif ($status >= 300 && $status < 400) $badge_class = 'bg-info';
                                            elseif ($status >= 400 && $status < 500) $badge_class = 'bg-warning';
                                            elseif ($status >= 500) $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status ?: 'N/A'; ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                    onclick="viewLogDetails(<?php echo $log['id']; ?>)" title="ดูรายละเอียด">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-transparent">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        แสดง <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $per_page, $total_logs)); ?> 
                                        จาก <?php echo number_format($total_logs); ?> รายการ
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-journal-text me-2"></i>
                        รายละเอียด Log
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="logDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewLogDetails(logId) {
            // TODO: Load log details via AJAX
            document.getElementById('logDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-2">กำลังโหลดรายละเอียด log...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
            modal.show();
            
            // Simulate API call
            setTimeout(() => {
                document.getElementById('logDetailsContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        รายละเอียด Log ID: ${logId}
                    </div>
                    <p>ฟังก์ชันนี้จะถูกพัฒนาต่อไป...</p>
                `;
            }, 1000);
        }
        
        function refreshLogs() {
            location.reload();
        }
        
        function exportLogs() {
            // TODO: Implement logs export
            alert('ส่งออกข้อมูล logs');
        }
    </script>
</body>
</html>