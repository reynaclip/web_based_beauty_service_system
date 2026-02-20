<?php
// pelanggan/profil.php
require_once '../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../database.php';
$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Get user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
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
        $no_telepon = sanitize($_POST['no_telepon'] ?? ''); // Ubah menjadi no_telepon
        
        // Validasi nomor telepon
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
                $foto = $user['foto'] ?? 'default.jpg';
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
                    // Update user data - UBAH menjadi no_telepon
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
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
        if (!password_verify($current_password, $user['password'])) {
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Pelanggan</title>
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: var(--pastel-pink);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
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
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--dark-pink);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
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
            border: 2px dashed var(--medium-gray);
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
            color: #d32f2f;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border-left: 4px solid #d32f2f;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .error-message i {
            margin-top: 2px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border-left: 4px solid #2e7d32;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .success-message i {
            margin-top: 2px;
        }
        
        .account-info {
            background: var(--light-pink);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--dark-pink);
        }
        
        .account-info h4 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-aktif {
            background-color: #d4edda;
            color: #155724;
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
            
            .profile-container {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .btn-submit {
                width: 100%;
                justify-content: center;
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
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/"><i class="fas fa-shopping-cart"></i> Pesanan Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/profil.php" class="active"><i class="fas fa-user"></i> Profil Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Kunjungi Situs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-user"></i> Profil Saya</h1>
                <a href="<?php echo BASE_URL; ?>/pelanggan/dashboard.php" class="btn-back" style="background: var(--medium-gray); color: var(--dark-gray); padding: 10px 20px; border-radius: 5px; text-decoration: none; display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar-container">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($user['foto'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" 
                             class="profile-avatar"
                             id="profileAvatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                        <label for="foto" class="avatar-upload-label" title="Ubah foto">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <h2><?php echo htmlspecialchars($user['nama_lengkap']); ?></h2>
                    <p>Pelanggan Lunelle Beauty</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-container">
                    <?php
                    // Get order statistics
                    $query = "SELECT 
                                COUNT(*) as total_orders,
                                SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_spent,
                                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_orders
                              FROM pesanan 
                              WHERE user_id = :user_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="stat-item">
                        <div class="stat-count"><?php echo $stats['total_orders'] ?: '0'; ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-count"><?php echo $stats['completed_orders'] ?: '0'; ?></div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-count"><?php echo formatRupiah($stats['total_spent'] ?: '0'); ?></div>
                        <div class="stat-label">Total Pengeluaran</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-count">
                            <?php echo formatDate($user['created_at'] ?? date('Y-m-d H:i:s'), 'd/m/Y'); ?>
                        </div>
                        <div class="stat-label">Bergabung Sejak</div>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="account-info">
                    <h4><i class="fas fa-info-circle"></i> Informasi Akun</h4>
                    <div class="info-row">
                        <div class="info-label">Status Akun</div>
                        <div class="info-value">
                            <span class="badge badge-<?php echo $user['status'] ?? 'aktif'; ?>">
                                <?php echo ucfirst($user['status'] ?? 'aktif'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Role</div>
                        <div class="info-value"><?php echo ucfirst($user['role'] ?? 'pelanggan'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nomor Telepon</div> <!-- Ubah label -->
                        <div class="info-value"><?php echo htmlspecialchars($user['no_telepon'] ?? '-'); ?></div> <!-- Ubah menjadi no_telepon -->
                    </div>
                    <div class="info-row">
                        <div class="info-label">Terdaftar Pada</div>
                        <div class="info-value"><?php echo formatDate($user['created_at'] ?? date('Y-m-d H:i:s'), 'd/m/Y H:i'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Terakhir Update</div>
                        <div class="info-value"><?php echo formatDate($user['updated_at'] ?? $user['created_at'] ?? date('Y-m-d H:i:s'), 'd/m/Y H:i'); ?></div>
                    </div>
                </div>
                
                <!-- Update Profile Form -->
                <div class="form-section">
                    <h3><i class="fas fa-user-edit"></i> Edit Profil</h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        
                        <!-- UBAH FIELD MENJADI no_telepon -->
                        <div class="form-group">
                            <label for="no_telepon"><i class="fas fa-phone"></i> Nomor Telepon</label> <!-- Ubah label -->
                            <input type="text" id="no_telepon" name="no_telepon" class="form-control" 
                                   placeholder="Contoh: 081234567890"
                                   value="<?php echo htmlspecialchars($user['no_telepon'] ?? ''); ?>"> <!-- Ubah menjadi no_telepon -->
                            <small style="color: var(--dark-gray); margin-top: 5px; display: block;">
                                <i class="fas fa-info-circle"></i> Masukkan nomor telepon 10-13 digit angka
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat"><i class="fas fa-home"></i> Alamat</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="4"><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-camera"></i> Foto Profil</label>
                            
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
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
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
                    const originalSrc = '<?php echo BASE_URL; ?>/assets/uploads/users/<?php echo $user['foto'] ?? 'default.jpg'; ?>';
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
            
            // Validate phone number format - UBAH ID MENJADI no_telepon
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
    </script>
</body>
</html>