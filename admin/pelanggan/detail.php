<?php
// admin/pelanggan/detail.php
require_once '../../config.php';

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

// Ambil ID pelanggan dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    $_SESSION['error'] = 'ID pelanggan tidak valid';
    redirect(BASE_URL . '/admin/pelanggan/');
}

// Ambil detail pelanggan
$query = "SELECT * FROM users WHERE id = :id AND role = 'pelanggan'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$pelanggan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pelanggan) {
    $_SESSION['error'] = 'Pelanggan tidak ditemukan';
    redirect(BASE_URL . '/admin/pelanggan/');
}

// Ambil statistik pesanan pelanggan
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_spent,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_orders,
            COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'diproses' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'dibatalkan' THEN 1 END) as cancelled_orders
          FROM pesanan 
          WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil riwayat pesanan terbaru pelanggan
$query = "SELECT p.*, l.nama_layanan, l.foto as foto_layanan 
          FROM pesanan p 
          JOIN layanan l ON p.layanan_id = l.id 
          WHERE p.user_id = :user_id 
          ORDER BY p.created_at DESC 
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
$stmt->execute();
$riwayat_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi helper untuk badge status
function getStatusBadge($status) {
    switch($status) {
        case 'aktif': return 'badge-aktif';
        case 'nonaktif': return 'badge-nonaktif';
        case 'menunggu': return 'badge-menunggu';
        case 'dikonfirmasi': return 'badge-dikonfirmasi';
        case 'diproses': return 'badge-diproses';
        case 'selesai': return 'badge-selesai';
        case 'dibatalkan': return 'badge-dibatalkan';
        default: return 'badge-secondary';
    }
}

// Fungsi helper untuk format tanggal
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '-';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggan - Admin</title>
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
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.3);
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
        
        /* =========== DETAIL PAGE STYLES =========== */
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
        }
        
        .back-button {
            margin-bottom: 20px;
        }
        
        .back-button a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--dark-pink);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-button a:hover {
            color: var(--primary-pink);
            transform: translateX(-5px);
        }
        
        .detail-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            gap: 30px;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--pastel-pink);
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--pastel-pink);
            box-shadow: var(--shadow);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-title h2 {
            color: var(--dark-pink);
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .profile-title .badge {
            font-size: 0.9rem;
            padding: 8px 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: var(--light-pink);
            border-radius: var(--border-radius);
            padding: 25px;
            transition: var(--transition);
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .info-card h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--pastel-pink);
            padding-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px dashed var(--pastel-pink);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 40%;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .info-value {
            width: 60%;
            color: var(--text-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 20px 0 30px;
        }
        
        .stat-item {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            border: 2px solid var(--pastel-pink);
        }
        
        .stat-item .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-pink);
            margin-bottom: 5px;
        }
        
        .stat-item .stat-label {
            color: var(--dark-gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .badge-aktif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-nonaktif {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-top: 30px;
            overflow-x: auto;
        }
        
        .table-container h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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
        
        .layanan-image-small {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid var(--pastel-pink);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
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
        
        .btn-secondary {
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
        }
        
        .btn-secondary:hover {
            background: var(--pastel-pink);
            transform: translateY(-2px);
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
            font-size: 0.85rem;
        }
        
        .btn-small:hover {
            background: var(--pastel-pink);
            transform: translateY(-2px);
        }
        
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label, .info-value {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
                    <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/layanan/"><i class="fas fa-spa"></i> Manajemen Layanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pesanan/"><i class="fas fa-shopping-cart"></i> Manajemen Pesanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="active"><i class="fas fa-user-friends"></i> Data Pelanggan</a></li>
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
                <h1><i class="fas fa-user"></i> Detail Pelanggan</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <!-- Back Button -->
            <div class="back-button">
                <a href="<?php echo BASE_URL; ?>/admin/pelanggan/">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pelanggan
                </a>
            </div>
            
            <!-- Detail Container -->
            <div class="detail-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($pelanggan['foto'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($pelanggan['nama_lengkap']); ?>"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($pelanggan['nama_lengkap']); ?></h2>
                        <span class="badge <?php echo getStatusBadge($pelanggan['status']); ?>">
                            <?php echo ucfirst($pelanggan['status']); ?>
                        </span>
                        <?php if (isset($pelanggan['email_verified_at']) && $pelanggan['email_verified_at']): ?>
                            <span class="badge badge-aktif" style="margin-left: 10px;">
                                <i class="fas fa-check-circle"></i> Email Terverifikasi
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_orders'] ?: 0; ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['completed_orders'] ?: 0; ?></div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['pending_orders'] ?: 0; ?></div>
                        <div class="stat-label">Menunggu</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['processing_orders'] ?: 0; ?></div>
                        <div class="stat-label">Diproses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['cancelled_orders'] ?: 0; ?></div>
                        <div class="stat-label">Dibatalkan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo formatRupiah($stats['total_spent'] ?: 0); ?></div>
                        <div class="stat-label">Total Belanja</div>
                    </div>
                </div>
                
                <!-- Info Grid -->
                <div class="info-grid">
                    <!-- Informasi Pribadi -->
                    <div class="info-card">
                        <h3><i class="fas fa-user-circle"></i> Informasi Pribadi</h3>
                        
                        <div class="info-row">
                            <div class="info-label">Nama Lengkap</div>
                            <div class="info-value"><?php echo htmlspecialchars($pelanggan['nama_lengkap']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($pelanggan['email']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">No. Telepon</div>
                            <div class="info-value"><?php echo htmlspecialchars($pelanggan['no_telepon'] ?: '-'); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Alamat</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($pelanggan['alamat'] ?: '-')); ?></div>
                        </div>
                    </div>
                    
                    <!-- Informasi Akun -->
                    <div class="info-card">
                        <h3><i class="fas fa-cog"></i> Informasi Akun</h3>
                        
                        <div class="info-row">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($pelanggan['username'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Role</div>
                            <div class="info-value"><?php echo ucfirst($pelanggan['role']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge <?php echo getStatusBadge($pelanggan['status']); ?>">
                                    <?php echo ucfirst($pelanggan['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Tanggal Daftar</div>
                            <div class="info-value"><?php echo formatDate($pelanggan['created_at']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Terakhir Update</div>
                            <div class="info-value"><?php echo formatDate($pelanggan['updated_at']); ?></div>
                        </div>
                        
                        <?php if (isset($pelanggan['last_login'])): ?>
                        <div class="info-row">
                            <div class="info-label">Terakhir Login</div>
                            <div class="info-value"><?php echo $pelanggan['last_login'] ? formatDate($pelanggan['last_login']) : '-'; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($pelanggan['status'] == 'aktif'): ?>
                        <a href="toggle_status.php?id=<?php echo $pelanggan['id']; ?>&status=nonaktif" 
                           class="btn-secondary"
                           onclick="return confirm('Yakin ingin menonaktifkan pelanggan ini?')">
                            <i class="fas fa-ban"></i> Nonaktifkan
                        </a>
                    <?php else: ?>
                        <a href="toggle_status.php?id=<?php echo $pelanggan['id']; ?>&status=aktif" 
                           class="btn-primary"
                           onclick="return confirm('Yakin ingin mengaktifkan pelanggan ini?')">
                            <i class="fas fa-check"></i> Aktifkan
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo BASE_URL; ?>/admin/pesanan/?user_id=<?php echo $pelanggan['id']; ?>" class="btn-secondary">
                        <i class="fas fa-shopping-cart"></i> Lihat Semua Pesanan
                    </a>
                </div>
            </div>
            
            <!-- Riwayat Pesanan -->
            <div class="table-container">
                <h3><i class="fas fa-history"></i> Riwayat Pesanan (10 Terbaru)</h3>
                
                <?php if (count($riwayat_pesanan) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_pesanan as $pesanan): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pesanan['kode_pesanan']); ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($pesanan['foto_layanan'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($pesanan['nama_layanan']); ?>" 
                                         class="layanan-image-small"
                                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                                    <span><?php echo htmlspecialchars($pesanan['nama_layanan']); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($pesanan['tanggal_pesanan'])); ?>
                                <br>
                                <small><?php echo date('H:i', strtotime($pesanan['jam_pesanan'])); ?></small>
                            </td>
                            <td><?php echo formatRupiah($pesanan['total_harga']); ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadge($pesanan['status']); ?>">
                                    <?php echo ucfirst($pesanan['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/admin/pesanan/detail.php?id=<?php echo $pesanan['id']; ?>" 
                                   class="btn-small"
                                   title="Lihat Detail Pesanan">
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
                    <h3>Belum Ada Pesanan</h3>
                    <p>Pelanggan ini belum melakukan pemesanan apapun.</p>
                </div>
                <?php endif; ?>
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
        });
        
        // Window resize handler
        window.addEventListener('resize', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (menuToggle) {
                if (window.innerWidth <= 768) {
                    menuToggle.style.display = 'block';
                } else {
                    menuToggle.style.display = 'none';
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                }
            }
            
            if (sidebar) {
                sidebar.style.height = window.innerHeight + 'px';
            }
        });
    </script>
</body>
</html>