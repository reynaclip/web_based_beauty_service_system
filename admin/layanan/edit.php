<?php
// admin/layanan/edit.php
require_once '../../config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . '/pelanggan/dashboard.php');
}

// Gunakan koneksi dari config.php
$conn = getDBConnection();

// Ambil ID layanan dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/admin/layanan/');
}

// Ambil profil admin
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data layanan
$query = "SELECT * FROM layanan WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$layanan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$layanan) {
    redirect(BASE_URL . '/admin/layanan/');
}

$error = '';
$success = '';

// Proses form update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_layanan = sanitize($_POST['nama_layanan'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $harga = sanitize($_POST['harga'] ?? '');
    $durasi = sanitize($_POST['durasi'] ?? '');
    $status = sanitize($_POST['status'] ?? '');
    
    // Upload foto jika ada
    $foto = $layanan['foto'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadImage($_FILES['foto'], 'layanan');
        
        if ($upload_result['success']) {
            // Hapus foto lama jika bukan default
            if ($foto && $foto !== 'default.jpg' && file_exists(UPLOAD_PATH . 'layanan/' . $foto)) {
                unlink(UPLOAD_PATH . 'layanan/' . $foto);
            }
            $foto = $upload_result['filename'];
        } else {
            $error = $upload_result['error'];
        }
    }
    
    // Hapus foto jika dicentang
    if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1') {
        if ($foto && $foto !== 'default.jpg' && file_exists(UPLOAD_PATH . 'layanan/' . $foto)) {
            unlink(UPLOAD_PATH . 'layanan/' . $foto);
        }
        $foto = 'default.jpg';
    }
    
    if (!$error) {
        // Update data layanan
        $query = "UPDATE layanan SET 
                  nama_layanan = :nama_layanan,
                  deskripsi = :deskripsi,
                  harga = :harga,
                  durasi = :durasi,
                  foto = :foto,
                  status = :status,
                  updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':nama_layanan', $nama_layanan);
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':harga', $harga);
        $stmt->bindParam(':durasi', $durasi);
        $stmt->bindParam(':foto', $foto);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $success = 'Layanan berhasil diperbarui!';
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM layanan WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $layanan = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Terjadi kesalahan saat memperbarui layanan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Layanan - Admin</title>
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
        
        .form-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .form-header p {
            color: var(--dark-gray);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
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
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .image-preview-container {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: var(--light-pink);
            border-radius: 8px;
        }
        
        .current-image {
            max-width: 300px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 3px solid var(--pastel-pink);
            margin: 10px 0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            color: var(--dark-gray);
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--medium-gray);
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
        
        .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .action-left {
            display: flex;
            gap: 10px;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .action-left {
                justify-content: center;
            }
            
            .current-image {
                max-width: 100%;
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
                <h1><i class="fas fa-edit"></i> Edit Layanan</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', ($_SESSION['nama'] ?? 'Admin'))[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="Admin Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-spa"></i> Edit Layanan</h2>
                    <p>Perbarui informasi layanan</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="nama_layanan"><i class="fas fa-spa"></i> Nama Layanan</label>
                        <input type="text" id="nama_layanan" name="nama_layanan" class="form-control" required 
                               value="<?php echo htmlspecialchars($layanan['nama_layanan'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi"><i class="fas fa-align-left"></i> Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" required 
                                  rows="5"><?php echo htmlspecialchars($layanan['deskripsi'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="harga"><i class="fas fa-money-bill-wave"></i> Harga (Rp)</label>
                        <input type="number" id="harga" name="harga" class="form-control" required 
                               value="<?php echo htmlspecialchars($layanan['harga'] ?? ''); ?>" min="0" step="1000">
                    </div>
                    
                    <div class="form-group">
                        <label for="durasi"><i class="far fa-clock"></i> Durasi (menit)</label>
                        <input type="number" id="durasi" name="durasi" class="form-control" required 
                               value="<?php echo htmlspecialchars($layanan['durasi'] ?? ''); ?>" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="aktif" <?php echo ($layanan['status'] ?? '') === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo ($layanan['status'] ?? '') === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Foto Layanan</label>
                        
                        <div class="image-preview-container">
                            <p>Foto saat ini:</p>
                            <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($layanan['foto'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($layanan['nama_layanan'] ?? 'Layanan'); ?>" 
                                 class="current-image"
                                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                            <p><small><?php echo htmlspecialchars($layanan['foto'] ?? ''); ?></small></p>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="hapus_foto" name="hapus_foto" value="1">
                            <label for="hapus_foto">Hapus foto saat ini dan gunakan default</label>
                        </div>
                        
                        <p style="margin: 15px 0 5px;">Atau upload foto baru:</p>
                        <input type="file" id="foto" name="foto" accept="image/*" class="form-control">
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP (Maks. 2MB). Biarkan kosong jika tidak ingin mengganti.</small>
                    </div>
                    
                    <div class="form-actions">
                        <div class="action-left">
                            <a href="<?php echo BASE_URL; ?>/admin/layanan/" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                            <a href="hapus.php?id=<?php echo $id; ?>" 
                               class="btn-danger" 
                               onclick="return confirm('Apakah Anda yakin ingin menghapus layanan ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
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
            
            // Toggle upload field based on delete checkbox
            const hapusFotoCheckbox = document.getElementById('hapus_foto');
            const uploadField = document.getElementById('foto');
            
            if (hapusFotoCheckbox && uploadField) {
                hapusFotoCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        uploadField.disabled = true;
                        uploadField.value = '';
                    } else {
                        uploadField.disabled = false;
                    }
                });
            }
            
            // Preview image before upload
            const fotoInput = document.getElementById('foto');
            if (fotoInput) {
                fotoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Validasi ukuran file
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Ukuran file maksimal 2MB!');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewContainer = document.querySelector('.image-preview-container');
                            previewContainer.innerHTML = `
                                <p>Preview foto baru:</p>
                                <img src="${e.target.result}" 
                                     alt="Preview Foto Baru" 
                                     class="current-image">
                                <p><small>${file.name}</small></p>
                            `;
                        }
                        reader.readAsDataURL(file);
                    }
                });
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
                    // Reset sidebar state on desktop
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