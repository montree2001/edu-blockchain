<?php
// api/blockchain_info.php - API สำหรับข้อมูลบล็อกเชน
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
    $blockchain = new Blockchain($pdo);
    
    // ดึงสถิติทั่วไป
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_blocks FROM blockchain_blocks");
    $stmt->execute();
    $total_blocks = $stmt->fetch()['total_blocks'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_records FROM educational_records");
    $stmt->execute();
    $total_records = $stmt->fetch()['total_records'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_transactions FROM transactions");
    $stmt->execute();
    $total_transactions = $stmt->fetch()['total_transactions'];
    
    // สำหรับสถาบัน ดึงเฉพาะข้อมูลของตนเอง
    if ($user['user_type'] === 'institution') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as user_records FROM educational_records WHERE institution_id = ?");
        $stmt->execute([$user['id']]);
        $user_records = $stmt->fetch()['user_records'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as user_transactions FROM transactions WHERE from_user_id = ?");
        $stmt->execute([$user['id']]);
        $user_transactions = $stmt->fetch()['user_transactions'];
    }
    
    // ดึงบล็อกล่าสุด
    $stmt = $pdo->prepare("
        SELECT * FROM blockchain_blocks 
        ORDER BY block_index DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $latest_block = $stmt->fetch();
    
    // ดึงบล็อกทั้งหมด (จำกัดจำนวน)
    $limit = isset($_GET['blocks_limit']) ? min(intval($_GET['blocks_limit']), 100) : 10;
    $blocks = $blockchain->getAllBlocks();
    $recent_blocks = array_slice(array_reverse($blocks), 0, $limit);
    
    // ตรวจสอบความสมบูรณ์ของบล็อกเชน
    $blockchain_integrity = $blockchain->validateBlockchainIntegrity();
    
    // ดึงธุรกรรมล่าสุด
    $transaction_limit = isset($_GET['transactions_limit']) ? min(intval($_GET['transactions_limit']), 50) : 10;
    $transaction_where = '';
    $transaction_params = [];
    
    if ($user['user_type'] === 'institution') {
        $transaction_where = 'WHERE from_user_id = ?';
        $transaction_params[] = $user['id'];
    }
    
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name as user_name, er.student_id, er.course_code
        FROM transactions t
        LEFT JOIN users u ON t.from_user_id = u.id
        LEFT JOIN educational_records er ON t.data_id = er.id
        {$transaction_where}
        ORDER BY t.created_at DESC
        LIMIT ?
    ");
    $transaction_params[] = $transaction_limit;
    $stmt->execute($transaction_params);
    $recent_transactions = $stmt->fetchAll();
    
    // จัดเตรียมข้อมูลผลลัพธ์
    $result = [
        'success' => true,
        'blockchain_info' => [
            'total_blocks' => intval($total_blocks),
            'total_records' => intval($total_records),
            'total_transactions' => intval($total_transactions),
            'blockchain_integrity' => $blockchain_integrity,
            'genesis_block' => $blocks[0] ?? null,
            'latest_block' => $latest_block ? [
                'id' => $latest_block['id'],
                'index' => $latest_block['block_index'],
                'hash' => $latest_block['block_hash'],
                'previous_hash' => $latest_block['previous_hash'],
                'timestamp' => $latest_block['timestamp'],
                'data_hash' => $latest_block['data_hash']
            ] : null
        ],
        'recent_blocks' => array_map(function($block) {
            return [
                'id' => $block['id'],
                'index' => $block['block_index'],
                'hash' => $block['block_hash'],
                'previous_hash' => $block['previous_hash'],
                'timestamp' => $block['timestamp'],
                'data_hash' => $block['data_hash'],
                'creator_name' => $block['creator_name']
            ];
        }, $recent_blocks),
        'recent_transactions' => array_map(function($tx) {
            return [
                'id' => $tx['id'],
                'type' => $tx['transaction_type'],
                'hash' => $tx['transaction_hash'],
                'status' => $tx['status'],
                'user_name' => $tx['user_name'],
                'student_id' => $tx['student_id'],
                'course_code' => $tx['course_code'],
                'created_at' => $tx['created_at'],
                'confirmed_at' => $tx['confirmed_at']
            ];
        }, $recent_transactions)
    ];
    
    // เพิ่มข้อมูลสำหรับสถาบัน
    if ($user['user_type'] === 'institution') {
        $result['user_stats'] = [
            'user_records' => intval($user_records),
            'user_transactions' => intval($user_transactions),
            'institution_name' => $user['institution']
        ];
    }
    
    // เพิ่มข้อมูลสำหรับ admin
    if ($user['user_type'] === 'admin') {
        // สถิติรายสถาบัน
        $stmt = $pdo->prepare("
            SELECT u.institution, u.full_name, COUNT(er.id) as record_count
            FROM users u
            LEFT JOIN educational_records er ON u.id = er.institution_id
            WHERE u.user_type = 'institution' AND u.is_active = 1
            GROUP BY u.id
            ORDER BY record_count DESC
        ");
        $stmt->execute();
        $institution_stats = $stmt->fetchAll();
        
        // สถิติรายเดือน
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
            FROM educational_records
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute();
        $monthly_stats = $stmt->fetchAll();
        
        $result['admin_stats'] = [
            'institution_stats' => $institution_stats,
            'monthly_stats' => $monthly_stats
        ];
    }
    
    $result['retrieved_at'] = date('Y-m-d H:i:s');
    
    // บันทึก API log
    $stmt = $pdo->prepare("
        INSERT INTO api_logs (user_id, endpoint, method, request_data, response_status, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        '/api/blockchain_info.php',
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
        '/api/blockchain_info.php',
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