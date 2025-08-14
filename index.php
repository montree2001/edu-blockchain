<?php
// index.php - หน้าแรกของระบบ
require_once 'config.php';

// ถ้าเข้าสู่ระบบแล้ว ให้ไปหน้า dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// ดึงสถิติพื้นฐานสำหรับแสดงหน้าแรก
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $stmt->execute();
    $total_users = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_blocks FROM blockchain_blocks");
    $stmt->execute();
    $total_blocks = $stmt->fetch()['total_blocks'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_records FROM educational_records");
    $stmt->execute();
    $total_records = $stmt->fetch()['total_records'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as verified_records FROM educational_records WHERE block_id IS NOT NULL");
    $stmt->execute();
    $verified_records = $stmt->fetch()['verified_records'];
    
} catch (Exception $e) {
    $total_users = 0;
    $total_blocks = 0;
    $total_records = 0;
    $verified_records = 0;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
            font-family: 'Prompt', sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,0 1000,300 1000,1000 0,700"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .blockchain-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 2rem auto;
            backdrop-filter: blur(10px);
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem auto;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
            border: none;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-custom {
            border: 2px solid white;
            color: white;
            background: transparent;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-custom:hover {
            background: white;
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .how-it-works {
            padding: 80px 0;
            background: white;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto 1rem auto;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        .security-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-shield-check text-primary me-2"></i>
                <span class="text-primary">Blockchain</span> EDU
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">คุณสมบัติ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">วิธีการทำงาน</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#stats">สถิติ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">ติดต่อ</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-primary-custom" href="login.php">เข้าสู่ระบบ</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="blockchain-icon floating">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    
                    <h1 class="display-4 fw-bold mb-4">
                        ระบบป้องกันการเปลี่ยนแปลงข้อมูลทางการศึกษา
                    </h1>
                    
                    <p class="lead mb-4">
                        ด้วยเทคโนโลยี Blockchain เฟรมเวิร์ค เพื่อความปลอดภัยและความน่าเชื่อถือ
                        ของข้อมูลการศึกษาระดับสูง
                    </p>
                    
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2">
                            <i class="bi bi-check-circle me-3"></i>
                            ข้อมูลไม่สามารถแก้ไขหรือปลอมแปลงได้
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle me-3"></i>
                            ตรวจสอบความถูกต้องได้ทันที
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle me-3"></i>
                            รองรับการเชื่อมต่อผ่าน API
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle me-3"></i>
                            เหมาะสำหรับสถาบันการศึกษาทุกขนาด
                        </li>
                    </ul>
                    
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="register.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-person-plus me-2"></i>สมัครสมาชิก
                        </a>
                        <a href="login.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="text-center">
                        <div class="position-relative">
                            <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 400'><rect fill='rgba(255,255,255,0.1)' width='500' height='400' rx='20'/><circle fill='rgba(255,255,255,0.2)' cx='250' cy='200' r='100'/><path fill='none' stroke='rgba(255,255,255,0.4)' stroke-width='2' d='M150,200 L350,200 M250,100 L250,300'/><text fill='white' x='250' y='210' text-anchor='middle' font-size='24' font-family='Arial'>🔒</text></svg>" 
                                 alt="Blockchain Security" class="img-fluid floating" style="max-width: 400px;">
                            <div class="security-badge">
                                <i class="bi bi-shield-check me-1"></i>
                                256-bit Encryption
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">คุณสมบัติหลักของระบบ</h2>
                    <p class="lead text-muted">
                        ระบบที่ออกแบบมาเพื่อตอบสนองความต้องการของสถาบันการศึกษาสมัยใหม่
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <h4 class="fw-bold mb-3">ความปลอดภัยสูง</h4>
                            <p class="text-muted">
                                ใช้เทคโนโลยี Blockchain และการเข้ารหัส 256-bit 
                                เพื่อปกป้องข้อมูลการศึกษาจากการปลอมแปลง
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="bi bi-search"></i>
                            </div>
                            <h4 class="fw-bold mb-3">ตรวจสอบได้ทันที</h4>
                            <p class="text-muted">
                                ระบบตรวจสอบความถูกต้องของข้อมูลได้ในเวลาจริง
                                พร้อมการแสดงผลที่ชัดเจนและเข้าใจง่าย
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>
                            <h4 class="fw-bold mb-3">API Integration</h4>
                            <p class="text-muted">
                                รองรับการเชื่อมต่อผ่าน RESTful API 
                                เพื่อการบูรณาการกับระบบเดิมของสถาบัน
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                                <i class="bi bi-diagram-3"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Blockchain Technology</h4>
                            <p class="text-muted">
                                เทคโนโลยี Blockchain แบบ Private และ Hybrid 
                                ที่เหมาะสมสำหรับองค์กรการศึกษา
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                                <i class="bi bi-people"></i>
                            </div>
                            <h4 class="fw-bold mb-3">จัดการผู้ใช้</h4>
                            <p class="text-muted">
                                ระบบจัดการผู้ใช้แบบหลายระดับ พร้อมการควบคุมสิทธิ์
                                และการติดตามการใช้งาน
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body p-4 text-center">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h4 class="fw-bold mb-3">รายงานและสถิติ</h4>
                            <p class="text-muted">
                                ระบบรายงานและการวิเคราะห์ข้อมูลการใช้งาน
                                พร้อมการแสดงผลแบบ Dashboard
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">วิธีการทำงานของระบบ</h2>
                    <p class="lead text-muted">
                        ขั้นตอนง่าย ๆ เพียง 4 ขั้นตอนในการใช้งานระบบ
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="step-number">1</div>
                        <h5 class="fw-bold mb-3">ลงทะเบียน</h5>
                        <p class="text-muted">
                            สมัครสมาชิกและรับ API Key สำหรับการเชื่อมต่อระบบ
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="step-number">2</div>
                        <h5 class="fw-bold mb-3">บันทึกข้อมูล</h5>
                        <p class="text-muted">
                            ส่งข้อมูลการศึกษาผ่าน Web Interface หรือ API
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="step-number">3</div>
                        <h5 class="fw-bold mb-3">เข้ารหัสและบันทึก</h5>
                        <p class="text-muted">
                            ระบบเข้ารหัสข้อมูลและบันทึกลงใน Blockchain
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="step-number">4</div>
                        <h5 class="fw-bold mb-3">ตรวจสอบ</h5>
                        <p class="text-muted">
                            ตรวจสอบความถูกต้องของข้อมูลได้ทุกเมื่อ
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="stats" class="stats-section">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">สถิติการใช้งานปัจจุบัน</h2>
                    <p class="lead text-muted">
                        ตัวเลขที่แสดงให้เห็นถึงความน่าเชื่อถือของระบบ
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card">
                        <div class="stat-number"><?php echo number_format($total_users); ?></div>
                        <h5 class="fw-bold mb-2">ผู้ใช้งาน</h5>
                        <p class="text-muted mb-0">สถาบันและองค์กรที่เชื่อถือ</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card">
                        <div class="stat-number"><?php echo number_format($total_blocks); ?></div>
                        <h5 class="fw-bold mb-2">บล็อกเชน</h5>
                        <p class="text-muted mb-0">บล็อกที่สร้างขึ้นในระบบ</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card">
                        <div class="stat-number"><?php echo number_format($total_records); ?></div>
                        <h5 class="fw-bold mb-2">ข้อมูลการศึกษา</h5>
                        <p class="text-muted mb-0">รายการที่บันทึกในระบบ</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card">
                        <div class="stat-number"><?php echo $total_records > 0 ? number_format(($verified_records / $total_records) * 100, 1) : 0; ?>%</div>
                        <h5 class="fw-bold mb-2">อัตราความสำเร็จ</h5>
                        <p class="text-muted mb-0">ข้อมูลที่ยืนยันในบล็อกเชน</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center text-white">
                    <h2 class="display-5 fw-bold mb-4">เริ่มต้นใช้งานระบบ</h2>
                    <p class="lead mb-4">
                        พร้อมที่จะยกระดับความปลอดภัยของข้อมูลการศึกษาหรือยัง?
                    </p>
                    
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="register.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-person-plus me-2"></i>สมัครสมาชิกฟรี
                        </a>
                        <a href="login.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                    
                    <div class="row mt-5">
                        <div class="col-md-4">
                            <i class="bi bi-envelope display-6 mb-3"></i>
                            <h5>อีเมล</h5>
                            <p>support@blockchain-edu.com</p>
                        </div>
                        <div class="col-md-4">
                            <i class="bi bi-telephone display-6 mb-3"></i>
                            <h5>โทรศัพท์</h5>
                            <p>02-XXX-XXXX</p>
                        </div>
                        <div class="col-md-4">
                            <i class="bi bi-geo-alt display-6 mb-3"></i>
                            <h5>ที่อยู่</h5>
                            <p>กรุงเทพมหานคร ประเทศไทย</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-check text-primary me-2 fs-3"></i>
                        <div>
                            <h5 class="mb-0">Blockchain EDU</h5>
                            <small class="text-muted">Educational Data Protection System</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        &copy; <?php echo date('Y'); ?> Blockchain EDU. 
                        สงวนลิขสิทธิ์ทั้งหมด.
                    </p>
                    <small class="text-muted">
                        พัฒนาโดยใช้เทคโนโลยี Blockchain และ PHP
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to navbar
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });

        // Counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString();
                }, 16);
            });
        }

        // Trigger counter animation when stats section is visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('#stats');
        if (statsSection) {
            observer.observe(statsSection);
        }
    </script>
</body>
</html>