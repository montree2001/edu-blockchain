<?php
// api/store_record.php - API สำหรับบันทึกข้อมูลการศึกษา
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config.php';
require_once '../Blockchain.php';

// ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// อ่านข้อมูล JSON
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['error' => 'Invalid JSON format'], 400);
}

// ตรวจสอบข้อมูลที่จำเป็น
$required_fields = ['student_id', 'student_name', 'course_code', 'course_name', 'grade', 'credits', 'semester', 'academic_year'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($input[$field]) || trim($input[$field]) === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    jsonResponse([
        'error' => 'Missing required fields',
        'missing_fields' => $missing_fields
    ], 400);
}

// ตรวจสอบรูปแบบข้อมูล
$errors = [];

// ตรวจสอบรหัสนักศึกษา
if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $input['student_id'])) {
    $errors[] = 'รหัสนักศึกษาต้องเป็นตัวอักษรและตัวเลข 5-20 ตัวอักษร';
}

// ตรวจสอบเกรด
$valid_grades = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F', 'I', 'W', 'S', 'U'];
if (!in_array(strtoupper($input['grade']), $valid_grades)) {
    $errors[] = 'เกรดไม่ถูกต้อง';
}

// ตรวจสอบหน่วยกิต
if (!is_numeric($input['credits']) || $input['credits'] <= 0 || $input['credits'] > 10) {
    $errors[] = 'หน่วยกิตต้องเป็นตัวเลข 0.1-10.0';
}

if (!empty($errors)) {
    jsonResponse([
        'error' => 'Validation failed',
        'validation_errors' => $errors
    ], 400);
}

try {
    // ตรวจสอบข้อมูลซ้ำ
    $stmt = $pdo->prepare("
        SELECT id FROM educational_records 
        WHERE student_id = ? AND course_code = ? AND semester = ? AND academic_year = ? AND institution_id = ?
    ");
    $stmt->execute([
        $input['student_id'],
        $input['course_code'], 
        $input['semester'],
        $input['academic_year'],
        $user['id']
    ]);
    
    if ($stmt->fetch()) {
        jsonResponse([
            'error' => 'Duplicate record',
            'message' => 'ข้อมูลการศึกษานี้มีอยู่แล้วในระบบ'
        ], 409);
    }
    
    // เตรียมข้อมูลสำหรับบันทึก
    $recordData = [
        'student_id' => trim($input['student_id']),
        'student_name' => trim($input['student_name']),
        'course_code' => trim($input['course_code']),
        'course_name' => trim($input['course_name']),
        'grade' => strtoupper(trim($input['grade'])),
        'credits' => floatval($input['credits']),
        'semester' => trim($input['semester']),
        'academic_year' => trim($input['academic_year'])
    ];
    
    // บันทึกลงบล็อกเชน
    $blockchain = new Blockchain($pdo);
    $result = $blockchain->storeEducationalRecord($recordData, $user['id']);
    
    if ($result['success']) {
        // บันทึก API log
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            '/api/store_record.php',
            'POST',
            json_encode($recordData, JSON_UNESCAPED_UNICODE),
            200,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'บันทึกข้อมูลการศึกษาสำเร็จ',
            'data' => [
                'record_id' => $result['record_id'],
                'record_hash' => $result['record_hash'],
                'transaction_hash' => $result['transaction_hash'],
                'block_hash' => $result['block_hash'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], 201);
        
    } else {
        // บันทึก API log สำหรับ error
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            '/api/store_record.php',
            'POST',
            json_encode($recordData, JSON_UNESCAPED_UNICODE),
            500,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        jsonResponse([
            'error' => 'Failed to store record',
            'message' => $result['error'] ?? 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
        ], 500);
    }
    
} catch (Exception $e) {
    // บันทึก API log สำหรับ exception
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/store_record.php',
        'POST',
        json_encode($input, JSON_UNESCAPED_UNICODE),
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