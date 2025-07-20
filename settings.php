<?php
// settings.php - การตั้งค่าระบบบล็อกเชนสำหรับข้อมูลการศึกษา

// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// การตั้งค่าฐานข้อมูล MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'blockchain_education_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// การตั้งค่าระบบ
define('SITE_NAME', 'ระบบป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษา');
define('SITE_URL', 'http://localhost/blockchain-education-system');
define('ADMIN_EMAIL', 'admin@education-blockchain.local');

// การตั้งค่าความปลอดภัย
define('SECRET_KEY', 'your_secret_key_here_change_this_in_production');
define('SESSION_TIMEOUT', 3600); // 1 ชั่วโมง
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_TIME', 900); // 15 นาที

// การตั้งค่า Blockchain
define('BLOCKCHAIN_NODE_URL', 'http://localhost:8545'); // Geth node
define('SMART_CONTRACT_ADDRESS', '0x1234567890123456789012345678901234567890'); // แทนที่ด้วย contract address จริง
define('BLOCKCHAIN_NETWORK_ID', '1337'); // Private network ID
define('GAS_LIMIT', 3000000);
define('GAS_PRICE', '20000000000'); // 20 Gwei

// การตั้งค่า API
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // requests per hour
define('API_KEY_LENGTH', 32);
define('API_SECRET_LENGTH', 64);

// การตั้งค่าไฟล์และการอัปโหลด
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// การตั้งค่า Logging
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_ROTATION_SIZE', 10485760); // 10MB

// การตั้งค่าการแจ้งเตือน
define('NOTIFICATION_EMAIL_ENABLED', true);
define('NOTIFICATION_SMS_ENABLED', false);

// การตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// การตั้งค่าการแสดงผลข้อผิดพลาด (สำหรับ development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ฟังก์ชันช่วยสำหรับการเชื่อมต่อฐานข้อมูล
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("การเชื่อมต่อฐานข้อมูลล้มเหลว");
    }
}

// ฟังก์ชันสำหรับ hashing รหัสผ่าน
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3,         // 3 threads
    ]);
}

// ฟังก์ชันสำหรับตรวจสอบรหัสผ่าน
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ฟังก์ชันสำหรับสร้าง API Key
function generateApiKey() {
    return bin2hex(random_bytes(API_KEY_LENGTH / 2));
}

// ฟังก์ชันสำหรับสร้าง API Secret
function generateApiSecret() {
    return bin2hex(random_bytes(API_SECRET_LENGTH / 2));
}

// ฟังก์ชันสำหรับสร้าง Token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// ฟังก์ชันสำหรับการ logging
function writeLog($level, $message, $context = []) {
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$level}: {$message}";
    
    if (!empty($context)) {
        $logEntry .= " Context: " . json_encode($context);
    }
    
    $logEntry .= PHP_EOL;
    
    $logFile = LOG_PATH . 'system_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// ฟังก์ชันสำหรับการสร้าง hash ของข้อมูล (สำหรับ blockchain)
function createDataHash($data) {
    return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// ฟังก์ชันสำหรับการตรวจสอบ CSRF Token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ฟังก์ชันสำหรับการ sanitize ข้อมูล
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ฟังก์ชันสำหรับการตรวจสอบสิทธิ์การเข้าถึง
function checkAuthentication() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    // ตรวจสอบ session timeout
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    // อัปเดตเวลาล่าสุดที่ใช้งาน
    $_SESSION['last_activity'] = time();
    
    return true;
}

// ฟังก์ชันสำหรับการส่งการแจ้งเตือนทางอีเมล
function sendNotificationEmail($to, $subject, $message) {
    if (!NOTIFICATION_EMAIL_ENABLED) {
        return false;
    }
    
    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// การตั้งค่าข้อผิดพลาดสำหรับ Production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// เริ่มต้น session หากยังไม่ได้เริ่มต้น
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า header ความปลอดภัย
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

?>