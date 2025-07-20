<?php
// dashboard.php - หน้าหลักของระบบ
require_once 'config.php';
require_once 'Blockchain.php';


checkPermission();

$blockchain = new Blockchain($pdo);

// ดึงสถิติต่างๆ
try {
    // นับจำนวนผู้ใช้
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $stmt->execute();
    $total_users = $stmt->fetch()['total_users'];
    
    // นับจำนวนบล็อก
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_blocks FROM blockchain_blocks");
    $stmt->execute();
    $total_blocks = $stmt->fetch()['total_blocks'];
    
    // นับจำนวนข้อมูลการศึกษา
    $where_clause = '';
    $params = [];
    if ($_SESSION['user_type'] === 'institution') {
        $where_clause = 'WHERE institution_id = ?';
        $params[] = $_SESSION['user_id'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_records FROM educational_records $where_clause");
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total_records'];
    
    // นับจำนวนธุรกรรม
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_transactions FROM transactions");
    $stmt->execute();
    $total_transactions = $stmt->fetch()['total_transactions'];
    
    // ดึงข้อมูลการศึกษาล่าสุด
    $recent_records = $blockchain->getAllEducationalRecords(
        $_SESSION['user_type'] === 'institution' ? $_SESSION['user_id'] : null
    );
    $recent_records = array_slice($recent_records, 0, 5);
    
    // ดึงบล็อกล่าสุด
    $recent_blocks = $blockchain->getAllBlocks();
    $recent_blocks = array_slice(array_reverse($recent_blocks), 0, 5);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - <?php echo SITE_NAME; ?></title>
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
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .blockchain-status {
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
                <span class="navbar-brand mb-0 h1">หน้าหลัก</span>
                
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

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Welcome Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card blockchain-status">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3 class="mb-1">ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
                                    <p class="mb-0 opacity-75">
                                        ระบบป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษาโดยใช้บล็อกเชนเฟรมเวิร์ค
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="d-flex align-items-center justify-content-end">
                                        <div class="me-3">
                                            <i class="bi bi-shield-check display-4"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">สถานะระบบ</div>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-success me-2">●</span>
                                                <small>ทำงานปกติ</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary me-3">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">ผู้ใช้ทั้งหมด</div>
                                    <div class="h4 mb-0"><?php echo number_format($total_users); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success me-3">
                                    <i class="bi bi-diagram-3"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">บล็อกทั้งหมด</div>
                                    <div class="h4 mb-0"><?php echo number_format($total_blocks); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-info me-3">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">ข้อมูลการศึกษา</div>
                                    <div class="h4 mb-0"><?php echo number_format($total_records); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-warning me-3">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">ธุรกรรม</div>
                                    <div class="h4 mb-0"><?php echo number_format($total_transactions); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card stat-card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                ข้อมูลการศึกษาล่าสุด
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_records)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <p class="text-muted mt-2">ยังไม่มีข้อมูลการศึกษา</p>
                                    <a href="records.php" class="btn btn-primary">
                                        <i class="bi bi-plus me-2"></i>เพิ่มข้อมูลใหม่
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>รหัสนักศึกษา</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>รายวิชา</th>
                                                <th>เกรด</th>
                                                <th>สถานะ</th>
                                                <th>วันที่สร้าง</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($record['course_code']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($record['grade']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($record['block_id']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-shield-check me-1"></i>ยืนยันแล้ว
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-clock me-1"></i>รอการยืนยัน
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="records.php" class="btn btn-outline-primary">
                                        ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card stat-card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="bi bi-diagram-3 me-2"></i>
                                บล็อกเชนล่าสุด
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_blocks)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-diagram-3 display-4 text-muted"></i>
                                    <p class="text-muted mt-2">ไม่มีบล็อกในระบบ</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($recent_blocks as $block): ?>
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary rounded-circle p-2 me-3">
                                            <i class="bi bi-box text-white" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">บล็อก #<?php echo $block['block_index']; ?></div>
                                            <div class="text-muted small mb-1">
                                                Hash: <?php echo substr($block['block_hash'], 0, 16); ?>...
                                            </div>
                                            <div class="text-muted small">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($block['timestamp'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="blockchain.php" class="btn btn-outline-primary btn-sm">
                                        ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh stats every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>