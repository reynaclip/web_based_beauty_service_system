<?php
// admin/pelanggan/edit.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Get user ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/admin/pelanggan/');
}

// Get user data
$query = "SELECT * FROM users WHERE id = :id AND role = 'pelanggan'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect(BASE_URL . '/admin/pelanggan/');
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $email = sanitize($_POST['email']);
    $no_telepon = sanitize($_POST['no_telepon']);
    $alamat = sanitize($_POST['alamat']);
    $status = sanitize($_POST['status']);
    
    // Check if email already exists (excluding current user)
    $query = "SELECT id FROM users WHERE email = :email AND id != :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $error = 'Email sudah digunakan oleh pelanggan lain!';
    } else {
        // Handle photo upload
        $foto = $user['foto'];
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadImage($_FILES['foto'], 'users');
            
            if ($upload_result['success']) {
                // Delete old photo if not default
                if ($foto && $foto !== 'default.png' && file_exists(UPLOAD_PATH . 'users/' . $foto)) {
                    unlink(UPLOAD_PATH . 'users/' . $foto);
                }
                $foto = $upload_result['filename'];
            } else {
                $error = $upload_result['error'];
            }
        }
        
        // Delete photo if checked
        if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1') {
            if ($foto && $foto !== 'default.png' && file_exists(UPLOAD_PATH . 'users/' . $foto)) {
                unlink(UPLOAD_PATH . 'users/' . $foto);
            }
            $foto = 'default.png';
        }
        
        if (!$error) {
            // Update user data
            $query = "UPDATE users SET 
                      nama_lengkap = :nama_lengkap,
                      email = :email,
                      no_telepon = :no_telepon,
                      alamat = :alamat,
                      foto = :foto,
                      status = :status
                      WHERE id = :id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':nama_lengkap', $nama_lengkap);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':no_telepon', $no_telepon);
            $stmt->bindParam(':alamat', $alamat);
            $stmt->bindParam(':foto', $foto);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = 'Data pelanggan berhasil diperbarui!';
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Terjadi kesalahan saat memperbarui data.';
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
    <title>Edit Pelanggan - Admin</title>
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
        
        /* =========== FORM STYLES =========== */
        .form-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        .form-header h2 {
            color: var(--dark-pink);
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .form-header p {
            color: var(--dark-gray);
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--pastel-pink);
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background-color: var(--white);
            color: var(--text-color);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }
        
        select.form-control {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23d81b60' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px 12px;
            padding-right: 40px;
        }
        
        /* =========== IMAGE PREVIEW =========== */
        .image-preview-container {
            text-align: center;
            margin: 20px 0 30px;
            padding: 20px;
            background-color: var(--light-pink);
            border-radius: 10px;
            border: 2px dashed var(--pastel-pink);
        }
        
        .current-image {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-pink);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 15px 0 20px;
            padding: 12px 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid var(--medium-gray);
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--dark-pink);
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            color: var(--dark-gray);
            font-weight: normal;
            cursor: pointer;
            flex: 1;
        }
        
        .checkbox-group:hover {
            background-color: var(--light-pink);
            border-color: var(--pastel-pink);
        }
        
        /* =========== MESSAGES =========== */
        .error-message {
            background-color: #ffe6e6;
            color: #c62828;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #c62828;
            font-size: 0.95rem;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #2e7d32;
            font-size: 0.95rem;
        }
        
        /* =========== FORM ACTIONS =========== */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--medium-gray);
        }
        
        .btn-back {
            background-color: var(--white);
            color: var(--dark-pink);
            padding: 14px 28px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid var(--dark-pink);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .btn-back:hover {
            background-color: var(--pastel-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: white;
            padding: 14px 28px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        /* =========== FILE INPUT =========== */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .custom-file-upload {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background-color: var(--light-pink);
            border: 2px dashed var(--primary-pink);
            border-radius: 10px;
            color: var(--dark-pink);
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .custom-file-upload:hover {
            background-color: var(--pastel-pink);
        }
        
        .custom-file-upload i {
            font-size: 1.2rem;
        }
        
        .file-name {
            display: block;
            margin-top: 10px;
            color: var(--dark-gray);
            font-size: 0.9rem;
            text-align: center;
        }
        
        small.text-muted {
            display: block;
            margin-top: 8px;
            color: #6c757d;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* =========== MOBILE MENU TOGGLE =========== */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 992px) {
            .form-container {
                padding: 30px 25px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .btn-back, .btn-primary {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 25px 20px;
            }
            
            .form-header h2 {
                font-size: 1.5rem;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .image-preview-container {
                padding: 15px;
            }
            
            .current-image {
                width: 140px;
                height: 140px;
            }
        }
        
        @media (max-width: 576px) {
            .form-container {
                padding: 20px 15px;
            }
            
            .form-header h2 {
                font-size: 1.3rem;
                flex-direction: column;
                gap: 5px;
            }
            
            .form-control {
                padding: 12px 15px;
            }
            
            .checkbox-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .checkbox-group label {
                flex: none;
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
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($_SESSION['foto'] ?: 'default.png'); ?>" 
                         alt="Admin Avatar"
                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['nama']); ?></h3>
                <small>Administrator</small>
            </div>
            
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/layanan/"><i class="fas fa-spa"></i> Manajemen Layanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="active"><i class="fas fa-user-friends"></i> Data Pelanggan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/pesanan/"><i class="fas fa-shopping-cart"></i> Manajemen Pesanan</a></li>
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
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-user-edit"></i> Edit Data Pelanggan</h2>
                    <p>Perbarui informasi pelanggan</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="editPelangganForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required 
                               value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" class="form-control" required 
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="no_telepon"><i class="fas fa-phone"></i> Nomor Telepon</label>
                        <input type="tel" id="no_telepon" name="no_telepon" class="form-control" 
                               value="<?php echo htmlspecialchars($user['no_telepon'] ?: ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="alamat"><i class="fas fa-home"></i> Alamat</label>
                        <textarea id="alamat" name="alamat" class="form-control" rows="4"><?php echo htmlspecialchars($user['alamat'] ?: ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status Akun</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="aktif" <?php echo $user['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo $user['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Foto Profil</label>
                        
                        <div class="image-preview-container">
                            <p>Foto saat ini:</p>
                            <img src="<?php echo BASE_URL . '/assets/uploads/users/' . $user['foto']; ?>" 
                                 alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" 
                                 class="current-image"
                                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                            <p class="file-name"><?php echo htmlspecialchars($user['foto']); ?></p>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="hapus_foto" name="hapus_foto" value="1">
                            <label for="hapus_foto">Hapus foto saat ini dan gunakan default</label>
                        </div>
                        
                        <p style="margin: 15px 0 10px; color: var(--dark-pink); font-weight: 500;">Atau upload foto baru:</p>
                        
                        <div class="file-input-wrapper">
                            <div class="custom-file-upload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Pilih Foto Baru</span>
                            </div>
                            <input type="file" id="foto" name="foto" accept="image/*" class="form-control">
                        </div>
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP (Maks. 2MB). Biarkan kosong jika tidak ingin mengganti.</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?php echo BASE_URL; ?>/admin/pelanggan/" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
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
            
            // Preview image before upload
            const fotoInput = document.getElementById('foto');
            if (fotoInput) {
                fotoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const currentImage = document.querySelector('.current-image');
                    const fileName = document.querySelector('.file-name');
                    
                    if (file) {
                        // Update file name
                        fileName.textContent = file.name;
                        
                        // Preview image
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            currentImage.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                        
                        // Uncheck delete photo checkbox
                        const hapusFotoCheckbox = document.getElementById('hapus_foto');
                        if (hapusFotoCheckbox) {
                            hapusFotoCheckbox.checked = false;
                        }
                    }
                });
            }
            
            // Toggle upload field based on delete checkbox
            const hapusFotoCheckbox = document.getElementById('hapus_foto');
            if (hapusFotoCheckbox) {
                hapusFotoCheckbox.addEventListener('change', function() {
                    const fotoInput = document.getElementById('foto');
                    const currentImage = document.querySelector('.current-image');
                    const fileName = document.querySelector('.file-name');
                    
                    if (this.checked) {
                        // Disable file input and clear it
                        if (fotoInput) {
                            fotoInput.disabled = true;
                            fotoInput.value = '';
                        }
                        
                        // Show default image
                        if (currentImage) {
                            currentImage.src = '<?php echo BASE_URL; ?>/assets/images/default-avatar.png';
                        }
                        
                        // Update file name
                        if (fileName) {
                            fileName.textContent = 'default.png';
                        }
                    } else {
                        // Enable file input
                        if (fotoInput) {
                            fotoInput.disabled = false;
                        }
                        
                        // Restore original image
                        if (currentImage) {
                            currentImage.src = '<?php echo BASE_URL . '/assets/uploads/users/' . $user['foto']; ?>';
                        }
                        
                        // Restore file name
                        if (fileName) {
                            fileName.textContent = '<?php echo htmlspecialchars($user['foto']); ?>';
                        }
                    }
                });
            }
            
            // Form validation
            const form = document.getElementById('editPelangganForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Validate email format
                    const email = document.getElementById('email').value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Format email tidak valid!');
                        document.getElementById('email').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
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