<?php
// api/get_records.php - API สำหรับดึงข้อมูลการศึกษา
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config.php';
require_once '../Blockchain.php';

// ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// ตรวจสอบ API Key
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($api_key)) {
    jsonResponse(['error' => 'API key required'], 401);
}

$user = validateApiKey($api_key);
if (!$user) {
    jsonResponse(['error' => 'Invalid API key'], 401);
}

try {
    // รับ parameters
    $student_id = $_GET['student_id'] ?? '';
    $course_code = $_GET['course_code'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $academic_year = $_GET['academic_year'] ?? '';
    $grade = $_GET['grade'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $order_by = $_GET['order_by'] ?? 'created_at';
    $order_dir = strtoupper($_GET['order_dir'] ?? 'DESC');
    
    // ตรวจสอบ limit และ offset
    if ($limit < 1 || $limit > 1000) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }
    
    // ตรวจสอบ order_by และ order_dir
    $allowed_order_by = ['created_at', 'student_id', 'course_code', 'grade', 'semester', 'academic_year'];
    if (!in_array($order_by, $allowed_order_by)) {
        $order_by = 'created_at';
    }
    if (!in_array($order_dir, ['ASC', 'DESC'])) {
        $order_dir = 'DESC';
    }
    
    // สร้าง SQL query
    $where_conditions = [];
    $params = [];
    
    // สถาบันสามารถดูได้เฉพาะข้อมูลของตนเอง
    if ($user['user_type'] === 'institution') {
        $where_conditions[] = "er.institution_id = ?";
        $params[] = $user['id'];
    }
    
    // เพิ่มเงื่อนไขการค้นหา
    if (!empty($student_id)) {
        $where_conditions[] = "er.student_id LIKE ?";
        $params[] = "%{$student_id}%";
    }
    
    if (!empty($course_code)) {
        $where_conditions[] = "er.course_code LIKE ?";
        $params[] = "%{$course_code}%";
    }
    
    if (!empty($semester)) {
        $where_conditions[] = "er.semester = ?";
        $params[] = $semester;
    }
    
    if (!empty($academic_year)) {
        $where_conditions[] = "er.academic_year = ?";
        $params[] = $academic_year;
    }
    
    if (!empty($grade)) {
        $where_conditions[] = "er.grade = ?";
        $params[] = strtoupper($grade);
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // นับจำนวนรายการทั้งหมด
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM educational_records er 
        LEFT JOIN users u ON er.institution_id = u.id 
        {$where_clause}
    ";
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_count = $stmt->fetch()['total'];
    
    // ดึงข้อมูล
    $sql = "
        SELECT er.*, bb.block_hash, bb.block_index, bb.timestamp as block_timestamp,
               u.full_name as institution_name, u.institution
        FROM educational_records er 
        LEFT JOIN blockchain_blocks bb ON er.block_id = bb.id 
        LEFT JOIN users u ON er.institution_id = u.id
        {$where_clause}
        ORDER BY er.{$order_by} {$order_dir}
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // จัดรูปแบบข้อมูล
    $formatted_records = array_map(function($record) {
        return [
            'id' => $record['id'],
            'student_id' => $record['student_id'],
            'student_name' => $record['student_name'],
            'course_code' => $record['course_code'],
            'course_name' => $record['course_name'],
            'grade' => $record['grade'],
            'credits' => floatval($record['credits']),
            'semester' => $record['semester'],
            'academic_year' => $record['academic_year'],
            'institution' => $record['institution'],
            'institution_name' => $record['institution_name'],
            'record_hash' => $record['record_hash'],
            'blockchain' => [
                'block_id' => $record['block_id'],
                'block_index' => $record['block_index'],
                'block_hash' => $record['block_hash'],
                'block_timestamp' => $record['block_timestamp']
            ],
            'status' => $record['block_id'] ? 'verified' : 'pending',
            'created_at' => $record['created_at'],
            'verified_at' => $record['verified_at']
        ];
    }, $records);
    
    // คำนวณ pagination
    $total_pages = ceil($total_count / $limit);
    $current_page = floor($offset / $limit) + 1;
    $has_next = $current_page < $total_pages;
    $has_prev = $current_page > 1;
    
    $result = [
        'success' => true,
        'data' => $formatted_records,
        'pagination' => [
            'total_count' => intval($total_count),
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'limit' => $limit,
            'offset' => $offset,
            'has_next' => $has_next,
            'has_prev' => $has_prev,
            'next_offset' => $has_next ? $offset + $limit : null,
            'prev_offset' => $has_prev ? max(0, $offset - $limit) : null
        ],
        'filters' => [
            'student_id' => $student_id,
            'course_code' => $course_code,
            'semester' => $semester,
            'academic_year' => $academic_year,
            'grade' => $grade,
            'order_by' => $order_by,
            'order_dir' => $order_dir
        ],
        'retrieved_at' => date('Y-m-d H:i:s')
    ];
    
    // บันทึก API log
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/get_records.php',
        'GET',
        json_encode($_GET, JSON_UNESCAPED_UNICODE),
        200,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    jsonResponse($result, 200);
    
} catch (Exception $e) {
    // บันทึก API log สำหรับ error
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/get_records.php',
        'GET',
        json_encode($_GET, JSON_UNESCAPED_UNICODE),
        500,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    jsonResponse([
        'error' => 'Internal server error',
        'message' => 'เกิดข้อผิดพลาดภายในระบบ'
    ], 500);
}
?>