<?php
// admin/layanan/index.php
require_once '../../config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . '/pelanggan/dashboard.php');
}

$conn = getDBConnection();

// Ambil profil admin
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data layanan
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM layanan WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nama_layanan LIKE :search OR deskripsi LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $query .= " AND status = :status";
    $params[':status'] = $status;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$layanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$total_aktif = 0;
$total_nonaktif = 0;
foreach ($layanan as $item) {
    if ($item['status'] == 'aktif') {
        $total_aktif++;
    } else {
        $total_nonaktif++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Layanan - Admin</title>
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
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
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
        
        .layanan-image {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
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
        
        .badge-aktif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-nonaktif {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #ffc107;
            color: #212529;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .btn-edit:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(255, 193, 7, 0.3);
        }
        
        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
        }
        
        .btn-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-status:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .status-aktif {
            background-color: #28a745;
            color: white;
        }
        
        .status-nonaktif {
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
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-group {
                min-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
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
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .layanan-image {
                width: 80px;
                height: 60px;
            }
            
            .btn-edit, .btn-delete, .btn-status {
                padding: 5px 10px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                gap: 10px;
            }
            
            .btn-filter, .btn-reset {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
        
        /* Additional Styles */
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
                    <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/layanan/" class="active"><i class="fas fa-spa"></i> Manajemen Layanan</a></li>
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
                <h1><i class="fas fa-spa"></i> Manajemen Layanan</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <!-- Statistik Layanan -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-spa"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Layanan</h3>
                        <div class="stat-number"><?php echo count($layanan); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Layanan Aktif</h3>
                        <div class="stat-number"><?php echo $total_aktif; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Layanan Nonaktif</h3>
                        <div class="stat-number"><?php echo $total_nonaktif; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Tambah Layanan</h3>
                        <div class="stat-number">
                            <a href="<?php echo BASE_URL; ?>/admin/layanan/tambah.php" class="btn-primary" style="font-size: 0.9rem; padding: 8px 15px;">
                                <i class="fas fa-plus"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="search"><i class="fas fa-search"></i> Cari Layanan</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               class="form-control" 
                               placeholder="Cari nama atau deskripsi layanan..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><i class="fas fa-filter"></i> Status Layanan</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?php echo ($status == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo ($status == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    
                    <a href="<?php echo BASE_URL; ?>/admin/layanan/" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </form>
            </div>
            
            <!-- Table Section -->
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: var(--dark-pink); font-size: 1.5rem; margin: 0;">
                        <i class="fas fa-list"></i> Daftar Layanan
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/admin/layanan/tambah.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Tambah Layanan Baru
                    </a>
                </div>
                
                <?php if (count($layanan) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama Layanan</th>
                            <th>Deskripsi</th>
                            <th>Harga</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($layanan as $item): ?>
                        <tr>
                            <td>
                                <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($item['foto'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['nama_layanan']); ?>" 
                                     class="layanan-image"
                                     onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                            </td>
                            <td>
                                <strong style="color: var(--dark-pink);"><?php echo htmlspecialchars($item['nama_layanan']); ?></strong>
                            </td>
                            <td style="max-width: 300px;">
                                <?php echo htmlspecialchars(substr($item['deskripsi'], 0, 100)) . (strlen($item['deskripsi']) > 100 ? '...' : ''); ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--dark-pink);">
                                    <?php echo formatRupiah($item['harga']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($item['durasi']); ?> menit</td>
                            <td>
                                <span class="badge badge-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo BASE_URL; ?>/admin/layanan/edit.php?id=<?php echo $item['id']; ?>" 
                                       class="btn-edit"
                                       title="Edit Layanan">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/admin/layanan/hapus.php?id=<?php echo $item['id']; ?>" 
                                       class="btn-delete"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus layanan ini?')"
                                       title="Hapus Layanan">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php if ($item['status'] == 'aktif'): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/layanan/toggle_status.php?id=<?php echo $item['id']; ?>&status=nonaktif" 
                                       class="btn-status status-nonaktif"
                                       onclick="return confirm('Nonaktifkan layanan ini?')"
                                       title="Nonaktifkan">
                                        <i class="fas fa-toggle-on"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/layanan/toggle_status.php?id=<?php echo $item['id']; ?>&status=aktif" 
                                       class="btn-status status-aktif"
                                       onclick="return confirm('Aktifkan layanan ini?')"
                                       title="Aktifkan">
                                        <i class="fas fa-toggle-off"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-spa"></i>
                    <h3>Tidak Ada Layanan</h3>
                    <p>Belum ada layanan yang ditambahkan. Mulai dengan menambahkan layanan baru.</p>
                    <a href="<?php echo BASE_URL; ?>/admin/layanan/tambah.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Tambah Layanan Pertama
                    </a>
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
            
            // Filter status highlight
            const statusBadges = document.querySelectorAll('.badge');
            statusBadges.forEach(badge => {
                badge.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.05)';
                });
                
                badge.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Table row hover effect
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.boxShadow = 'none';
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
            const importantLinks = document.querySelectorAll('.btn-primary, .btn-edit, .btn-delete, .btn-status, .sidebar-menu a');
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