<?php
// admin/layanan/tambah.php
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_layanan = sanitize($_POST['nama_layanan']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $harga_input = sanitize($_POST['harga']);
    $durasi = sanitize($_POST['durasi']);
    $status = sanitize($_POST['status']);
    
    // Validasi input
    if (empty($nama_layanan) || empty($deskripsi) || empty($harga_input) || empty($durasi)) {
        $error = 'Semua field wajib diisi!';
    } elseif (!is_numeric($durasi) || $durasi <= 0) {
        $error = 'Durasi harus berupa angka positif!';
    } else {
        // Konversi harga ke format numerik
        $harga_clean = str_replace('.', '', $harga_input);
        $harga_clean = str_replace(',', '.', $harga_clean);
        $harga = floatval($harga_clean);
        
        if ($harga <= 0) {
            $error = 'Harga harus berupa angka positif!';
        } else {
            // Upload foto
            $foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                $file_type = mime_content_type($_FILES['foto']['tmp_name']);
                $file_size = $_FILES['foto']['size'];
                
                if (in_array($file_type, $allowed_types)) {
                    if ($file_size <= 2 * 1024 * 1024) { // Max 2MB
                        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '_' . time() . '.' . strtolower($ext);
                        $destination = UPLOAD_PATH . 'layanan/' . $filename;
                        
                        // Buat folder jika belum ada
                        if (!file_exists(UPLOAD_PATH . 'layanan/')) {
                            mkdir(UPLOAD_PATH . 'layanan/', 0777, true);
                        }
                        
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                            $foto = $filename;
                        } else {
                            $error = 'Gagal mengupload foto. Pastikan folder upload memiliki izin yang tepat.';
                        }
                    } else {
                        $error = 'Ukuran file terlalu besar. Maksimal 2MB.';
                    }
                } else {
                    $error = 'Format file tidak didukung. Hanya JPG, PNG, dan GIF.';
                }
            }
            
            if (!$error) {
                try {
                    $query = "INSERT INTO layanan (nama_layanan, deskripsi, harga, durasi, foto, status, created_at) 
                              VALUES (:nama_layanan, :deskripsi, :harga, :durasi, :foto, :status, NOW())";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nama_layanan', $nama_layanan);
                    $stmt->bindParam(':deskripsi', $deskripsi);
                    $stmt->bindParam(':harga', $harga);
                    $stmt->bindParam(':durasi', $durasi);
                    $stmt->bindParam(':foto', $foto);
                    $stmt->bindParam(':status', $status);
                    
                    if ($stmt->execute()) {
                        $success = 'Layanan berhasil ditambahkan!';
                        header("refresh:2;url=" . BASE_URL . "/admin/layanan/");
                    } else {
                        $error = 'Terjadi kesalahan saat menambahkan layanan.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Layanan - Admin</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary-pink);
            margin: 0;
        }
        
        .form-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-header {
            margin-bottom: 40px;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--pastel-pink);
        }
        
        .form-header h2 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .form-header p {
            color: var(--dark-gray);
            font-size: 1.1rem;
            opacity: 0.8;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background-color: #ffe6e6;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label .required {
            color: #d32f2f;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--pastel-pink);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--white);
            color: var(--text-color);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        .form-control.error {
            border-color: #d32f2f;
            background-color: #fff5f5;
        }
        
        .form-control.success {
            border-color: #2e7d32;
            background-color: #f5fff5;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
            line-height: 1.5;
        }
        
        .form-help {
            display: block;
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }
        
        .file-upload-container {
            margin-top: 10px;
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            display: block;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 40px 20px;
            background-color: var(--light-pink);
            border: 2px dashed var(--primary-pink);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            color: var(--dark-pink);
            transition: var(--transition);
            min-height: 180px;
        }
        
        .file-upload-label:hover {
            background-color: var(--pastel-pink);
            border-color: var(--dark-pink);
        }
        
        .file-upload-label i {
            font-size: 3rem;
            opacity: 0.7;
        }
        
        .file-upload-label span {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .image-preview {
            margin-top: 20px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 300px;
            max-height: 200px;
            border-radius: 12px;
            border: 3px solid var(--pastel-pink);
            box-shadow: var(--shadow);
            object-fit: cover;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 2px solid var(--pastel-pink);
        }
        
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            color: var(--dark-pink);
            padding: 14px 30px;
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
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--dark-pink);
            color: var(--white);
            padding: 14px 30px;
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
        
        .btn-primary i {
            margin-right: 5px;
        }
        
        .mobile-menu-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1000;
                background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.2rem;
                box-shadow: var(--shadow);
            }
            
            .main-content {
                padding-top: 60px;
            }
            
            .form-container {
                padding: 25px;
                margin: 0 15px;
            }
            
            .form-header h2 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .form-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .btn-secondary, .btn-primary {
                width: 100%;
                justify-content: center;
            }
            
            .file-upload-label {
                padding: 25px 15px;
                min-height: 150px;
            }
            
            .image-preview img {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
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
                <div class="brand-logo">KECAN<span>TIKAN</span></div>
                <small>Beauty & Spa Center</small>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-plus-circle"></i> Tambah Layanan Baru</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Admin')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-spa"></i> Formulir Tambah Layanan</h2>
                    <p>Isi semua informasi layanan di bawah ini dengan lengkap</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="tambahLayananForm" onsubmit="return validateForm()">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nama_layanan" class="form-label">
                                <i class="fas fa-spa"></i> Nama Layanan <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="nama_layanan" 
                                   name="nama_layanan" 
                                   class="form-control" 
                                   required 
                                   placeholder="Contoh: Facial Treatment, Hair Spa, Nail Art"
                                   value="<?php echo isset($_POST['nama_layanan']) ? htmlspecialchars($_POST['nama_layanan']) : ''; ?>"
                                   oninput="validateField(this)">
                            <small class="form-help">Masukkan nama layanan yang jelas dan menarik</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="harga" class="form-label">
                                <i class="fas fa-money-bill-wave"></i> Harga (Rp) <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="harga" 
                                   name="harga" 
                                   class="form-control" 
                                   required 
                                   placeholder="Contoh: 150.000, 150000.50, 1.250.000"
                                   value="<?php echo isset($_POST['harga']) ? htmlspecialchars($_POST['harga']) : ''; ?>"
                                   oninput="formatRupiahInput(this, true)">
                            <small class="form-help">Masukkan harga (contoh: 150.000, 150.000,50, 1.250.000)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="durasi" class="form-label">
                                <i class="far fa-clock"></i> Durasi (menit) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="durasi" 
                                   name="durasi" 
                                   class="form-control" 
                                   required 
                                   placeholder="Contoh: 60"
                                   min="1"
                                   max="480"
                                   value="<?php echo isset($_POST['durasi']) ? htmlspecialchars($_POST['durasi']) : ''; ?>">
                            <small class="form-help">Durasi layanan dalam menit (1-480 menit)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">
                                <i class="fas fa-toggle-on"></i> Status Layanan <span class="required">*</span>
                            </label>
                            <select id="status" 
                                    name="status" 
                                    class="form-control" 
                                    required>
                                <option value="aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                            <small class="form-help">Status aktif akan menampilkan layanan di halaman pelanggan</small>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="deskripsi" class="form-label">
                            <i class="fas fa-align-left"></i> Deskripsi Layanan <span class="required">*</span>
                        </label>
                        <textarea id="deskripsi" 
                                  name="deskripsi" 
                                  class="form-control" 
                                  required 
                                  placeholder="Deskripsikan layanan secara detail termasuk manfaat dan prosedur..."
                                  rows="5"
                                  oninput="validateField(this)"><?php echo isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : ''; ?></textarea>
                        <small class="form-help">Deskripsi yang jelas akan membantu pelanggan memahami layanan</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">
                            <i class="fas fa-camera"></i> Foto Layanan
                        </label>
                        <div class="file-upload-container">
                            <div class="file-upload">
                                <input type="file" 
                                       id="foto" 
                                       name="foto" 
                                       accept="image/*" 
                                       onchange="previewImage(this, 'imagePreview')">
                                <label for="foto" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Klik untuk memilih foto atau drag & drop di sini</span>
                                    <small style="opacity: 0.7;">Format: JPG, PNG, GIF (Maks. 2MB)</small>
                                </label>
                            </div>
                            <div class="image-preview">
                                <img id="imagePreview" src="" alt="Preview Foto" style="display: none;">
                            </div>
                        </div>
                        <small class="form-help">Foto yang menarik akan meningkatkan minat pelanggan</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?php echo BASE_URL; ?>/admin/layanan/" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Layanan
                        </a>
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Simpan Layanan
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    mobileMenuToggle.innerHTML = sidebar.classList.contains('active') 
                        ? '<i class="fas fa-times"></i>' 
                        : '<i class="fas fa-bars"></i>';
                });
                
                // Tutup sidebar saat klik di luar
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                            sidebar.classList.remove('active');
                            mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                        }
                    }
                });
                
                // Set sidebar height
                sidebar.style.height = window.innerHeight + 'px';
            }
            
            // Preview image on file select
            const fileInput = document.getElementById('foto');
            const fileUploadLabel = document.querySelector('.file-upload-label');
            
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const fileName = this.files[0] ? this.files[0].name : 'Belum ada file dipilih';
                    fileUploadLabel.innerHTML = `
                        <i class="fas fa-file-image"></i>
                        <span>${fileName}</span>
                        <small style="opacity: 0.7;">Klik untuk mengganti file</small>
                    `;
                });
            }
            
            // Real-time validation
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('blur', function() {
                    validateField(this);
                });
            });
            
            // Load saved form data if exists
            const savedData = localStorage.getItem('layananFormDraft');
            if (savedData) {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field) {
                        field.value = data[key];
                        validateField(field);
                        
                        // Format harga jika field harga
                        if (key === 'harga' && field.value) {
                            formatRupiahInput(field);
                        }
                    }
                });
                
                // Clear saved data after loading
                localStorage.removeItem('layananFormDraft');
            }
        });
        
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onloadend = function() {
                preview.src = reader.result;
                preview.style.display = 'block';
            }
            
            if (file) {
                reader.readAsDataURL(file);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        }
        
        function formatRupiahInput(input) {
            // Simpan posisi cursor
            const cursorPosition = input.selectionStart;
            
            // Dapatkan nilai input
            let value = input.value;
            
            // Hapus semua karakter kecuali angka, titik desimal, dan koma
            let cleanValue = value.replace(/[^0-9.,]/g, '');
            
            // Ganti koma dengan titik untuk format desimal internasional
            cleanValue = cleanValue.replace(/,/g, '.');
            
            // Pastikan hanya ada satu titik desimal
            const parts = cleanValue.split('.');
            if (parts.length > 2) {
                cleanValue = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Pisahkan bagian integer dan desimal
            const [integerPart, decimalPart] = cleanValue.includes('.') ? 
                cleanValue.split('.') : [cleanValue, ''];
            
            // Format bagian integer dengan pemisah ribuan
            let formattedInteger = '';
            if (integerPart) {
                // Hapus nol di depan
                let intNum = integerPart.replace(/^0+/, '') || '0';
                
                // Format dengan pemisah ribuan
                formattedInteger = parseInt(intNum).toLocaleString('id-ID');
            }
            
            // Gabungkan dengan bagian desimal jika ada
            let result = decimalPart ? 
                `${formattedInteger}.${decimalPart.substring(0, 2)}` : // Batasi 2 digit desimal
                formattedInteger;
            
            // Update nilai input
            input.value = result;
            
            // Kembalikan cursor ke posisi yang sesuai
            setTimeout(() => {
                const newCursorPos = cursorPosition + (result.length - value.length);
                input.setSelectionRange(newCursorPos, newCursorPos);
            }, 0);
            
            return result;
        }
        
        function validateField(field) {
            const value = field.value.trim();
            
            if (!value) {
                field.classList.add('error');
                field.classList.remove('success');
                return false;
            }
            
            // Additional validation based on field type
            switch(field.id) {
                case 'nama_layanan':
                    if (value.length < 3) {
                        field.classList.add('error');
                        field.classList.remove('success');
                        return false;
                    }
                    break;
                    
                case 'harga':
                    // Hilangkan pemisah ribuan untuk validasi
                    const cleanPrice = value.replace(/\./g, '').replace(',', '.');
                    const priceNum = parseFloat(cleanPrice);
                    
                    if (isNaN(priceNum) || priceNum <= 0) {
                        field.classList.add('error');
                        field.classList.remove('success');
                        return false;
                    }
                    break;
                    
                case 'durasi':
                    if (isNaN(value) || parseInt(value) < 1 || parseInt(value) > 480) {
                        field.classList.add('error');
                        field.classList.remove('success');
                        return false;
                    }
                    break;
                    
                case 'deskripsi':
                    if (value.length < 20) {
                        field.classList.add('error');
                        field.classList.remove('success');
                        return false;
                    }
                    break;
            }
            
            field.classList.remove('error');
            field.classList.add('success');
            return true;
        }
        
        // Fungsi untuk format ke Rupiah
        function formatRupiah(angka, prefix = 'Rp ') {
            if (!angka) return '';
            
            // Konversi ke string dan bersihkan
            let number_string = angka.toString().replace(/[^0-9.]/g, ''),
                split = number_string.split('.'),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            
            // Tambahkan titik jika yang angka ribuan
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            
            rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == '' ? rupiah : (rupiah ? prefix + rupiah : '');
        }
        
        // Fungsi untuk parse dari Rupiah ke angka
        function parseRupiah(rupiah) {
            if (!rupiah) return 0;
            return parseFloat(rupiah.replace(/[^0-9.,]/g, '').replace(/\./g, '').replace(',', '.'));
        }
        
        function validateForm() {
            const form = document.getElementById('tambahLayananForm');
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            // Validate all required fields
            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            // Validasi harga khusus
            const hargaField = document.getElementById('harga');
            if (hargaField.value) {
                const cleanPrice = hargaField.value.replace(/\./g, '').replace(',', '.');
                const priceNum = parseFloat(cleanPrice);
                
                if (isNaN(priceNum) || priceNum <= 0) {
                    hargaField.classList.add('error');
                    isValid = false;
                    alert('Harga harus berupa angka positif!');
                }
            }
            
            // Validate file size if uploaded
            const fileInput = document.getElementById('foto');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 2) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    fileInput.value = '';
                    isValid = false;
                }
            }
            
            if (!isValid) {
                // Scroll to first error
                const firstError = form.querySelector('.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
            
            return isValid;
        }
    </script>
</body>
</html>