<?php
// dashboard.php - หน้าหลักระบบ
define('SECURE_ACCESS', true);
require_once 'settings.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!checkAuthentication()) {
    header('Location: login.php');
    exit;
}

// ดึงข้อมูลสถิติ
try {
    $pdo = getDBConnection();
    
    // สถิติทั่วไป
    $stats = [];
    
    // จำนวนข้อมูลการศึกษาทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM educational_records WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_records'] = $stmt->fetch()['total'];
    
    // ข้อมูลที่ยืนยันแล้วใน blockchain
    $stmt = $pdo->prepare("SELECT COUNT(*) as confirmed FROM educational_records WHERE user_id = ? AND blockchain_status = 'confirmed'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['confirmed_records'] = $stmt->fetch()['confirmed'];
    
    // ข้อมูลที่รอการยืนยัน
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM educational_records WHERE user_id = ? AND blockchain_status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_records'] = $stmt->fetch()['pending'];
    
    // จำนวน API Keys ที่ใช้งานอยู่
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_keys FROM api_keys WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['active_api_keys'] = $stmt->fetch()['active_keys'];
    
    // การใช้งาน API ในเดือนนี้
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as monthly_usage 
        FROM api_usage_logs aul
        JOIN api_keys ak ON aul.api_key_id = ak.id
        WHERE ak.user_id = ? AND MONTH(aul.created_at) = MONTH(CURRENT_DATE())
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['monthly_api_usage'] = $stmt->fetch()['monthly_usage'];
    
    // ข้อมูลการศึกษาล่าสุด
    $stmt = $pdo->prepare("
        SELECT er.*, bt.tx_hash, bt.status as tx_status
        FROM educational_records er
        LEFT JOIN blockchain_transactions bt ON er.id = bt.educational_record_id
        WHERE er.user_id = ?
        ORDER BY er.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_records = $stmt->fetchAll();
    
    // สถิติ blockchain
    $stmt = $pdo->query("SELECT COUNT(*) as total_blocks FROM blockchain_blocks");
    $blockchain_stats = $stmt->fetch();
    
    // การใช้งาน API ล่าสุด
    $stmt = $pdo->prepare("
        SELECT aul.*, ak.name as api_name
        FROM api_usage_logs aul
        JOIN api_keys ak ON aul.api_key_id = ak.id
        WHERE ak.user_id = ?
        ORDER BY aul.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_api_usage = $stmt->fetchAll();
    
} catch (Exception $e) {
    writeLog('ERROR', "Dashboard data fetch error: " . $e->getMessage());
    $stats = [
        'total_records' => 0,
        'confirmed_records' => 0,
        'pending_records' => 0,
        'active_api_keys' => 0,
        'monthly_api_usage' => 0
    ];
    $recent_records = [];
    $blockchain_stats = ['total_blocks' => 0];
    $recent_api_usage = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        .stats-label {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .nav-link {
            color: white !important;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #6c757d;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-failed { background-color: #f8d7da; color: #721c24; }
        
        .welcome-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <div class="text-center text-white mb-4">
                <i class="bi bi-shield-lock" style="font-size: 2.5rem;"></i>
                <h5 class="mt-2 mb-0">Blockchain Education</h5>
                <small class="opacity-75">Educational Data Protection</small>
            </div>
            
            <nav class="nav flex-column px-3">
                <a class="nav-link text-white active" href="dashboard.php">
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
        </div>
        
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
                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-light btn-sm me-2">
                            <i class="bi bi-gear"></i>
                        </a>
                        <a href="logout.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-3">ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                    <p class="mb-0 text-muted">ระบบป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษาโดยใช้เทคโนโลยีบล็อกเชน</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-muted">
                        <small>เข้าสู่ระบบล่าสุด</small><br>
                        <span class="fw-bold"><?php echo date('d/m/Y H:i', $_SESSION['login_time']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">หน้าหลัก</li>
            </ol>
        </nav>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stats-number"><?php echo number_format($stats['total_records']); ?></div>
                            <p class="stats-label">ข้อมูลทั้งหมด</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stats-number"><?php echo number_format($stats['confirmed_records']); ?></div>
                            <p class="stats-label">ยืนยันแล้ว</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stats-number"><?php echo number_format($stats['pending_records']); ?></div>
                            <p class="stats-label">รอการยืนยัน</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                            <i class="bi bi-key"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stats-number"><?php echo number_format($stats['active_api_keys']); ?></div>
                            <p class="stats-label">API Keys</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Recent Data -->
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Recent Educational Records -->
                <div class="table-card">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            ข้อมูลการศึกษาล่าสุด
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>รหัสนักศึกษา</th>
                                    <th>รายวิชา</th>
                                    <th>เกรด</th>
                                    <th>สถานะ</th>
                                    <th>วันที่สร้าง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_records)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox me-2"></i>ไม่มีข้อมูล
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recent_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($record['course_code']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($record['grade']); ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $record['blockchain_status']; ?>">
                                            <?php
                                            switch($record['blockchain_status']) {
                                                case 'confirmed': echo 'ยืนยันแล้ว'; break;
                                                case 'pending': echo 'รอการยืนยัน'; break;
                                                case 'failed': echo 'ล้มเหลว'; break;
                                                default: echo 'ไม่ทราบ';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-transparent border-0 text-end">
                        <a href="records.php" class="btn btn-outline-primary btn-sm">
                            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Blockchain Status -->
                <div class="chart-card mb-4">
                    <h6 class="mb-3">
                        <i class="bi bi-diagram-3 me-2"></i>
                        สถานะบล็อกเชน
                    </h6>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span>จำนวนบล็อก</span>
                        <span class="fw-bold"><?php echo number_format($blockchain_stats['total_blocks']); ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span>การใช้งาน API เดือนนี้</span>
                        <span class="fw-bold"><?php echo number_format($stats['monthly_api_usage']); ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo min(($stats['confirmed_records'] / max($stats['total_records'], 1)) * 100, 100); ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo number_format(($stats['confirmed_records'] / max($stats['total_records'], 1)) * 100, 1); ?>% 
                        ของข้อมูลได้รับการยืนยันแล้ว
                    </small>
                </div>
                
                <!-- Quick Actions -->
                <div class="chart-card">
                    <h6 class="mb-3">
                        <i class="bi bi-lightning me-2"></i>
                        การดำเนินการด่วน
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="records.php?action=add" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>เพิ่มข้อมูลการศึกษา
                        </a>
                        <a href="api_management.php?action=create" class="btn btn-outline-primary">
                            <i class="bi bi-key me-2"></i>สร้าง API Key
                        </a>
                        <a href="blockchain.php" class="btn btn-outline-secondary">
                            <i class="bi bi-search me-2"></i>ตรวจสอบบล็อกเชน
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent API Usage -->
        <?php if (!empty($recent_api_usage)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-card">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-activity me-2"></i>
                            การใช้งาน API ล่าสุด
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>API Key</th>
                                    <th>Endpoint</th>
                                    <th>Method</th>
                                    <th>Response Code</th>
                                    <th>Response Time</th>
                                    <th>วันที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_api_usage as $usage): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usage['api_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($usage['endpoint']); ?></code></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($usage['method']); ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $usage['response_code'] < 400 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $usage['response_code']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $usage['response_time_ms']; ?> ms</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usage['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Show loading state for buttons
        document.querySelectorAll('a[href*="action="]').forEach(link => {
            link.addEventListener('click', function() {
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + this.innerHTML;
            });
        });
    </script>
</body>
</html>