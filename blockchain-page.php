<?php
// blockchain-page.php - หน้าแสดงข้อมูลบล็อกเชน
require_once 'config.php';
require_once 'blockchain.php';

checkPermission();

$blockchain = new Blockchain($pdo);

try {
    // ดึงข้อมูลบล็อกทั้งหมด
    $blocks = $blockchain->getAllBlocks();
    
    // ตรวจสอบความสมบูรณ์ของบล็อกเชน
    $blockchain_integrity = $blockchain->validateBlockchainIntegrity();
    
    // ดึงสถิติ
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_records FROM educational_records");
    $stmt->execute();
    $total_records = $stmt->fetch()['total_records'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_transactions FROM transactions");
    $stmt->execute();
    $total_transactions = $stmt->fetch()['total_transactions'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as verified_records 
        FROM educational_records 
        WHERE block_id IS NOT NULL
    ");
    $stmt->execute();
    $verified_records = $stmt->fetch()['verified_records'];
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูลบล็อกเชน';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บล็อกเชน - <?php echo SITE_NAME; ?></title>
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
        .blockchain-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .block-item {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .block-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
        }
        .block-chain {
            position: relative;
        }
        .block-chain::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        .block-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        .genesis-block {
            border-left-color: #28a745;
        }
        .integrity-status {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .integrity-valid {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .integrity-invalid {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
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
            <a class="nav-link text-white active" href="blockchain-page.php">
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
                <span class="navbar-brand mb-0 h1">บล็อกเชน</span>
                
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

        <!-- Blockchain Content -->
        <div class="container-fluid p-4">
            <!-- Blockchain Integrity Status -->
            <div class="integrity-status <?php echo $blockchain_integrity ? 'integrity-valid' : 'integrity-invalid'; ?>">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">
                            <i class="bi bi-<?php echo $blockchain_integrity ? 'shield-check' : 'shield-x'; ?> me-2"></i>
                            สถานะความสมบูรณ์ของบล็อกเชน
                        </h4>
                        <p class="mb-0">
                            <?php if ($blockchain_integrity): ?>
                                บล็อกเชนมีความสมบูรณ์และปลอดภัย ไม่มีการแก้ไขข้อมูล
                            <?php else: ?>
                                ตรวจพบความผิดปกติในบล็อกเชน กรุณาตรวจสอบระบบ
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-light" onclick="validateBlockchain()">
                            <i class="bi bi-arrow-clockwise me-2"></i>ตรวจสอบอีกครั้ง
                        </button>
                    </div>
                </div>
            </div>

            <!-- Blockchain Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card blockchain-card">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="bi bi-diagram-3 display-4"></i>
                            </div>
                            <h4 class="mb-1"><?php echo count($blocks); ?></h4>
                            <p class="text-muted mb-0">บล็อกทั้งหมด</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card blockchain-card">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="bi bi-file-earmark-check display-4"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($verified_records); ?></h4>
                            <p class="text-muted mb-0">ข้อมูลที่ยืนยันแล้ว</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card blockchain-card">
                        <div class="card-body text-center">
                            <div class="text-info mb-2">
                                <i class="bi bi-arrow-left-right display-4"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($total_transactions); ?></h4>
                            <p class="text-muted mb-0">ธุรกรรมทั้งหมด</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card blockchain-card">
                        <div class="card-body text-center">
                            <div class="text-warning mb-2">
                                <i class="bi bi-database display-4"></i>
                            </div>
                            <h4 class="mb-1"><?php echo number_format($total_records); ?></h4>
                            <p class="text-muted mb-0">ข้อมูลทั้งหมด</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blockchain Visualization -->
            <div class="card blockchain-card">
                <div class="card-header bg-transparent">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="bi bi-diagram-2 me-2"></i>
                                โครงสร้างบล็อกเชน
                            </h5>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="refreshBlockchain()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportBlockchain()">
                                    <i class="bi bi-download me-1"></i>ส่งออก
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($blocks)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-diagram-3 display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">ไม่มีบล็อกในระบบ</h5>
                            <p class="text-muted">รอการสร้าง Genesis Block</p>
                        </div>
                    <?php else: ?>
                        <div class="block-chain">
                            <?php foreach ($blocks as $index => $block): ?>
                                <div class="row mb-4">
                                    <div class="col-1 d-flex justify-content-center">
                                        <div class="block-number">
                                            <?php echo $block['block_index']; ?>
                                        </div>
                                    </div>
                                    <div class="col-11">
                                        <div class="card block-item <?php echo $block['block_index'] === 0 ? 'genesis-block' : ''; ?>">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="card-title mb-2">
                                                            <?php if ($block['block_index'] === 0): ?>
                                                                <i class="bi bi-star-fill text-warning me-2"></i>
                                                                Genesis Block
                                                            <?php else: ?>
                                                                <i class="bi bi-box me-2"></i>
                                                                Block #<?php echo $block['block_index']; ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        
                                                        <div class="row text-muted small">
                                                            <div class="col-md-6">
                                                                <strong>Block Hash:</strong><br>
                                                                <code class="text-primary"><?php echo substr($block['block_hash'], 0, 32); ?>...</code>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Previous Hash:</strong><br>
                                                                <code class="text-secondary"><?php echo substr($block['previous_hash'], 0, 32); ?>...</code>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row text-muted small mt-2">
                                                            <div class="col-md-6">
                                                                <strong>Data Hash:</strong><br>
                                                                <code><?php echo substr($block['data_hash'], 0, 32); ?>...</code>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>สร้างโดย:</strong><br>
                                                                <?php echo htmlspecialchars($block['creator_name'] ?? 'System'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-4 text-end">
                                                        <div class="mb-2">
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock me-1"></i>
                                                                <?php echo date('d/m/Y H:i:s', strtotime($block['timestamp'])); ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                    onclick="viewBlockDetails(<?php echo $block['id']; ?>)" title="ดูรายละเอียด">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                                    onclick="validateBlock(<?php echo $block['id']; ?>)" title="ตรวจสอบ">
                                                                <i class="bi bi-shield-check"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Block Details Modal -->
    <div class="modal fade" id="blockDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box me-2"></i>
                        รายละเอียดบล็อก
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="blockDetailsContent">
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
        function viewBlockDetails(blockId) {
            // TODO: Load block details via AJAX
            document.getElementById('blockDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-2">กำลังโหลดรายละเอียดบล็อก...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('blockDetailsModal'));
            modal.show();
            
            // Simulate API call
            setTimeout(() => {
                document.getElementById('blockDetailsContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        รายละเอียดบล็อก ID: ${blockId}
                    </div>
                    <p>ฟังก์ชันนี้จะถูกพัฒนาต่อไป...</p>
                `;
            }, 1000);
        }
        
        function validateBlock(blockId) {
            // TODO: Implement block validation
            alert('ตรวจสอบบล็อก ID: ' + blockId);
        }
        
        function validateBlockchain() {
            // TODO: Implement blockchain validation
            location.reload();
        }
        
        function refreshBlockchain() {
            location.reload();
        }
        
        function exportBlockchain() {
            // TODO: Implement blockchain export
            alert('ส่งออกข้อมูลบล็อกเชน');
        }
    </script>
</body>
</html>