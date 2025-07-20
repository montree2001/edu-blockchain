<?php
// api/verify_record.php - API สำหรับตรวจสอบข้อมูลการศึกษา
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

// ตรวจสอบ parameters
$record_id = $_GET['record_id'] ?? '';
$record_hash = $_GET['record_hash'] ?? '';

if (empty($record_id) && empty($record_hash)) {
    jsonResponse([
        'error' => 'Missing parameters',
        'message' => 'กรุณาระบุ record_id หรือ record_hash'
    ], 400);
}

try {
    $blockchain = new Blockchain($pdo);
    
    // หาข้อมูลการศึกษา
    if (!empty($record_id)) {
        // ค้นหาจาก record_id
        $stmt = $pdo->prepare("
            SELECT er.*, bb.block_hash, bb.block_index, bb.timestamp as block_timestamp,
                   u.full_name as institution_name, u.institution
            FROM educational_records er 
            LEFT JOIN blockchain_blocks bb ON er.block_id = bb.id 
            LEFT JOIN users u ON er.institution_id = u.id
            WHERE er.id = ?
        ");
        $stmt->execute([$record_id]);
        $record = $stmt->fetch();
        
    } else {
        // ค้นหาจาก record_hash
        $stmt = $pdo->prepare("
            SELECT er.*, bb.block_hash, bb.block_index, bb.timestamp as block_timestamp,
                   u.full_name as institution_name, u.institution
            FROM educational_records er 
            LEFT JOIN blockchain_blocks bb ON er.block_id = bb.id 
            LEFT JOIN users u ON er.institution_id = u.id
            WHERE er.record_hash = ?
        ");
        $stmt->execute([$record_hash]);
        $record = $stmt->fetch();
    }
    
    if (!$record) {
        // บันทึก API log
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (user_id, endpoint, method, response_status, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            '/api/verify_record.php',
            'GET',
            404,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        jsonResponse([
            'error' => 'Record not found',
            'message' => 'ไม่พบข้อมูลการศึกษาที่ระบุ'
        ], 404);
    }
    
    // ตรวจสอบสิทธิ์การเข้าถึง (สถาบันสามารถดูได้เฉพาะข้อมูลของตนเอง)
    if ($user['user_type'] === 'institution' && $record['institution_id'] != $user['id']) {
        jsonResponse([
            'error' => 'Access denied',
            'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้'
        ], 403);
    }
    
    // ตรวจสอบความถูกต้องของข้อมูล
    $verification_result = $blockchain->verifyRecord($record['id']);
    
    if (!$verification_result['success']) {
        jsonResponse([
            'error' => 'Verification failed',
            'message' => $verification_result['error']
        ], 500);
    }
    
    // ตรวจสอบ transaction ที่เกี่ยวข้อง
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE data_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$record['id']]);
    $transactions = $stmt->fetchAll();
    
    // เตรียมผลลัพธ์
    $result = [
        'success' => true,
        'record' => [
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
            'created_at' => $record['created_at'],
            'verified_at' => $record['verified_at']
        ],
        'verification' => [
            'is_valid' => $verification_result['is_valid'],
            'record_hash' => $verification_result['record_hash'],
            'calculated_hash' => $verification_result['calculated_hash'],
            'hash_match' => $verification_result['record_hash'] === $verification_result['calculated_hash'],
            'blockchain_integrity' => $verification_result['blockchain_integrity']
        ],
        'blockchain' => [
            'block_id' => $record['block_id'],
            'block_index' => $record['block_index'],
            'block_hash' => $record['block_hash'],
            'block_timestamp' => $record['block_timestamp']
        ],
        'transactions' => array_map(function($tx) {
            return [
                'id' => $tx['id'],
                'type' => $tx['transaction_type'],
                'hash' => $tx['transaction_hash'],
                'status' => $tx['status'],
                'created_at' => $tx['created_at'],
                'confirmed_at' => $tx['confirmed_at']
            ];
        }, $transactions),
        'verification_timestamp' => date('Y-m-d H:i:s')
    ];
    
    // บันทึก verification log
    $stmt = $pdo->prepare("
        INSERT INTO verification_logs (record_id, verifier_id, verification_result, verification_details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $record['id'],
        $user['id'],
        $verification_result['is_valid'] ? 'valid' : 'invalid',
        json_encode($verification_result, JSON_UNESCAPED_UNICODE)
    ]);
    
    // บันทึก API log
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/verify_record.php',
        'GET',
        200,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    jsonResponse($result, 200);
    
} catch (Exception $e) {
    // บันทึก API log สำหรับ error
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/verify_record.php',
        'GET',
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