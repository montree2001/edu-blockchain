<?php
// records.php - หน้าจัดการข้อมูลการศึกษา
require_once 'config.php';
require_once 'Blockchain.php';

checkPermission();

$blockchain = new Blockchain($pdo);
$success_message = '';
$error_message = '';

// การเพิ่มข้อมูลใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_record') {
    $recordData = [
        'student_id' => trim($_POST['student_id'] ?? ''),
        'student_name' => trim($_POST['student_name'] ?? ''),
        'course_code' => trim($_POST['course_code'] ?? ''),
        'course_name' => trim($_POST['course_name'] ?? ''),
        'grade' => trim($_POST['grade'] ?? ''),
        'credits' => floatval($_POST['credits'] ?? 0),
        'semester' => trim($_POST['semester'] ?? ''),
        'academic_year' => trim($_POST['academic_year'] ?? '')
    ];
    
    // ตรวจสอบข้อมูล
    $errors = [];
    if (empty($recordData['student_id'])) $errors[] = 'กรุณากรอกรหัสนักศึกษา';
    if (empty($recordData['student_name'])) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if (empty($recordData['course_code'])) $errors[] = 'กรุณากรอกรหัสวิชา';
    if (empty($recordData['course_name'])) $errors[] = 'กรุณากรอกชื่อวิชา';
    if (empty($recordData['grade'])) $errors[] = 'กรุณาเลือกเกรด';
    if ($recordData['credits'] <= 0) $errors[] = 'กรุณากรอกหน่วยกิต';
    if (empty($recordData['semester'])) $errors[] = 'กรุณากรอกภาคเรียน';
    if (empty($recordData['academic_year'])) $errors[] = 'กรุณากรอกปีการศึกษา';
    
    if (empty($errors)) {
        try {
            $result = $blockchain->storeEducationalRecord($recordData, $_SESSION['user_id']);
            
            if ($result['success']) {
                $success_message = 'บันทึกข้อมูลการศึกษาสำเร็จ';
                logActivity($_SESSION['user_id'], 'add_educational_record', json_encode($recordData, JSON_UNESCAPED_UNICODE));
            } else {
                $error_message = 'เกิดข้อผิดพลาด: ' . ($result['error'] ?? 'ไม่สามารถบันทึกข้อมูลได้');
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage();
            error_log('Blockchain error: ' . $e->getMessage());
        }
    } else {
        $error_message = implode(', ', $errors);
    }
}

// การค้นหา
$search_student_id = $_GET['search_student_id'] ?? '';
$search_course_code = $_GET['search_course_code'] ?? '';
$search_semester = $_GET['search_semester'] ?? '';
$search_academic_year = $_GET['search_academic_year'] ?? '';

// ดึงข้อมูลการศึกษา
$records = $blockchain->getAllEducationalRecords(
    $_SESSION['user_type'] === 'institution' ? $_SESSION['user_id'] : null
);

// กรองข้อมูลตามการค้นหา
if (!empty($search_student_id) || !empty($search_course_code) || !empty($search_semester) || !empty($search_academic_year)) {
    $records = array_filter($records, function($record) use ($search_student_id, $search_course_code, $search_semester, $search_academic_year) {
        $match = true;
        if (!empty($search_student_id) && strpos($record['student_id'], $search_student_id) === false) {
            $match = false;
        }
        if (!empty($search_course_code) && strpos($record['course_code'], $search_course_code) === false) {
            $match = false;
        }
        if (!empty($search_semester) && $record['semester'] !== $search_semester) {
            $match = false;
        }
        if (!empty($search_academic_year) && $record['academic_year'] !== $search_academic_year) {
            $match = false;
        }
        return $match;
    });
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$total_records = count($records);
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;
$current_records = array_slice($records, $offset, $per_page);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลการศึกษา - <?php echo SITE_NAME; ?></title>
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
        .records-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        .grade-badge {
            min-width: 50px;
            text-align: center;
        }
        .search-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            <a class="nav-link text-white active" href="records.php">
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
                <span class="navbar-brand mb-0 h1">ข้อมูลการศึกษา</span>
                
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

        <!-- Records Content -->
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

            <!-- Search and Add Form -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card search-card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-search me-2"></i>
                                ค้นหาข้อมูลการศึกษา
                            </h5>
                            
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search_student_id" 
                                           placeholder="รหัสนักศึกษา" value="<?php echo htmlspecialchars($search_student_id); ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search_course_code" 
                                           placeholder="รหัสวิชา" value="<?php echo htmlspecialchars($search_course_code); ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="search_semester" 
                                           placeholder="ภาคเรียน" value="<?php echo htmlspecialchars($search_semester); ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="search_academic_year" 
                                           placeholder="ปีการศึกษา" value="<?php echo htmlspecialchars($search_academic_year); ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-light w-100">
                                        <i class="bi bi-search me-1"></i>ค้นหา
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($search_student_id) || !empty($search_course_code) || !empty($search_semester) || !empty($search_academic_year)): ?>
                                <div class="mt-2">
                                    <a href="records.php" class="btn btn-outline-light btn-sm">
                                        <i class="bi bi-x-circle me-1"></i>ล้างการค้นหา
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card records-card h-100">
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="bi bi-plus-circle display-4 text-primary mb-3"></i>
                                <h5>เพิ่มข้อมูลใหม่</h5>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                                    <i class="bi bi-plus me-2"></i>เพิ่มข้อมูลการศึกษา
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="card records-card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>
                        รายการข้อมูลการศึกษา
                        <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
                    </h5>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportData('csv')">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportData('json')">
                            <i class="bi bi-file-earmark-code me-1"></i>Export JSON
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($current_records)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">ไม่พบข้อมูลการศึกษา</h5>
                            <p class="text-muted">เริ่มต้นด้วยการเพิ่มข้อมูลการศึกษาใหม่</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                                <i class="bi bi-plus me-2"></i>เพิ่มข้อมูลใหม่
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>รหัสนักศึกษา</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>รายวิชา</th>
                                        <th>เกรด</th>
                                        <th>หน่วยกิต</th>
                                        <th>ภาคเรียน</th>
                                        <th>สถานะ</th>
                                        <th>วันที่สร้าง</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_records as $index => $record): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['student_id']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($record['course_code']); ?></strong>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $grade_class = 'bg-secondary';
                                            switch ($record['grade']) {
                                                case 'A': $grade_class = 'bg-success'; break;
                                                case 'B+': case 'B': $grade_class = 'bg-info'; break;
                                                case 'C+': case 'C': $grade_class = 'bg-warning'; break;
                                                case 'D+': case 'D': $grade_class = 'bg-danger'; break;
                                                case 'F': $grade_class = 'bg-dark'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $grade_class; ?> grade-badge">
                                                <?php echo htmlspecialchars($record['grade']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($record['credits'], 1); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($record['semester']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['academic_year']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($record['block_id']): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-shield-check me-1"></i>ยืนยันแล้ว
                                                </span>
                                                <br><small class="text-muted">Block #<?php echo $record['block_index']; ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-clock me-1"></i>รอการยืนยัน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewRecord(<?php echo $record['id']; ?>)" title="ดูรายละเอียด">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success btn-sm" 
                                                        onclick="verifyRecord(<?php echo $record['id']; ?>)" title="ตรวจสอบ">
                                                    <i class="bi bi-shield-check"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
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
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Record Modal -->
    <div class="modal fade" id="addRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>เพิ่มข้อมูลการศึกษาใหม่
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_record">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="student_name" class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" class="form-control" id="student_name" name="student_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="course_code" class="form-label">รหัสวิชา</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="course_name" class="form-label">ชื่อวิชา</label>
                                <input type="text" class="form-control" id="course_name" name="course_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="grade" class="form-label">เกรด</label>
                                <select class="form-select" id="grade" name="grade" required>
                                    <option value="">เลือกเกรด</option>
                                    <option value="A">A</option>
                                    <option value="B+">B+</option>
                                    <option value="B">B</option>
                                    <option value="C+">C+</option>
                                    <option value="C">C</option>
                                    <option value="D+">D+</option>
                                    <option value="D">D</option>
                                    <option value="F">F</option>
                                    <option value="I">I (ไม่สมบูรณ์)</option>
                                    <option value="W">W (ถอน)</option>
                                    <option value="S">S (พอใจ)</option>
                                    <option value="U">U (ไม่พอใจ)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="credits" class="form-label">หน่วยกิต</label>
                                <input type="number" class="form-control" id="credits" name="credits" 
                                       step="0.1" min="0.1" max="10" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="semester" class="form-label">ภาคเรียน</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">เลือกภาคเรียน</option>
                                    <option value="1/2567">1/2567</option>
                                    <option value="2/2567">2/2567</option>
                                    <option value="3/2567">3/2567</option>
                                    <option value="1/2568">1/2568</option>
                                    <option value="2/2568">2/2568</option>
                                    <option value="3/2568">3/2568</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="academic_year" class="form-label">ปีการศึกษา</label>
                                <select class="form-select" id="academic_year" name="academic_year" required>
                                    <option value="">เลือกปีการศึกษา</option>
                                    <option value="2567">2567</option>
                                    <option value="2568">2568</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>หมายเหตุ:</strong> ข้อมูลที่บันทึกจะถูกเข้ารหัสและจัดเก็บใน Blockchain 
                            ไม่สามารถแก้ไขหรือลบได้หลังจากที่ยืนยันแล้ว
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRecord(recordId) {
            // TODO: Implement view record modal
            alert('ดูรายละเอียดข้อมูล ID: ' + recordId);
        }
        
        function verifyRecord(recordId) {
            // TODO: Implement verify record functionality
            alert('ตรวจสอบข้อมูล ID: ' + recordId);
        }
        
        function exportData(format) {
            // TODO: Implement data export functionality
            alert('ส่งออกข้อมูลในรูปแบบ: ' + format);
        }
    </script>
</body>
</html>