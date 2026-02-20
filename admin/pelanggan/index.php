<?php
// admin/pelanggan/index.php
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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_at_desc';

$where_clause = "WHERE role = 'pelanggan'";
$bind_params = [];

if ($search) {
    $where_clause .= " AND (nama_lengkap LIKE :search OR email LIKE :search OR no_telepon LIKE :search)";
    $bind_params[':search'] = "%{$search}%";
}

if ($status_filter && $status_filter != 'all') {
    $where_clause .= " AND status = :status";
    $bind_params[':status'] = $status_filter;
}

// Sort functionality
$order_by = "ORDER BY ";
switch ($sort) {
    case 'nama_asc':
        $order_by .= "nama_lengkap ASC";
        break;
    case 'nama_desc':
        $order_by .= "nama_lengkap DESC";
        break;
    case 'date_asc':
        $order_by .= "created_at ASC";
        break;
    case 'date_desc':
    default:
        $order_by .= "created_at DESC";
        break;
}

// Get total records
$query_total = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt_total = $conn->prepare($query_total);
foreach ($bind_params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_records = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

// Cek apakah kolom email_verified_at ada di tabel
$columns = "id, nama_lengkap, email, no_telepon, foto, status, created_at";
try {
    $check_stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'");
    if ($check_stmt->rowCount() > 0) {
        $columns .= ", email_verified_at";
    }
} catch (Exception $e) {
    // Kolom tidak ada, lanjutkan tanpa itu
}

// Get pelanggan data with pagination
$query_data = "SELECT $columns FROM users $where_clause $order_by LIMIT :limit OFFSET :offset";
$stmt_data = $conn->prepare($query_data);

// Bind search parameters
foreach ($bind_params as $key => $value) {
    $stmt_data->bindValue($key, $value);
}

// Bind pagination parameters
$stmt_data->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_data->execute();
$pelanggan = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
    FROM users WHERE role = 'pelanggan'";
$stats_stmt = $conn->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate pagination
$total_pages = ceil($total_records / $per_page);

// Fungsi helper untuk badge status
function getStatusBadge($status) {
    switch($status) {
        case 'aktif': return 'badge-aktif';
        case 'nonaktif': return 'badge-nonaktif';
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
    <title>Data Pelanggan - Admin</title>
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
        
        .action-buttons {
            display: flex;
            gap: 15px;
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
        
        .filter-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark-pink);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--pastel-pink);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .btn-filter {
            padding: 12px 25px;
            background: var(--dark-pink);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            height: fit-content;
        }
        
        .btn-filter:hover {
            background: var(--primary-pink);
            transform: translateY(-2px);
        }
        
        .btn-reset {
            padding: 12px 25px;
            background: var(--white);
            color: var(--dark-pink);
            border: 2px solid var(--dark-pink);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            height: fit-content;
        }
        
        .btn-reset:hover {
            background: var(--pastel-pink);
            transform: translateY(-2px);
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
            font-size: 0.95rem;
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
        
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
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
            white-space: nowrap;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--medium-gray);
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: var(--light-pink);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .user-avatar-small {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
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
        
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
            min-width: 36px;
            height: 36px;
        }
        
        .btn-view {
            background-color: var(--primary-pink);
            color: white;
        }
        
        .btn-view:hover {
            background-color: var(--dark-pink);
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-edit:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .status-aktif {
            background-color: #28a745;
            color: white;
        }
        
        .status-nonaktif {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-status:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--pastel-pink);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.3rem;
        }
        
        .empty-state p {
            color: var(--dark-gray);
            margin-bottom: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .page-item {
            display: inline-block;
        }
        
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 2px solid var(--pastel-pink);
            border-radius: 8px;
            background: var(--white);
            color: var(--dark-pink);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .page-link:hover {
            background: var(--primary-pink);
            color: white;
            border-color: var(--primary-pink);
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background: var(--dark-pink);
            color: white;
            border-color: var(--dark-pink);
        }
        
        .page-item.disabled .page-link {
            background: var(--medium-gray);
            color: var(--dark-gray);
            border-color: var(--medium-gray);
            cursor: not-allowed;
            transform: none;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-header h2 {
            color: var(--dark-pink);
            font-size: 1.5rem;
            margin: 0;
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
            
            .user-info {
                align-self: flex-end;
            }
            
            .filter-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .user-avatar-small {
                width: 35px;
                height: 35px;
            }
            
            .pagination {
                gap: 5px;
            }
            
            .page-link {
                min-width: 35px;
                height: 35px;
                padding: 0 8px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: space-between;
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
                <h1><i class="fas fa-user-friends"></i> Data Pelanggan</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <!-- Statistik Pelanggan -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Pelanggan</h3>
                        <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pelanggan Aktif</h3>
                        <div class="stat-number"><?php echo number_format($stats['aktif'] ?? 0); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pelanggan Nonaktif</h3>
                        <div class="stat-number"><?php echo number_format($stats['nonaktif'] ?? 0); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Ekspor Data</h3>
                        <div class="stat-number">
                            <a href="#" class="btn-secondary" style="font-size: 0.9rem; padding: 8px 15px;" onclick="alert('Fitur ekspor akan segera tersedia')">
                                <i class="fas fa-file-export"></i> Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="search"><i class="fas fa-search"></i> Cari Pelanggan</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               class="form-control" 
                               placeholder="Cari nama, email, atau telepon..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><i class="fas fa-filter"></i> Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all">Semua Status</option>
                            <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo ($status_filter == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sort"><i class="fas fa-sort"></i> Urutkan</label>
                        <select id="sort" name="sort" class="form-control">
                            <option value="nama_asc" <?php echo ($sort == 'nama_asc') ? 'selected' : ''; ?>>Nama A-Z</option>
                            <option value="nama_desc" <?php echo ($sort == 'nama_desc') ? 'selected' : ''; ?>>Nama Z-A</option>
                            <option value="date_desc" <?php echo ($sort == 'date_desc') ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="date_asc" <?php echo ($sort == 'date_asc') ? 'selected' : ''; ?>>Terlama</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </form>
            </div>
            
            <!-- Tabel Pelanggan -->
            <div class="table-container">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Daftar Pelanggan</h2>
                    <div class="action-buttons">
        
                        <button class="btn-primary" onclick="printTable()">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                    </div>
                </div>
                
                <?php if (count($pelanggan) > 0): ?>
                <table id="pelangganTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Foto</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Status</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pelanggan as $index => $user): ?>
                        <tr>
                            <td><?php echo $offset + $index + 1; ?></td>
                            <td>
                                <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($user['foto'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>"
                                     class="user-avatar-small"
                                     onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['nama_lengkap']); ?></strong>
                                <?php if (isset($user['email_verified_at']) && $user['email_verified_at']): ?>
                                    <br><small style="color: green;"><i class="fas fa-check-circle"></i> Email terverifikasi</small>
                                <?php else: ?>
                                    <br><small style="color: orange;"><i class="fas fa-exclamation-circle"></i> Email belum diverifikasi</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $user['no_telepon'] ? htmlspecialchars($user['no_telepon']) : '-'; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadge($user['status']); ?>">
                                    <?php echo ucfirst($user['status'] ?? 'aktif'); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="detail.php?id=<?php echo $user['id']; ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($user['status'] == 'aktif'): ?>
                                        <a href="toggle_status.php?id=<?php echo $user['id']; ?>&status=nonaktif" 
                                           class="btn-action btn-delete" 
                                           title="Nonaktifkan"
                                           onclick="return confirm('Yakin ingin menonaktifkan pelanggan ini?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle_status.php?id=<?php echo $user['id']; ?>&status=aktif" 
                                           class="btn-action btn-view" 
                                           title="Aktifkan"
                                           onclick="return confirm('Yakin ingin mengaktifkan pelanggan ini?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>" 
                           class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends"></i>
                    <h3>Tidak Ada Data Pelanggan</h3>
                    <p><?php echo $search ? 'Tidak ditemukan pelanggan dengan kata kunci "' . htmlspecialchars($search) . '"' : 'Belum ada pelanggan yang terdaftar.'; ?></p>
                    <?php if ($search): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="btn-primary">
                            <i class="fas fa-redo"></i> Tampilkan Semua
                        </a>
                    <?php endif; ?>
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
            
            // Confirm before deleting
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Yakin ingin menghapus pelanggan ini?')) {
                        e.preventDefault();
                    }
                });
            });
        });
        
        // Print function
        function printTable() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Data Pelanggan - Kecantikan</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #d81b60; text-align: center; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #ffd6e7; color: #d81b60; padding: 10px; border: 1px solid #ddd; }
                        td { padding: 10px; border: 1px solid #ddd; }
                        .badge { padding: 4px 8px; border-radius: 10px; font-size: 0.8rem; }
                        .aktif { background-color: #d4edda; color: #155724; }
                        .nonaktif { background-color: #f8d7da; color: #721c24; }
                        .print-info { margin-bottom: 20px; text-align: center; color: #666; }
                        @media print {
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Data Pelanggan</h1>
                    <div class="print-info">
                        Dicetak pada: ${new Date().toLocaleString('id-ID')}<br>
                        Total Pelanggan: ${<?php echo $total_records; ?>}
                    </div>
                    ${document.getElementById('pelangganTable').outerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }
        
        // Search on Enter key
        document.getElementById('search')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
        
        // Auto submit filter on change
        document.getElementById('status')?.addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('sort')?.addEventListener('change', function() {
            this.form.submit();
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
        
        // Initialize menu toggle visibility
        if (window.innerWidth <= 768) {
            document.getElementById('menuToggle').style.display = 'block';
        } else {
            document.getElementById('menuToggle').style.display = 'none';
        }
    </script>
</body>
</html>