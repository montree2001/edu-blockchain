<?php
// logout.php - การออกจากระบบ
require_once 'config.php';

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (isLoggedIn()) {
    // บันทึก log การออกจากระบบ
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // ทำลาย session
    session_destroy();
}

// เปลี่ยนเส้นทางไปหน้า login พร้อมข้อความ
header("Location: login.php?message=logout");
exit();
?>