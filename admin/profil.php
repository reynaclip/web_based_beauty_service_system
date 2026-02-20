<?php
// admin/profil.php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (!isAdmin()) {
    redirect(BASE_URL . '/pelanggan/dashboard.php');
}

require_once '../database.php';
$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Get admin data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    redirect(BASE_URL . '/logout.php');
}

$error = '';
$success = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $alamat = sanitize($_POST['alamat'] ?? '');
        $no_telepon = sanitize($_POST['no_telepon'] ?? '');
        
        // Validate phone number
        if (!empty($no_telepon) && !preg_match('/^[0-9]{10,13}$/', $no_telepon)) {
            $error = 'Nomor telepon harus berupa angka antara 10-13 digit!';
        } else {
            // Check if email already exists (excluding current user)
            $query = "SELECT id FROM users WHERE email = :email AND id != :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email sudah digunakan oleh pengguna lain!';
            } else {
                // Handle photo upload
                $foto = $admin['foto'] ?? 'default.jpg';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['foto'], 'users');
                    
                    if ($upload_result['success']) {
                        // Delete old photo if not default
                        if ($foto && $foto !== 'default.jpg' && file_exists(UPLOAD_PATH . 'users/' . $foto)) {
                            @unlink(UPLOAD_PATH . 'users/' . $foto);
                        }
                        $foto = $upload_result['filename'];
                    } else {
                        $error = $upload_result['error'];
                    }
                }
                
                // Delete photo if checked
                if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1') {
                    if ($foto && $foto !== 'default.jpg' && file_exists(UPLOAD_PATH . 'users/' . $foto)) {
                        @unlink(UPLOAD_PATH . 'users/' . $foto);
                    }
                    $foto = 'default.jpg';
                }
                
                if (!$error) {
                    // Update admin data
                    $query = "UPDATE users SET 
                              nama_lengkap = :nama_lengkap,
                              email = :email,
                              alamat = :alamat,
                              no_telepon = :no_telepon,
                              foto = :foto,
                              updated_at = NOW()
                              WHERE id = :id";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nama_lengkap', $nama_lengkap);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':alamat', $alamat);
                    $stmt->bindParam(':no_telepon', $no_telepon);
                    $stmt->bindParam(':foto', $foto);
                    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        // Update session
                        $_SESSION['nama'] = $nama_lengkap;
                        $_SESSION['email'] = $email;
                        $_SESSION['foto'] = $foto;
                        
                        $success = 'Profil berhasil diperbarui!';
                        // Refresh admin data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = 'Terjadi kesalahan saat memperbarui profil.';
                    }
                }
            }
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($current_password, $admin['password'])) {
            $error = 'Password saat ini salah!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi password tidak cocok!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Terjadi kesalahan saat mengubah password.';
            }
        }
    }
}

// Get statistics for admin
function getAdminStats($conn) {
    $stats = [
        'total_pelanggan' => 0,
        'total_layanan' => 0,
        'total_pesanan' => 0,
        'total_pendapatan' => 0
    ];
    
    try {
        // Total pelanggan
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'pelanggan'";
        $stmt = $conn->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_pelanggan'] = $result['total'] ?? 0;
        
        // Total layanan
        $query = "SELECT COUNT(*) as total FROM layanan";
        $stmt = $conn->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_layanan'] = $result['total'] ?? 0;
        
        // Total pesanan
        $query = "SELECT COUNT(*) as total FROM pesanan";
        $stmt = $conn->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_pesanan'] = $result['total'] ?? 0;
        
        // Total pendapatan
        $query = "SELECT SUM(total_harga) as total FROM pesanan WHERE status = 'selesai'";
        $stmt = $conn->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_pendapatan'] = $result['total'] ?? 0;
        
    } catch (Exception $e) {
        // Return default stats if error
    }
    
    return $stats;
}

$admin_stats = getAdminStats($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin</title>
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
        
        /* =========== PROFILE STYLES =========== */
        .profile-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--pastel-pink);
        }
        
        .profile-avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 25px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-pink);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .avatar-upload-label {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--dark-pink);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid var(--white);
        }
        
        .avatar-upload-label:hover {
            background: var(--primary-pink);
            transform: scale(1.1);
        }
        
        .profile-header h2 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .profile-header p {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
        }
        
        /* Admin Stats */
        .admin-stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 2px solid var(--pastel-pink);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-pink);
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
            margin: 0 auto 15px;
        }
        
        .stat-content h3 {
            font-size: 0.95rem;
            color: var(--dark-gray);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-pink);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--dark-gray);
            margin-top: 5px;
        }
        
        /* Admin Info */
        .admin-info-card {
            background: var(--light-pink);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--dark-pink);
            box-shadow: var(--shadow);
        }
        
        .admin-info-card h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            color: var(--dark-gray);
            font-size: 0.95rem;
            padding-left: 28px;
        }
        
        /* Forms */
        .form-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 2px solid var(--pastel-pink);
        }
        
        .form-section h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
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
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--dark-pink);
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            color: var(--dark-gray);
            cursor: pointer;
        }
        
        .file-upload {
            margin-top: 10px;
        }
        
        .file-upload input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed var(--pastel-pink);
            border-radius: 8px;
            background: var(--light-pink);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .file-upload input[type="file"]:hover {
            border-color: var(--primary-pink);
        }
        
        .file-upload small {
            display: block;
            margin-top: 5px;
            color: var(--dark-gray);
            font-size: 0.85rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message i {
            margin-top: 2px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message i {
            margin-top: 2px;
        }
        
        /* Badge */
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
        
        .badge-admin {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 992px) {
            .admin-stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }
            
            .admin-stats-container {
                grid-template-columns: 1fr;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .btn-submit {
                width: 100%;
                justify-content: center;
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
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($admin['foto'] ?? 'default.jpg'); ?>" 
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
                    <li><a href="<?php echo BASE_URL; ?>/admin/pelanggan/"><i class="fas fa-user-friends"></i> Data Pelanggan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/backup.php"><i class="fas fa-database"></i> Backup Data</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/profil.php" class="active"><i class="fas fa-user-cog"></i> Profil Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Kunjungi Situs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="brand-logo">KECAN<span>TRAN</span></div>
                <small>Beauty & Spa Center</small>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar-container">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($admin['foto'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($admin['nama_lengkap']); ?>" 
                             class="profile-avatar"
                             id="profileAvatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                        <label for="foto" class="avatar-upload-label" title="Ubah foto">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <h2><?php echo htmlspecialchars($admin['nama_lengkap']); ?></h2>
                    <p>Administrator Sistem</p>
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
                
                <!-- Admin Statistics -->
                <div class="admin-stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($admin_stats['total_pelanggan']); ?></div>
                            <div class="stat-label">Total Pelanggan</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-spa"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($admin_stats['total_layanan']); ?></div>
                            <div class="stat-label">Layanan Tersedia</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($admin_stats['total_pesanan']); ?></div>
                            <div class="stat-label">Total Pesanan</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatRupiah($admin_stats['total_pendapatan']); ?></div>
                            <div class="stat-label">Total Pendapatan</div>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Information -->
                <div class="admin-info-card">
                    <h3><i class="fas fa-info-circle"></i> Informasi Admin</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-id-card"></i> Status Akun
                            </div>
                            <div class="info-value">
                                <span class="badge badge-<?php echo $admin['status'] ?? 'aktif'; ?>">
                                    <?php echo ucfirst($admin['status'] ?? 'aktif'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user-tag"></i> Role
                            </div>
                            <div class="info-value">
                                <span class="badge badge-admin">
                                    <?php echo ucfirst($admin['role'] ?? 'admin'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-envelope"></i> Email
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($admin['email'] ?? ''); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-phone"></i> Nomor Telepon
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($admin['no_telepon'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-home"></i> Alamat
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($admin['alamat'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus"></i> Terdaftar
                            </div>
                            <div class="info-value"><?php echo formatDate($admin['created_at'] ?? '', 'd/m/Y H:i'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-check"></i> Terakhir Update
                            </div>
                            <div class="info-value"><?php echo formatDate($admin['updated_at'] ?? $admin['created_at'] ?? '', 'd/m/Y H:i'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-shield-alt"></i> ID Admin
                            </div>
                            <div class="info-value">#<?php echo str_pad($admin['id'], 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Update Profile Form -->
                <div class="form-section">
                    <h3><i class="fas fa-user-edit"></i> Edit Profil Admin</h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required 
                                   value="<?php echo htmlspecialchars($admin['nama_lengkap'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="no_telepon">Nomor Telepon</label>
                            <input type="text" id="no_telepon" name="no_telepon" class="form-control" 
                                   placeholder="Contoh: 081234567890"
                                   value="<?php echo htmlspecialchars($admin['no_telepon'] ?? ''); ?>">
                            <small style="color: var(--dark-gray); margin-top: 5px; display: block;">
                                <i class="fas fa-info-circle"></i> 10-13 digit angka
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="4"><?php echo htmlspecialchars($admin['alamat'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Foto Profil</label>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="hapus_foto" name="hapus_foto" value="1">
                                <label for="hapus_foto">Hapus foto saat ini dan gunakan default</label>
                            </div>
                            
                            <div class="file-upload">
                                <input type="file" id="foto" name="foto" accept="image/*">
                                <small>Format: JPG, PNG, GIF, WebP (Maks. 2MB)</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Simpan Perubahan Profil
                        </button>
                    </form>
                </div>
                
                <!-- Change Password Form -->
                <div class="form-section">
                    <h3><i class="fas fa-key"></i> Ubah Password</h3>
                    
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
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
            
            // Preview image before upload
            document.getElementById('foto').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Check file size (max 2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 2MB.');
                        this.value = '';
                        return;
                    }
                    
                    // Check file type
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
                        this.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const avatar = document.getElementById('profileAvatar');
                        avatar.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                    
                    // Uncheck delete checkbox when uploading new photo
                    document.getElementById('hapus_foto').checked = false;
                }
            });
            
            // Toggle upload field based on delete checkbox
            document.getElementById('hapus_foto').addEventListener('change', function() {
                const uploadField = document.getElementById('foto');
                if (this.checked) {
                    uploadField.disabled = true;
                    uploadField.value = '';
                    // Set default avatar
                    document.getElementById('profileAvatar').src = '<?php echo BASE_URL; ?>/assets/images/default-avatar.png';
                } else {
                    uploadField.disabled = false;
                    // Restore original avatar
                    const originalSrc = '<?php echo BASE_URL; ?>/assets/uploads/users/<?php echo $admin['foto'] ?? 'default.jpg'; ?>';
                    document.getElementById('profileAvatar').src = originalSrc;
                    document.getElementById('profileAvatar').onerror = function() {
                        this.src = '<?php echo BASE_URL; ?>/assets/images/default-avatar.png';
                    };
                }
            });
            
            // Validate password match
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Password tidak cocok');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
            
            // Validate phone number format
            const phoneInput = document.getElementById('no_telepon');
            phoneInput.addEventListener('input', function() {
                // Remove non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            
            phoneInput.addEventListener('blur', function() {
                if (this.value && !/^[0-9]{10,13}$/.test(this.value)) {
                    alert('Nomor telepon harus antara 10-13 digit angka!');
                    this.focus();
                }
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