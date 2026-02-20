<?php
// pelanggan/pesanan/buat.php
require_once '../../config.php';

// Check login dan role
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

if (isAdmin()) {
    redirect(BASE_URL . '/admin/dashboard.php');
}

// Gunakan koneksi dari config.php
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get service ID
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($service_id === 0) {
    redirect(BASE_URL . '/pelanggan/layanan.php');
}

// Get service details
$query = "SELECT * FROM layanan WHERE id = :id AND status = 'aktif'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $service_id, PDO::PARAM_INT);
$stmt->execute();
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['error'] = 'Layanan tidak ditemukan atau tidak tersedia.';
    redirect(BASE_URL . '/pelanggan/layanan.php');
}

// Ambil data profil
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Process booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_pesanan = sanitize($_POST['tanggal_pesanan']);
    $jam_pesanan = sanitize($_POST['jam_pesanan']);
    $catatan = sanitize($_POST['catatan'] ?? '');
    
    // Validate date
    $today = date('Y-m-d');
    if ($tanggal_pesanan < $today) {
        $error = 'Tanggal pesanan tidak boleh kurang dari hari ini.';
    } else {
        // Check if time slot is available
        $query = "SELECT COUNT(*) as count FROM pesanan 
                  WHERE tanggal_pesanan = :tanggal 
                  AND jam_pesanan = :jam 
                  AND status NOT IN ('dibatalkan', 'selesai')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':tanggal', $tanggal_pesanan);
        $stmt->bindParam(':jam', $jam_pesanan);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing['count'] > 0) {
            $error = 'Jam tersebut sudah dipesan. Silakan pilih jam lain.';
        } else {
            // Generate booking code
            $kode_pesanan = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Create booking
            $query = "INSERT INTO pesanan (kode_pesanan, user_id, layanan_id, tanggal_pesanan, jam_pesanan, catatan, total_harga, status) 
                      VALUES (:kode_pesanan, :user_id, :layanan_id, :tanggal_pesanan, :jam_pesanan, :catatan, :total_harga, 'menunggu')";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':kode_pesanan', $kode_pesanan);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':layanan_id', $service_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_pesanan', $tanggal_pesanan);
            $stmt->bindParam(':jam_pesanan', $jam_pesanan);
            $stmt->bindParam(':catatan', $catatan);
            $stmt->bindParam(':total_harga', $service['harga']);
            
            if ($stmt->execute()) {
                $order_id = $conn->lastInsertId();
                $success = 'Pesanan berhasil dibuat! Kode pesanan Anda: ' . $kode_pesanan;
                
                // Redirect to order detail after 3 seconds
                header("refresh:3;url=detail.php?id=" . $order_id);
            } else {
                $error = 'Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.';
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
    <title>Buat Pesanan - Pelanggan</title>
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
        
        /* =========== SIDEBAR =========== */
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
            color: #ffffff;
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
        
        /* =========== MAIN CONTENT =========== */
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
        
        .btn-primary {
            display: inline-block;
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
        
        .btn-primary i {
            margin-right: 8px;
        }
        
        /* =========== BOOKING STYLES =========== */
        .booking-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .booking-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid var(--primary-pink);
            position: relative;
        }
        
        .booking-header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--dark-pink);
        }
        
        .booking-header h2 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .booking-header p {
            color: var(--dark-gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .booking-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
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
            
            .user-info {
                align-self: flex-end;
            }
        }
        
        @media (max-width: 768px) {
            .booking-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .booking-container {
                padding: 20px;
            }
        }
        
        .service-info {
            background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
            border-radius: var(--border-radius);
            padding: 25px;
            border: 2px solid var(--primary-pink);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .service-info h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(231, 70, 148, 0.2);
        }
        
        .service-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }
        
        .info-label {
            color: var(--dark-pink);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            color: var(--dark-gray);
            text-align: right;
            max-width: 60%;
        }
        
        .info-value.price {
            color: var(--dark-pink);
            font-weight: 700;
            font-size: 1.4rem;
            background: white;
            padding: 5px 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .booking-form-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            border: 2px solid var(--pastel-pink);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .booking-form-container h3 {
            color: var(--dark-pink);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(231, 70, 148, 0.2);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(231, 70, 148, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .error-message {
            background: linear-gradient(90deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 5px solid #c62828;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .success-message {
            background: linear-gradient(90deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 5px solid #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .success-message p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .form-actions {
            display: flex;
            gap: 20px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #f1f1f1;
        }
        
        .btn-back {
            background: linear-gradient(90deg, #718096 0%, #4a5568 100%);
            color: white;
            padding: 14px 30px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }
        
        .btn-back:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(113, 128, 150, 0.3);
            color: white;
        }
        
        .btn-book {
            background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
            flex: 2;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(231, 70, 148, 0.3);
        }
        
        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 70, 148, 0.4);
        }
        
        .date-input-wrapper {
            position: relative;
        }
        
        .calendar-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-pink);
            pointer-events: none;
            font-size: 1.1rem;
        }
        
        .booking-summary {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
            border: 2px solid var(--primary-pink);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .booking-summary h4 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(231, 70, 148, 0.2);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--primary-pink);
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--dark-pink);
        }
        
        .summary-total span:last-child {
            font-size: 1.5rem;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
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
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
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
                <h1><i class="fas fa-calendar-check"></i> Buat Pesanan Baru</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Pelanggan')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="User Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <div class="booking-container">
                <div class="booking-header">
                    <h2><i class="fas fa-calendar-check"></i> Buat Pesanan Baru</h2>
                    <p>Isi formulir di bawah untuk membuat pesanan baru dengan layanan yang Anda pilih</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <p>Anda akan diarahkan ke halaman detail pesanan...</p>
                    </div>
                <?php endif; ?>
                
                <div class="booking-content">
                    <!-- Service Information -->
                    <div class="service-info">
                        <h3><i class="fas fa-spa"></i> Informasi Layanan</h3>
                        
                        <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($service['foto'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($service['nama_layanan']); ?>" 
                             class="service-image"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-tag"></i> Nama Layanan
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($service['nama_layanan']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-file-alt"></i> Deskripsi
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($service['deskripsi']); ?></div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-clock"></i> Durasi
                            </div>
                            <div class="info-value"><?php echo $service['durasi']; ?> menit</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-money-bill-wave"></i> Harga
                            </div>
                            <div class="info-value price"><?php echo formatRupiah($service['harga']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Booking Form -->
                    <div class="booking-form-container">
                        <h3><i class="fas fa-edit"></i> Formulir Pesanan</h3>
                        
                        <form method="POST" action="" id="bookingForm">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            
                            <div class="form-group">
                                <label for="tanggal_pesanan"><i class="far fa-calendar-alt"></i> Tanggal Pesanan</label>
                                <div class="date-input-wrapper">
                                    <input type="date" id="tanggal_pesanan" name="tanggal_pesanan" 
                                           class="form-control" required 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo date('Y-m-d'); ?>">
                                    <i class="fas fa-calendar-alt calendar-icon"></i>
                                </div>
                                <small style="color: #718096; font-size: 0.9rem; margin-top: 5px; display: block;">
                                    Pilih tanggal untuk layanan
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="jam_pesanan"><i class="far fa-clock"></i> Jam Pesanan</label>
                                <select id="jam_pesanan" name="jam_pesanan" class="form-control" required>
                                    <option value="">Pilih Jam</option>
                                    <?php
                                    // Generate time slots (9:00 - 18:00)
                                    for ($hour = 9; $hour <= 18; $hour++) {
                                        $time = sprintf('%02d:00', $hour);
                                        echo "<option value='$time'>$time</option>";
                                        
                                        if ($hour < 18) {
                                            $time = sprintf('%02d:30', $hour);
                                            echo "<option value='$time'>$time</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small style="color: #718096; font-size: 0.9rem; margin-top: 5px; display: block;">
                                    Jam operasional: 09:00 - 18:00
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="catatan"><i class="far fa-comment-alt"></i> Catatan Tambahan (Opsional)</label>
                                <textarea id="catatan" name="catatan" class="form-control" 
                                          placeholder="Tambahkan catatan khusus untuk layanan... (misal: alergi, preferensi khusus, dll.)"></textarea>
                            </div>
                            
                            <!-- Booking Summary -->
                            <div class="booking-summary">
                                <h4><i class="fas fa-receipt"></i> Ringkasan Pesanan</h4>
                                <div class="summary-row">
                                    <span>Layanan:</span>
                                    <span><?php echo htmlspecialchars($service['nama_layanan']); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Harga:</span>
                                    <span><?php echo formatRupiah($service['harga']); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Tanggal:</span>
                                    <span id="summaryDate"><?php echo date('d/m/Y'); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Jam:</span>
                                    <span id="summaryTime">-</span>
                                </div>
                                <div class="summary-total">
                                    <span>Total:</span>
                                    <span id="summaryTotal"><?php echo formatRupiah($service['harga']); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="btn-back">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                                <button type="submit" class="btn-book">
                                    <i class="fas fa-check-circle"></i> Konfirmasi Pesanan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Update summary when date changes
        document.getElementById('tanggal_pesanan').addEventListener('change', function() {
            const date = new Date(this.value);
            const formattedDate = date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            document.getElementById('summaryDate').textContent = formattedDate;
        });
        
        // Update summary when time changes
        document.getElementById('jam_pesanan').addEventListener('change', function() {
            document.getElementById('summaryTime').textContent = this.value || '-';
        });
        
        // Validate form before submit
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const date = document.getElementById('tanggal_pesanan').value;
            const time = document.getElementById('jam_pesanan').value;
            
            if (!date || !time) {
                e.preventDefault();
                alert('Silakan isi tanggal dan jam pesanan.');
                return false;
            }
            
            // Check if date is not in the past
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Tanggal pesanan tidak boleh kurang dari hari ini.');
                return false;
            }
            
            return true;
        });
        
        // Format date display on page load
        const dateInput = document.getElementById('tanggal_pesanan');
        const initialDate = new Date(dateInput.value);
        document.getElementById('summaryDate').textContent = initialDate.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 992) {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                
                // Buat tombol menu
                const menuButton = document.createElement('button');
                menuButton.innerHTML = '<i class="fas fa-bars"></i>';
                menuButton.classList.add('menu-toggle');
                
                // Atur style untuk tombol menu
                menuButton.style.cssText = `
                    background: linear-gradient(90deg, var(--primary-pink) 0%, var(--dark-pink) 100%);
                    color: white;
                    border: none;
                    width: 45px;
                    height: 45px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 1.2rem;
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                    position: fixed;
                    top: 20px;
                    left: 20px;
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
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
                mainContent.style.paddingTop = '70px';
            }
        });
    </script>
</body>
</html>