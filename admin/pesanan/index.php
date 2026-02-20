<?php
// admin/pesanan/index.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];

if ($status_filter && $status_filter !== 'all') {
    $where_clause .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_filter) {
    $where_clause .= " AND DATE(p.tanggal_pesanan) = :date";
    $params[':date'] = $date_filter;
}

if ($search) {
    $where_clause .= " AND (p.kode_pesanan LIKE :search OR u.nama_lengkap LIKE :search OR l.nama_layanan LIKE :search)";
    $params[':search'] = "%{$search}%";
}

// Get total records
$query = "SELECT COUNT(*) as total 
          FROM pesanan p
          JOIN users u ON p.user_id = u.id
          JOIN layanan l ON p.layanan_id = l.id
          $where_clause";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get pesanan data
$query = "SELECT p.*, u.nama_lengkap, u.email, u.no_telepon, l.nama_layanan, l.foto as foto_layanan
          FROM pesanan p
          JOIN users u ON p.user_id = u.id
          JOIN layanan l ON p.layanan_id = l.id
          $where_clause
          ORDER BY p.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination
$total_pages = ceil($total_records / $per_page);

// Get status counts for stats
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as menunggu,
            SUM(CASE WHEN status = 'dikonfirmasi' THEN 1 ELSE 0 END) as dikonfirmasi,
            SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
          FROM pesanan";

$stmt = $conn->query($query);
$status_counts = $stmt->fetch(PDO::FETCH_ASSOC);

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
        default: return 'badge-secondary';
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Admin</title>
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
        
        .btn-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
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
        
        .filters-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 5px;
            color: var(--dark-pink);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .filter-select, .filter-input {
            padding: 10px;
            border: 2px solid var(--pastel-pink);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-filter {
            background-color: var(--primary-pink);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .btn-filter:hover {
            background-color: var(--dark-pink);
            transform: translateY(-2px);
        }
        
        .btn-reset {
            background-color: var(--white);
            color: var(--dark-pink);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 2px solid var(--dark-pink);
            transition: var(--transition);
        }
        
        .btn-reset:hover {
            background-color: var(--pastel-pink);
            transform: translateY(-2px);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        
        .stat-item.active {
            background: var(--pastel-pink);
            border-color: var(--dark-pink);
        }
        
        .stat-count {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-pink);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--dark-gray);
        }
        
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th {
            background-color: var(--pastel-pink);
            color: var(--dark-pink);
            text-align: left;
            padding: 15px;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        tr:hover {
            background-color: var(--light-pink);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .kode-pesanan-pendek {
            background-color: var(--dark-pink);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 5px;
            box-shadow: 0 3px 6px rgba(216, 27, 96, 0.2);
        }
        
        .layanan-image-small {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid var(--primary-pink);
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
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .btn-action {
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
            transition: var(--transition);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-view {
            background-color: var(--primary-pink);
            color: white;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-confirm {
            background-color: #28a745;
            color: white;
        }
        
        .btn-process {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-complete {
            background-color: #007bff;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark-pink);
            border: 1px solid var(--medium-gray);
            min-width: 40px;
            text-align: center;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--primary-pink);
            color: white;
            border-color: var(--primary-pink);
        }
        
        .pagination .active {
            background-color: var(--dark-pink);
            color: white;
            border-color: var(--dark-pink);
        }
        
        .pagination .disabled {
            color: var(--medium-gray);
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--pastel-pink);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--dark-gray);
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 992px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .table-container {
                padding: 20px;
                margin: 0 15px;
            }
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .filters-container,
            .table-container {
                margin-left: 0;
                margin-right: 0;
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
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($_SESSION['foto'] ?? 'default.png'); ?>" 
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
                    <li><a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="active"><i class="fas fa-shopping-cart"></i> Manajemen Pesanan</a></li>
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
                <h1><i class="fas fa-shopping-cart"></i> Kelola Pesanan</h1>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn-small">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            
            <!-- Status Stats -->
            <div class="stats-container">
                <div class="stat-item <?php echo !$status_filter ? 'active' : ''; ?>" 
                     onclick="window.location.href='?'">
                    <div class="stat-count"><?php echo $status_counts['total']; ?></div>
                    <div class="stat-label">Semua</div>
                </div>
                <div class="stat-item <?php echo $status_filter === 'menunggu' ? 'active' : ''; ?>" 
                     onclick="window.location.href='?status=menunggu'">
                    <div class="stat-count"><?php echo $status_counts['menunggu']; ?></div>
                    <div class="stat-label">Menunggu</div>
                </div>
                <div class="stat-item <?php echo $status_filter === 'dikonfirmasi' ? 'active' : ''; ?>" 
                     onclick="window.location.href='?status=dikonfirmasi'">
                    <div class="stat-count"><?php echo $status_counts['dikonfirmasi']; ?></div>
                    <div class="stat-label">Dikonfirmasi</div>
                </div>
                <div class="stat-item <?php echo $status_filter === 'diproses' ? 'active' : ''; ?>" 
                     onclick="window.location.href='?status=diproses'">
                    <div class="stat-count"><?php echo $status_counts['diproses']; ?></div>
                    <div class="stat-label">Diproses</div>
                </div>
                <div class="stat-item <?php echo $status_filter === 'selesai' ? 'active' : ''; ?>" 
                     onclick="window.location.href='?status=selesai'">
                    <div class="stat-count"><?php echo $status_counts['selesai']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-item <?php echo $status_filter === 'dibatalkan' ? 'active' : ''; ?>" 
                     onclick="window.location.href='?status=dibatalkan'">
                    <div class="stat-count"><?php echo $status_counts['dibatalkan']; ?></div>
                    <div class="stat-label">Dibatalkan</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-container">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status">Status Pesanan</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="all" <?php echo !$status_filter ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="menunggu" <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                <option value="dikonfirmasi" <?php echo $status_filter === 'dikonfirmasi' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="dibatalkan" <?php echo $status_filter === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Tanggal Pesanan</label>
                            <input type="date" id="date" name="date" class="filter-input" 
                                   value="<?php echo $date_filter; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Cari</label>
                            <input type="text" id="search" name="search" class="filter-input" 
                                   placeholder="Kode pesanan (cth: K1234), nama pelanggan, atau layanan..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="?" class="btn-reset">
                            <i class="fas fa-times"></i> Reset Filter
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Pesanan Table -->
            <div class="table-container">
                <?php if (count($pesanan) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Tanggal & Jam</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan as $order): 
                            $kode_pendek = formatKodePendek($order['kode_pesanan']);
                        ?>
                        <tr>
                            <td>
                                <div class="kode-pesanan-pendek"><?php echo $kode_pendek; ?></div>
                                <small><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></small>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($order['nama_lengkap']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($order['email']); ?></small>
                                    <br>
                                    <small><?php echo htmlspecialchars($order['no_telepon'] ?: '-'); ?></small>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . $order['foto_layanan']; ?>" 
                                         alt="<?php echo htmlspecialchars($order['nama_layanan']); ?>" 
                                         class="layanan-image-small"
                                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                                    <span><?php echo htmlspecialchars($order['nama_layanan']); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?>
                                <br>
                                <small><?php echo date('H:i', strtotime($order['jam_pesanan'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo formatRupiah($order['total_harga']); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    
                                    <?php if ($order['status'] === 'menunggu'): ?>
                                        <a href="update_status.php?id=<?php echo $order['id']; ?>&status=dikonfirmasi" 
                                           class="btn-action btn-confirm" title="Konfirmasi"
                                           onclick="return confirm('Konfirmasi pesanan ini?')">
                                            <i class="fas fa-check"></i> Konfirmasi
                                        </a>
                                        <a href="update_status.php?id=<?php echo $order['id']; ?>&status=dibatalkan" 
                                           class="btn-action btn-cancel" title="Batalkan"
                                           onclick="return confirm('Batalkan pesanan ini?')">
                                            <i class="fas fa-times"></i> Batalkan
                                        </a>
                                    
                                    <?php elseif ($order['status'] === 'dikonfirmasi'): ?>
                                        <a href="update_status.php?id=<?php echo $order['id']; ?>&status=diproses" 
                                           class="btn-action btn-process" title="Proses"
                                           onclick="return confirm('Mulai proses pesanan ini?')">
                                            <i class="fas fa-play"></i> Proses
                                        </a>
                                    
                                    <?php elseif ($order['status'] === 'diproses'): ?>
                                        <a href="update_status.php?id=<?php echo $order['id']; ?>&status=selesai" 
                                           class="btn-action btn-complete" title="Selesai"
                                           onclick="return confirm('Tandai pesanan sebagai selesai?')">
                                            <i class="fas fa-check-circle"></i> Selesai
                                        </a>
                                    
                                    <?php elseif ($order['status'] === 'selesai'): ?>
                                        <a href="print.php?id=<?php echo $order['id']; ?>&kode=<?php echo urlencode($kode_pendek); ?>" 
                                           class="btn-action btn-view" title="Cetak"
                                           target="_blank">
                                            <i class="fas fa-print"></i> Cetak
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                        <span class="disabled"><i class="fas fa-angle-left"></i></span>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<span>...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-angle-right"></i></span>
                        <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Tidak Ada Pesanan</h3>
                    <p>
                        <?php if ($status_filter || $date_filter || $search): ?>
                            Tidak ditemukan pesanan dengan filter yang dipilih.
                        <?php else: ?>
                            Belum ada pesanan yang dibuat.
                        <?php endif; ?>
                    </p>
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
            
            // Konfirmasi untuk aksi status
            const statusLinks = document.querySelectorAll('.btn-confirm, .btn-cancel, .btn-process, .btn-complete');
            statusLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const action = this.title.toLowerCase();
                    if (!confirm(`Apakah Anda yakin ingin ${action} pesanan ini?`)) {
                        e.preventDefault();
                    }
                });
            });
        });
        
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