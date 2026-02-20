<?php
// pelanggan/pesanan/index.php
require_once '../../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Status filter
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build WHERE clause
$where_clause = "WHERE p.user_id = :user_id";
$params = [':user_id' => $user_id];

if ($status_filter && $status_filter !== 'all') {
    $where_clause .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

// Get total records
$query = "SELECT COUNT(*) as total 
          FROM pesanan p
          JOIN layanan l ON p.layanan_id = l.id
          $where_clause";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get orders
$query = "SELECT p.*, l.nama_layanan, l.foto as foto_layanan
          FROM pesanan p
          JOIN layanan l ON p.layanan_id = l.id
          $where_clause
          ORDER BY p.created_at DESC
          LIMIT :limit OFFSET :offset";

$params[':limit'] = $per_page;
$params[':offset'] = $offset;

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    if ($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination
$total_pages = ceil($total_records / $per_page);

// Get status counts
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as menunggu,
            SUM(CASE WHEN status = 'dikonfirmasi' THEN 1 ELSE 0 END) as dikonfirmasi,
            SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
          FROM pesanan 
          WHERE user_id = :user_id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$status_counts = $stmt->fetch(PDO::FETCH_ASSOC);

// FUNGSI FORMAT KODE PESANAN PENDEK - 4 DIGIT SAJA (SAMA DENGAN DI DETAIL.PHP)
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Pelanggan</title>
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
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            padding: 20px 0;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--white);
            margin: 0 auto 15px;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .sidebar-header h3 {
            margin-top: 10px;
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: #ffffff
        }
        
        .sidebar-header small {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            padding: 20px 0;
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
            gap: 10px;
            padding: 12px 20px;
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--white);
        }
        
        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            padding: 20px;
            background-color: var(--light-pink);
            overflow-y: auto;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        .dashboard-header h1 {
            color: var(--dark-pink);
            font-size: 1.8rem;
        }
        
        .page-description {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-pink);
        }
        
        .page-description h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        
        .page-description p {
            color: var(--dark-gray);
            line-height: 1.8;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 16px;
            background: var(--white);
            transition: var(--transition);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--dark-pink);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }
        
        .btn-filter {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
            height: 46px;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        .btn-new-order {
            background: #28a745;
            color: var(--white);
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-new-order:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .orders-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .orders-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .order-card {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--medium-gray);
            transition: var(--transition);
        }
        
        .order-card:hover {
            border-color: var(--primary-pink);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .order-code {
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
        
        .order-date {
            font-size: 0.85rem;
            color: var(--dark-gray);
            display: block;
            margin-top: 5px;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .order-body {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .order-image {
            width: 90px;
            height: 90px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--primary-pink);
        }
        
        .order-details {
            flex: 1;
        }
        
        .order-service {
            font-weight: 600;
            color: var(--dark-pink);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .order-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }
        
        .order-meta div {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .order-price {
            font-weight: 700;
            color: var(--dark-pink);
            font-size: 1.2rem;
            margin-top: 5px;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--medium-gray);
        }
        
        .btn-action {
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-view {
            background: var(--primary-pink);
            color: var(--white);
        }
        
        .btn-view:hover {
            background: var(--dark-pink);
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #dc3545;
            color: var(--white);
        }
        
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-cancel:disabled {
            background: var(--medium-gray);
            color: #adb5bd;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-print {
            background: #17a2b8;
            color: var(--white);
        }
        
        .btn-print:hover {
            background: #138496;
            transform: translateY(-2px);
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
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--pastel-pink);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--dark-gray);
            max-width: 500px;
            margin: 0 auto 20px;
            line-height: 1.8;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid var(--medium-gray);
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark-pink);
            border: 1px solid var(--medium-gray);
            min-width: 45px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--primary-pink);
            color: var(--white);
            border-color: var(--primary-pink);
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background-color: var(--dark-pink);
            color: var(--white);
            border-color: var(--dark-pink);
        }
        
        .pagination .disabled {
            color: var(--medium-gray);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination .ellipsis {
            border: none;
            min-width: auto;
            padding: 10px 5px;
        }
        
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-new-order {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
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
            <div class="dashboard-header">
                <h1><i class="fas fa-shopping-cart"></i> Pesanan Saya</h1>
                <a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="btn-new-order">
                    <i class="fas fa-plus"></i> Pesan Layanan Baru
                </a>
            </div>
            
            <div class="page-description">
                <h3><i class="fas fa-info-circle"></i> Riwayat Pesanan</h3>
                <p>Lihat dan kelola semua pesanan Anda di sini. Anda dapat melihat detail, membatalkan pesanan yang masih menunggu, atau mencetak invoice.</p>
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
            
            <!-- Filter -->
            <div class="filter-container">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status"><i class="fas fa-filter"></i> Filter Status</label>
                            <select id="status" name="status" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo !$status_filter ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="menunggu" <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                <option value="dikonfirmasi" <?php echo $status_filter === 'dikonfirmasi' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="dibatalkan" <?php echo $status_filter === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Orders Grid -->
            <div class="orders-container">
                <?php if (count($pesanan) > 0): ?>
                <div class="orders-grid">
                    <?php foreach ($pesanan as $order): 
                        $kode_pendek = formatKodePendek($order['kode_pesanan']);
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <span class="order-code"><?php echo $kode_pendek; ?></span>
                                <div class="order-date"><?php echo formatDate($order['created_at'] ?? $order['tanggal_pesanan'], 'd/m/Y H:i'); ?></div>
                            </div>
                            <span class="badge <?php echo getStatusBadge($order['status']); ?> order-status">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        
                        <div class="order-body">
                            <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($order['foto_layanan'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($order['nama_layanan']); ?>" 
                                 class="order-image"
                                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                            <div class="order-details">
                                <div class="order-service"><?php echo htmlspecialchars($order['nama_layanan']); ?></div>
                                <div class="order-meta">
                                    <div>
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?>
                                    </div>
                                    <div>
                                        <i class="far fa-clock"></i>
                                        <?php echo date('H:i', strtotime($order['jam_pesanan'])); ?>
                                    </div>
                                </div>
                                <div class="order-price"><?php echo formatRupiah($order['total_harga']); ?></div>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            <a href="detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                            
                            <?php if ($order['status'] === 'menunggu'): ?>
                                <a href="batal.php?id=<?php echo $order['id']; ?>" class="btn-action btn-cancel"
                                   onclick="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                                    <i class="fas fa-times"></i> Batalkan
                                </a>
                            <?php else: ?>
                                <button class="btn-action btn-cancel" disabled>
                                    <i class="fas fa-times"></i> Batalkan
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'selesai'): ?>
                                <a href="print.php?id=<?php echo $order['id']; ?>&kode=<?php echo urlencode($kode_pendek); ?>" class="btn-action btn-print" target="_blank">
                                    <i class="fas fa-print"></i> Cetak
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>">
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
                        echo '<span class="ellipsis">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $class = ($i == $page) ? 'active' : '';
                        $url = "?page=$i" . ($status_filter ? "&status=" . urlencode($status_filter) : '');
                        echo "<a href=\"$url\" class=\"$class\">$i</a>";
                    }
                    
                    if ($end < $total_pages) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>">
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
                    <h3>
                        <?php echo $status_filter ? 'Tidak Ada Pesanan dengan Status Ini' : 'Belum Ada Pesanan'; ?>
                    </h3>
                    <p>
                        <?php if ($status_filter): ?>
                            Anda tidak memiliki pesanan dengan status "<?php echo ucfirst($status_filter); ?>".
                        <?php else: ?>
                            Anda belum membuat pesanan apapun. Ayo pesan layanan kecantikan pertama Anda!
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="btn-new-order" style="margin-top: 20px;">
                        <i class="fas fa-spa"></i> Lihat Layanan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 992) {
                const sidebar = document.querySelector('.sidebar');
                const header = document.querySelector('.dashboard-header');
                
                // Buat tombol menu
                const menuButton = document.createElement('button');
                menuButton.innerHTML = '<i class="fas fa-bars"></i>';
                menuButton.style.cssText = `
                    background: var(--dark-pink);
                    color: white;
                    border: none;
                    padding: 10px 15px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 1.2rem;
                    position: fixed;
                    top: 15px;
                    left: 15px;
                    z-index: 1000;
                    box-shadow: var(--shadow);
                `;
                
                document.body.appendChild(menuButton);
                
                // Tampilkan/sembunyikan sidebar
                menuButton.addEventListener('click', function() {
                    if (sidebar.style.display === 'block') {
                        sidebar.style.display = 'none';
                    } else {
                        sidebar.style.display = 'block';
                        sidebar.style.position = 'fixed';
                        sidebar.style.top = '0';
                        sidebar.style.left = '0';
                        sidebar.style.width = '250px';
                        sidebar.style.height = '100vh';
                        sidebar.style.zIndex = '999';
                        sidebar.style.boxShadow = '5px 0 15px rgba(0,0,0,0.2)';
                    }
                });
                
                // Tutup sidebar saat klik di luar
                document.addEventListener('click', function(e) {
                    if (!sidebar.contains(e.target) && !menuButton.contains(e.target)) {
                        sidebar.style.display = 'none';
                    }
                });
                
                // Adjust main content padding
                document.querySelector('.main-content').style.paddingTop = '70px';
            }
            
            // Auto refresh setiap 60 detik untuk update status
            setTimeout(function() {
                location.reload();
            }, 60000);
            
            // Konfirmasi sebelum batalkan pesanan
            const cancelButtons = document.querySelectorAll('.btn-cancel:not([disabled])');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                        e.preventDefault();
                    }
                });
            });
        });
        
        // Window resize handler
        window.addEventListener('resize', function() {
            const menuButton = document.querySelector('button[style*="position: fixed"]');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth > 992 && menuButton) {
                menuButton.remove();
                if (sidebar) {
                    sidebar.style.display = '';
                    sidebar.style.position = '';
                    sidebar.style.top = '';
                    sidebar.style.left = '';
                    sidebar.style.width = '';
                    sidebar.style.height = '';
                    sidebar.style.zIndex = '';
                    sidebar.style.boxShadow = '';
                }
                document.querySelector('.main-content').style.paddingTop = '';
            } else if (window.innerWidth <= 992 && !menuButton) {
                location.reload(); // Reload untuk membuat menu button
            }
        });
    </script>
</body>
</html>