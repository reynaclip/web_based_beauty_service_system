<?php
// admin/dashboard.php
require_once '../config.php';

// Check login dan role
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . '/pelanggan/dashboard.php');
}

// Gunakan koneksi dari config.php
$conn = getDBConnection();

// Ambil profil admin
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil statistik
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $conn->query($query);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total pelanggan
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'pelanggan'";
$stmt = $conn->query($query);
$stats['total_pelanggan'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total admin
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
$stmt = $conn->query($query);
$stats['total_admin'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total layanan aktif
$query = "SELECT COUNT(*) as total FROM layanan WHERE status = 'aktif'";
$stmt = $conn->query($query);
$stats['total_layanan'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total pesanan hari ini
$query = "SELECT COUNT(*) as total FROM pesanan WHERE DATE(created_at) = CURDATE()";
$stmt = $conn->query($query);
$stats['pesanan_hari_ini'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total pendapatan hari ini
$query = "SELECT SUM(total_harga) as total FROM pesanan WHERE DATE(created_at) = CURDATE() AND status = 'selesai'";
$stmt = $conn->query($query);
$stats['pendapatan_hari_ini'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total pendapatan bulan ini
$query = "SELECT SUM(total_harga) as total FROM pesanan WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'selesai'";
$stmt = $conn->query($query);
$stats['pendapatan_bulan_ini'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pesanan terbaru (7 hari terakhir)
$query = "SELECT p.*, u.nama_lengkap, u.foto as foto_user, l.nama_layanan, l.foto as foto_layanan 
          FROM pesanan p 
          JOIN users u ON p.user_id = u.id 
          JOIN layanan l ON p.layanan_id = l.id 
          WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY p.created_at DESC 
          LIMIT 10";
$stmt = $conn->query($query);
$pesanan_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pelanggan baru (30 hari terakhir)
$query = "SELECT * FROM users WHERE role = 'pelanggan' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 8";
$stmt = $conn->query($query);
$pelanggan_baru = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #ff6b9d;
            --light-pink: #ffe6ee;
            --dark-pink: #d81b60;
            --white: #ffffff;
            --pastel-pink: #ffd6e7;
            --soft-pink: #ffb6d0;
            --medium-gray: #e9ecef;
            --dark-gray: #495057;
            --text-color: #333333;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 15px;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-pink);
            min-height: 100vh;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* =========== SIDEBAR =========== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .sidebar-header h3 {
            margin: 10px 0 5px;
            font-size: 1.1rem;
            color: white;
        }
        
        .sidebar-header small {
            color: #a0aec0;
            font-size: 0.85rem;
            display: block;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: 15px;
            margin-top: 5px;
            display: inline-block;
        }
        
        .sidebar-menu {
            flex: 1;
            padding: 15px 0;
            overflow-y: auto;
        }
        
        .sidebar-menu::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 3px 10px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #cbd5e0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-menu a.active {
            background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(231, 70, 148, 0.3);
        }
        
        .sidebar-menu a i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }
        
        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .brand-logo {
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }
        
        .brand-logo span {
            color: var(--primary-pink);
        }
        
        /* =========== MAIN CONTENT =========== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        /* =========== RESPONSIVE =========== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 5px 0 20px rgba(255, 0, 98, 0.98);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
                padding-top: 70px;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 101;
                background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
                color: white;
                border: none;
                width: 45px;
                height: 45px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 1.2rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                transition: all 0.3s ease;
            }
            
            .menu-toggle:hover {
                transform: scale(1.1);
            }
        }
        
        /* =========== DASHBOARD STYLES =========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        .dashboard-header h1 {
            color: var(--dark-pink);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary-pink);
            margin: 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-info .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            color: var(--dark-pink);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        
        .welcome-text h2 {
            margin-bottom: 10px;
            font-size: 1.7rem;
        }
        
        .welcome-text p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--dark-pink);
            color: var(--white);
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: var(--shadow);
        }
        
        .btn-primary:hover {
            background: var(--primary-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-profile {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--white);
            color: var(--dark-pink);
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            text-align: center;
            border: 2px solid var(--dark-pink);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: var(--shadow);
            margin-left: 10px;
        }
        
        .btn-profile:hover {
            background: var(--pastel-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--pastel-pink);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--dark-pink);
        }
        
        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-pink);
        }
        
        .stats-highlight {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: white;
        }
        
        .stats-highlight .stat-number {
            color: white;
        }
        
        .stats-highlight .stat-info h3 {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .stats-highlight .stat-icon {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .table-container h2 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1200px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: var(--pastel-pink);
            color: var(--dark-pink);
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        tr:hover {
            background-color: var(--light-pink);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .layanan-image-small {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid var(--pastel-pink);
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .badge-menunggu {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-dikonfirmasi {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-diproses {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-selesai {
            background-color: #c3e6cb;
            color: #155724;
        }
        
        .badge-dibatalkan {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-admin {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-pelanggan {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-aktif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-nonaktif {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--pastel-pink);
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .empty-state p {
            color: var(--dark-gray);
            margin-bottom: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--pastel-pink);
        }
        
        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-color);
            display: block;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            background: var(--pastel-pink);
        }
        
        .action-card i {
            font-size: 2rem;
            color: var(--dark-pink);
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            font-size: 1rem;
            color: var(--dark-pink);
            margin: 0;
        }
        
        .action-link {
            text-decoration: none;
            color: inherit;
        }
        
        .btn-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: var(--white);
            color: var(--dark-pink);
            border: 2px solid var(--dark-pink);
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-small:hover {
            background: var(--pastel-pink);
            transform: translateY(-2px);
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
                margin-top: 10px;
            }
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            .two-columns {
                gap: 20px;
            }
        }
        
        /* Additional Styles */
        .status-aktif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-nonaktif {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .info-text {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-top: 5px;
            display: block;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                         alt="Admin Avatar"
                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Administrator'); ?></h3>
                <small>Administrator</small>
            </div>
            
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/layanan/"><i class="fas fa-spa"></i> Manajemen Layanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pesanan/"><i class="fas fa-shopping-cart"></i> Manajemen Pesanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pelanggan/"><i class="fas fa-user-friends"></i> Data Pelanggan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/backup.php"><i class="fas fa-database"></i> Backup Data</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/profil.php"><i class="fas fa-user-cog"></i> Profil Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Kunjungi Situs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            
            <div class="sidebar-footer">
                <div class="brand-logo">LUNELLE<span>BEAUTY</span></div>
                <small>Beauty & Spa Center</small>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?></span>
                    <a href="<?php echo BASE_URL; ?>/admin/profil.php" class="user-avatar" title="Profil Saya">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </a>
                </div>
            </div>
            
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-text">
                    <h2>Selamat Datang, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?>!</h2>
                    <p>Kelola semua aspek sistem salon kecantikan Anda dari dashboard ini.</p>
                </div>
                <div>
                    <a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="btn-primary">
                        <i class="fas fa-shopping-cart"></i> Kelola Pesanan
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/profil.php" class="btn-profile">
                        <i class="fas fa-user-cog"></i> Profil Saya
                    </a>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="<?php echo BASE_URL; ?>/admin/layanan/tambah.php" class="action-card">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Tambah Layanan</h3>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="action-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Lihat Pesanan</h3>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="action-card">
                    <i class="fas fa-user-friends"></i>
                    <h3>Data Pelanggan</h3>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/layanan/" class="action-card">
                    <i class="fas fa-spa"></i>
                    <h3>Kelola Layanan</h3>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/profil.php" class="action-card">
                    <i class="fas fa-user-cog"></i>
                    <h3>Profil Saya</h3>
                </a>
                <a href="<?php echo BASE_URL; ?>" class="action-card" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <h3>Kunjungi Situs</h3>
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card stats-highlight">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Pelanggan</h3>
                        <div class="stat-number"><?php echo $stats['total_pelanggan']; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-spa"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Layanan</h3>
                        <div class="stat-number"><?php echo $stats['total_layanan']; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pesanan Hari Ini</h3>
                        <div class="stat-number"><?php echo $stats['pesanan_hari_ini']; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pendapatan Hari Ini</h3>
                        <div class="stat-number"><?php echo formatRupiah($stats['pendapatan_hari_ini']); ?></div>
                    </div>
                </div>
                
                <div class="stat-card stats-highlight">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pendapatan Bulan Ini</h3>
                        <div class="stat-number"><?php echo formatRupiah($stats['pendapatan_bulan_ini']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Dua tabel: Pesanan Terbaru dan Pelanggan Baru -->
            <div class="two-columns">
                <!-- Pesanan Terbaru -->
                <div class="table-container">
                    <h2><i class="fas fa-history"></i> Pesanan 7 Hari Terakhir</h2>
                    <?php if (count($pesanan_terbaru) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesanan_terbaru as $item): ?>
                            <tr>
                                <td class="user-cell">
                                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($item['foto_user'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama_lengkap']); ?>"
                                         class="user-avatar-small"
                                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['nama_lengkap']); ?></div>
                                        <small style="color: var(--dark-gray); font-size: 0.8rem;"><?php echo $item['kode_pesanan']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($item['foto_layanan'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($item['nama_layanan']); ?>" 
                                             class="layanan-image-small"
                                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                                        <span style="font-size: 0.9rem;"><?php echo htmlspecialchars(substr($item['nama_layanan'], 0, 15)) . (strlen($item['nama_layanan']) > 15 ? '...' : ''); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($item['tanggal_pesanan'])); ?>
                                    <br>
                                    <small style="font-size: 0.8rem;"><?php echo date('H:i', strtotime($item['jam_pesanan'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadge($item['status']); ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/pesanan/detail.php?id=<?php echo $item['id']; ?>" 
                                       class="btn-small" 
                                       title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Tidak Ada Pesanan 7 Hari Terakhir</h3>
                        <p>Belum ada pesanan yang dibuat dalam 7 hari terakhir.</p>
                        <a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="btn-primary">
                            <i class="fas fa-shopping-cart"></i> Lihat Semua Pesanan
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pelanggan Baru -->
                <div class="table-container">
                    <h2><i class="fas fa-user-plus"></i> Pelanggan 30 Hari Terakhir</h2>
                    <?php if (count($pelanggan_baru) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pelanggan_baru as $user): ?>
                            <tr>
                                <td class="user-cell">
                                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($user['foto'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>"
                                         class="user-avatar-small"
                                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                                        <small style="color: var(--dark-gray); font-size: 0.8rem;"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status'] ?? 'aktif'; ?>">
                                        <?php echo ucfirst($user['status'] ?? 'aktif'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/pelanggan/edit.php?id=<?php echo $user['id']; ?>" 
                                       class="btn-small" 
                                       title="Edit Pelanggan">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Tidak Ada Pelanggan Baru</h3>
                        <p>Belum ada pelanggan yang mendaftar dalam 30 hari terakhir.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (menuToggle && sidebar) {
                // Set initial display based on screen size
                if (window.innerWidth <= 768) {
                    menuToggle.style.display = 'block';
                } else {
                    menuToggle.style.display = 'none';
                }
                
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    menuToggle.innerHTML = sidebar.classList.contains('active') 
                        ? '<i class="fas fa-times"></i>' 
                        : '<i class="fas fa-bars"></i>';
                });
                
                // Tutup sidebar saat klik di luar (hanya di mobile)
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                            sidebar.classList.remove('active');
                            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                        }
                    }
                });
                
                // Set sidebar height
                sidebar.style.height = window.innerHeight + 'px';
            }
            
            // Pastikan semua link action-card bekerja
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#' || !this.getAttribute('href')) {
                        e.preventDefault();
                        alert('Fitur ini sedang dalam pengembangan.');
                    }
                });
            });
            
            // Cek apakah file yang dituju ada
            async function checkUrlExists(url) {
                try {
                    const response = await fetch(url, { method: 'HEAD' });
                    return response.ok;
                } catch (error) {
                    return false;
                }
            }
            
            // Validasi link sebelum navigasi (opsional)
            const importantLinks = document.querySelectorAll('.btn-primary, .action-card, .sidebar-menu a');
            importantLinks.forEach(link => {
                link.addEventListener('click', async function(e) {
                    const href = this.getAttribute('href');
                    if (href && !href.startsWith('http') && !href.startsWith('mailto:') && !href.startsWith('tel:') && !href.startsWith('#') && !this.classList.contains('logout')) {
                        const exists = await checkUrlExists(href);
                        if (!exists && !href.includes('logout')) {
                            e.preventDefault();
                            alert('Halaman yang dituju tidak ditemukan. Pastikan file sudah dibuat.');
                        }
                    }
                });
            });
            
            // Tambahkan efek hover pada avatar di header
            const headerAvatar = document.querySelector('.user-info .user-avatar');
            if (headerAvatar) {
                headerAvatar.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                    this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.2)';
                });
                
                headerAvatar.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            }
        });
        
        // Auto refresh setiap 60 detik untuk update status (opsional)
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Window resize handler
        window.addEventListener('resize', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            // Update menu toggle visibility
            if (menuToggle) {
                if (window.innerWidth <= 768) {
                    menuToggle.style.display = 'block';
                } else {
                    menuToggle.style.display = 'none';
                    // Reset sidebar state on desktop
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                }
            }
            
            // Update sidebar height
            if (sidebar) {
                sidebar.style.height = window.innerHeight + 'px';
            }
        });
    </script>
</body>
</html>