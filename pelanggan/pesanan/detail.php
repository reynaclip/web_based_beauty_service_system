<?php
// pelanggan/pesanan/detail.php
require_once '../../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/pelanggan/pesanan/');
}

$user_id = $_SESSION['user_id'];

// Cek struktur tabel users untuk kolom telepon
$hasTeleponColumn = false;
try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'telepon'");
    $hasTeleponColumn = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $hasTeleponColumn = false;
}

// Build query berdasarkan kolom yang ada
$teleponField = $hasTeleponColumn ? 'u.telepon' : 'NULL as telepon';

$query = "SELECT p.*, l.nama_layanan, l.deskripsi, l.harga, l.durasi, l.foto as foto_layanan,
                 u.nama_lengkap, u.email, u.alamat, $teleponField
          FROM pesanan p
          JOIN layanan l ON p.layanan_id = l.id
          JOIN users u ON p.user_id = u.id
          WHERE p.id = :id AND p.user_id = :user_id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect(BASE_URL . '/pelanggan/pesanan/');
}

// Cek apakah kolom catatan_admin ada
$hasCatatanAdmin = false;
try {
    $stmt = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'catatan_admin'");
    $hasCatatanAdmin = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $hasCatatanAdmin = false;
}

// Cek apakah kolom no_telepon ada (untuk kompatibilitas)
$hasNoTeleponColumn = false;
try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'no_telepon'");
    $hasNoTeleponColumn = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $hasNoTeleponColumn = false;
}

// FUNGSI FORMAT KODE PESANAN PENDEK - 4 DIGIT SAJA
function formatKodePendek($kode) {
    if (empty($kode)) {
        // Generate kode acak 4 digit jika kosong
        return 'K' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Jika sudah format pendek 4 digit: K + 4 angka
    if (preg_match('/^K\d{4}$/', $kode)) {
        return $kode;
    }
    
    // Jika kode panjang: ambil 4 digit terakhir
    preg_match('/\d+$/', $kode, $matches);
    if (!empty($matches[0])) {
        $angka = intval($matches[0]);
        $padded = str_pad($angka % 10000, 4, '0', STR_PAD_LEFT);
        return 'K' . $padded;
    }
    
    // Default: generate dari hash string menjadi 4 digit
    $hash = abs(crc32($kode)) % 10000;
    return 'K' . str_pad($hash, 4, '0', STR_PAD_LEFT);
}

// Fungsi helper untuk badge status
function getStatusBadge($status) {
    switch($status) {
        case 'menunggu': return 'badge-menunggu';
        case 'dikonfirmasi': return 'badge-dikonfirmasi';
        case 'diproses': return 'badge-diproses';
        case 'selesai': return 'badge-selesai';
        case 'dibatalkan': return 'badge-dibatalkan';
        default: return '';
    }
}

// Fungsi helper untuk format tanggal
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '-';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

// Fungsi format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Format kode pesanan pendek
$kode_pesanan_pendek = formatKodePendek($order['kode_pesanan'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Pelanggan</title>
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
            --shadow-lg: 0 15px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --border-radius: 15px;
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
        
        /* Dashboard Layout */
        .dashboard {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            padding: 30px 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 25px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--white);
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .sidebar-header h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
            font-weight: 600;
            color: #ffffff
        }
        
        .sidebar-header small {
            opacity: 0.8;
            font-size: 0.9rem;
            display: block;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .sidebar-menu {
            padding: 0 15px;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
            border-radius: 10px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--white);
            font-weight: 600;
        }
        
        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            background-color: var(--light-pink);
            overflow-y: auto;
            min-height: 100vh;
        }
        
        .order-detail-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--pastel-pink);
        }
        
        .order-header h1 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .order-code {
            font-size: 1.1rem;
            color: var(--dark-gray);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .kode-pendek {
            background: var(--dark-pink);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            box-shadow: 0 4px 10px rgba(216, 27, 96, 0.3);
            margin-right: 8px;
        }
        
        .status-badge-large {
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Progress Bar */
        .progress-section {
            background: var(--light-pink);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 40px;
            border: 2px solid var(--pastel-pink);
        }
        
        .progress-bar {
            height: 10px;
            background: var(--medium-gray);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
            border-radius: 5px;
            transition: width 1s ease;
        }
        
        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .progress-label {
            text-align: center;
            flex: 1;
        }
        
        .progress-label.active {
            color: var(--dark-pink);
            font-weight: 600;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .info-section {
            background: var(--light-pink);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .info-section:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .info-section h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-pink);
            font-size: 1.3rem;
        }
        
        .service-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 3px solid var(--primary-pink);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            flex: 0 0 150px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .info-value {
            flex: 1;
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        /* Timeline */
        .timeline-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--dark-pink);
        }
        
        .timeline-section h3 {
            color: var(--dark-pink);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
        }
        
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 10px;
            bottom: 10px;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-pink) 0%, var(--dark-pink) 100%);
            border-radius: 3px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--white);
            border: 3px solid var(--medium-gray);
            z-index: 2;
        }
        
        .timeline-item.active::before {
            border-color: var(--dark-pink);
            background: var(--dark-pink);
            box-shadow: 0 0 0 4px var(--pastel-pink);
        }
        
        .timeline-item.completed::before {
            border-color: #28a745;
            background: #28a745;
        }
        
        .timeline-content {
            background: var(--light-pink);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--pastel-pink);
        }
        
        .timeline-title {
            font-weight: 700;
            color: var(--dark-pink);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .timeline-date {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .timeline-desc {
            font-size: 0.95rem;
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        /* Invoice Section */
        .invoice-section {
            background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            border: 3px solid var(--primary-pink);
        }
        
        .invoice-section h4 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
        }
        
        .invoice-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(216, 27, 96, 0.2);
        }
        
        .invoice-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--primary-pink);
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--dark-pink);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--medium-gray);
        }
        
        .btn-back {
            background: var(--medium-gray);
            color: var(--dark-gray);
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .btn-back:hover {
            background: var(--dark-gray);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: #28a745;
            color: var(--white);
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-print:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-cancel {
            background: #dc3545;
            color: var(--white);
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        /* Badge Styles */
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
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.2);
            }
            
            .sidebar.active {
                display: block;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .order-detail-container {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-back, .btn-print, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
            
            .order-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .progress-labels {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label {
                flex: none;
            }
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--dark-pink);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }
        
        @media (max-width: 1200px) {
            .mobile-menu-btn {
                display: flex;
            }
        }
        
        .mobile-menu-btn:hover {
            background: var(--primary-pink);
            transform: scale(1.1);
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        @media (max-width: 1200px) {
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($_SESSION['foto'] ?? 'default.jpg'); ?>" 
                         alt="User Avatar"
                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Pelanggan'); ?></h3>
                <small>Pelanggan</small>
            </div>
            
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php"><i class="fas fa-spa"></i> Layanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/" class="active"><i class="fas fa-shopping-cart"></i> Pesanan Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/profil.php"><i class="fas fa-user"></i> Profil Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Kunjungi Situs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="order-detail-container">
                <div class="order-header">
                    <div>
                        <h1><i class="fas fa-file-invoice"></i> Detail Pesanan</h1>
                        <div class="order-code">
                            <i class="fas fa-hashtag"></i>
                            Kode Pesanan: 
                            <span class="kode-pendek"><?php echo $kode_pesanan_pendek; ?></span>
                        </div>
                    </div>
                    <span class="badge <?php echo getStatusBadge($order['status']); ?> status-badge-large">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
                
                <!-- Progress Bar -->
                <div class="progress-section">
                    <div class="progress-bar">
                        <?php
                        $progress_width = 0;
                        switch($order['status']) {
                            case 'menunggu': $progress_width = 20; break;
                            case 'dikonfirmasi': $progress_width = 40; break;
                            case 'diproses': $progress_width = 60; break;
                            case 'selesai': $progress_width = 100; break;
                            case 'dibatalkan': $progress_width = 100; break;
                            default: $progress_width = 20;
                        }
                        ?>
                        <div class="progress-fill" style="width: <?php echo $progress_width; ?>%"></div>
                    </div>
                    <div class="progress-labels">
                        <div class="progress-label <?php echo $order['status'] == 'menunggu' ? 'active' : ''; ?>">Menunggu</div>
                        <div class="progress-label <?php echo $order['status'] == 'dikonfirmasi' ? 'active' : ''; ?>">Dikonfirmasi</div>
                        <div class="progress-label <?php echo $order['status'] == 'diproses' ? 'active' : ''; ?>">Diproses</div>
                        <div class="progress-label <?php echo $order['status'] == 'selesai' ? 'active' : ''; ?>">Selesai</div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <!-- Service Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-spa"></i> Informasi Layanan</h3>
                        <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($order['foto_layanan'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($order['nama_layanan'] ?? 'Layanan'); ?>" 
                             class="service-image"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                        <div class="info-row">
                            <div class="info-label">Layanan</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['nama_layanan'] ?? '-'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Deskripsi</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['deskripsi'] ?? '-'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Durasi</div>
                            <div class="info-value"><?php echo ($order['durasi'] ?? '0') . ' menit'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Harga Satuan</div>
                            <div class="info-value"><?php echo formatRupiah($order['harga'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <!-- Order Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-calendar-alt"></i> Informasi Pesanan</h3>
                        <div class="info-row">
                            <div class="info-label">Tanggal Pesan</div>
                            <div class="info-value"><?php echo formatDate($order['created_at'] ?? date('Y-m-d H:i:s'), 'd/m/Y H:i'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Tanggal Layanan</div>
                            <div class="info-value"><?php echo formatDate($order['tanggal_pesanan'] ?? $order['created_at'], 'd/m/Y'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Jam Layanan</div>
                            <div class="info-value"><?php echo date('H:i', strtotime($order['jam_pesanan'] ?? '09:00:00')); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Catatan</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['catatan'] ?? '-')); ?></div>
                        </div>
                        <?php if ($hasCatatanAdmin): ?>
                        <div class="info-row">
                            <div class="info-label">Catatan Admin</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['catatan_admin'] ?? '-')); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Customer Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-user"></i> Informasi Anda</h3>
                        <div class="info-row">
                            <div class="info-label">Nama</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['nama_lengkap'] ?? '-'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['email'] ?? '-'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Telepon</div>
                            <div class="info-value">
                                <?php 
                                $telepon = '-';
                                if ($hasTeleponColumn && isset($order['telepon']) && !empty($order['telepon'])) {
                                    $telepon = htmlspecialchars($order['telepon']);
                                } elseif ($hasNoTeleponColumn) {
                                    // Jika ada kolom no_telepon, ambil dari database lagi
                                    try {
                                        $stmt = $conn->prepare("SELECT no_telepon FROM users WHERE id = :id");
                                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $userTel = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($userTel && !empty($userTel['no_telepon'])) {
                                            $telepon = htmlspecialchars($userTel['no_telepon']);
                                        }
                                    } catch (PDOException $e) {
                                        $telepon = '-';
                                    }
                                }
                                echo $telepon;
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Alamat</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['alamat'] ?? '-')); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="timeline-section">
                    <h3><i class="fas fa-history"></i> Status Pesanan</h3>
                    <div class="timeline">
                        <?php
                        $statuses = [
                            'menunggu' => [
                                'title' => 'Menunggu Konfirmasi',
                                'desc' => 'Pesanan Anda sedang menunggu konfirmasi dari admin salon.',
                                'date' => $order['created_at'] ?? date('Y-m-d H:i:s')
                            ],
                            'dikonfirmasi' => [
                                'title' => 'Pesanan Dikonfirmasi',
                                'desc' => 'Pesanan Anda telah dikonfirmasi oleh admin. Tim kami akan mempersiapkan layanan Anda.',
                                'date' => $order['updated_at'] ?? $order['created_at']
                            ],
                            'diproses' => [
                                'title' => 'Sedang Diproses',
                                'desc' => 'Layanan Anda sedang diproses oleh tim kecantikan profesional kami.',
                                'date' => $order['updated_at'] ?? $order['created_at']
                            ],
                            'selesai' => [
                                'title' => 'Pesanan Selesai',
                                'desc' => 'Layanan telah selesai dilakukan. Terima kasih telah menggunakan jasa kami!',
                                'date' => $order['updated_at'] ?? $order['created_at']
                            ],
                            'dibatalkan' => [
                                'title' => 'Pesanan Dibatalkan',
                                'desc' => 'Pesanan telah dibatalkan. Hubungi admin untuk informasi lebih lanjut.',
                                'date' => $order['updated_at'] ?? $order['created_at']
                            ]
                        ];
                        
                        $current_status = $order['status'] ?? 'menunggu';
                        $status_keys = array_keys($statuses);
                        $current_index = array_search($current_status, $status_keys);
                        
                        foreach ($statuses as $key => $status):
                            $is_completed = array_search($key, $status_keys) < $current_index;
                            $is_active = $key === $current_status;
                            $class = '';
                            if ($is_active) {
                                $class = 'active';
                            } elseif ($is_completed) {
                                $class = 'completed';
                            }
                        ?>
                        <div class="timeline-item <?php echo $class; ?>">
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo $status['title']; ?></div>
                                <div class="timeline-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php 
                                    if ($is_completed || $is_active) {
                                        echo formatDate($status['date'], 'd/m/Y H:i');
                                    } else {
                                        echo 'Belum dicapai';
                                    }
                                    ?>
                                </div>
                                <div class="timeline-desc"><?php echo $status['desc']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Invoice -->
                <div class="invoice-section">
                    <h4><i class="fas fa-file-invoice-dollar"></i> Ringkasan Pembayaran</h4>
                    <div class="invoice-row">
                        <span>Harga Layanan:</span>
                        <span><?php echo formatRupiah($order['harga'] ?? 0); ?></span>
                    </div>
                    <div class="invoice-row">
                        <span>Biaya Lainnya:</span>
                        <span>Rp 0</span>
                    </div>
                    <div class="invoice-total">
                        <span>Total Pembayaran:</span>
                        <span><?php echo formatRupiah($order['total_harga'] ?? 0); ?></span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    
                    <?php if (($order['status'] ?? '') === 'menunggu'): ?>
                        <a href="batal.php?id=<?php echo $order['id']; ?>" class="btn-cancel"
                           onclick="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                            <i class="fas fa-times"></i> Batalkan Pesanan
                        </a>
                    <?php endif; ?>
                    
                    <?php if (($order['status'] ?? '') === 'selesai'): ?>
                        <a href="print.php?id=<?php echo $order['id']; ?>&kode=<?php echo urlencode($kode_pesanan_pendek); ?>" class="btn-print" target="_blank">
                            <i class="fas fa-print"></i> Cetak Invoice
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }
        
        mobileMenuBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);
        
        // Auto refresh halaman setiap 60 detik untuk update status
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>