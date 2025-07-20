<?php
// config.php - การตั้งค่าระบบ
session_start();

/* แสดงผล Erorr */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// การตั้งค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'ed-chain');
define('DB_USER', 'root');
define('DB_PASS', '');

// การตั้งค่าระบบ
define('SITE_URL', 'https://blockchain.krumontree.online');
define('SITE_NAME', 'ระบบป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษา');
define('HASH_ALGORITHM', 'sha256');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // ตรวจสอบและสร้างข้อมูลเริ่มต้นถ้าจำเป็น
    checkAndCreateInitialData($pdo);
    
} catch(PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// ฟังก์ชันตรวจสอบและสร้างข้อมูลเริ่มต้น
function checkAndCreateInitialData($pdo) {
    try {
        // ตรวจสอบว่ามีผู้ใช้ admin หรือไม่
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
        $stmt->execute();
        $adminCount = $stmt->fetch()['count'];
        
        if ($adminCount == 0) {
            // สร้างผู้ใช้ admin เริ่มต้น
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, institution, user_type, api_key, is_active) 
                VALUES ('admin', 'admin@system.com', ?, 'System Administrator', 'System', 'admin', ?, 1)
            ");
            $stmt->execute([
                password_hash('password', PASSWORD_DEFAULT),
                hash('sha256', 'admin' . time())
            ]);
        }
        
        // ตรวจสอบว่ามี Genesis Block หรือไม่
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blockchain_blocks WHERE block_index = 0");
        $stmt->execute();
        $genesisCount = $stmt->fetch()['count'];
        
        if ($genesisCount == 0) {
            // สร้าง Genesis Block
            $stmt = $pdo->prepare("
                INSERT INTO blockchain_blocks (block_index, previous_hash, data_hash, merkle_root, block_hash, created_by) VALUES
                (0, '0000000000000000000000000000000000000000000000000000000000000000', 
                'genesis_data_hash', 'genesis_merkle_root', 
                '0000000000000000000000000000000000000000000000000000000000000001', 1)
            ");
            $stmt->execute();
        }
        
    } catch (Exception $e) {
        // ไม่ต้องหยุดระบบถ้าไม่สามารถสร้างข้อมูลเริ่มต้นได้
        error_log('Failed to create initial data: ' . $e->getMessage());
    }
}

// ฟังก์ชันสำหรับการตรวจสอบการเข้าสู่ระบบ
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ฟังก์ชันสำหรับการตรวจสอบสิทธิ์
function checkPermission($required_type = null) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    if ($required_type && $_SESSION['user_type'] !== $required_type && $_SESSION['user_type'] !== 'admin') {
        header("Location: dashboard.php?error=permission_denied");
        exit();
    }
}

// ฟังก์ชันสำหรับการล็อกเอาท์
function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}

// ฟังก์ชันสำหรับการสร้าง hash
function createHash($data) {
    return hash(HASH_ALGORITHM, $data);
}

// ฟังก์ชันสำหรับการสร้าง API key
function generateApiKey() {
    return hash('sha256', uniqid(rand(), true));
}

// ฟังก์ชันสำหรับการบันทึก log
function logActivity($user_id, $activity, $details = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO api_logs (user_id, endpoint, method, request_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $activity, 'SYSTEM', $details, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
}

// ฟังก์ชันสำหรับการส่ง JSON response
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ฟังก์ชันสำหรับการตรวจสอบ API key
function validateApiKey($api_key) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$api_key]);
    
    return $stmt->fetch();
}
?>