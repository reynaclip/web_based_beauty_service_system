<?php
// admin/pesanan/detail.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil profil admin
$user_id = $_SESSION['user_id'];
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $conn->prepare($query_user);
$stmt_user->bindParam(':user_id', $user_id);
$stmt_user->execute();
$profil = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/admin/pesanan/');
}

// Cek apakah kolom catatan_admin ada di tabel pesanan
$check_column_query = "SHOW COLUMNS FROM pesanan LIKE 'catatan_admin'";
$check_column_stmt = $conn->query($check_column_query);
$has_catatan_admin = $check_column_stmt->rowCount() > 0;

// Get order details with user and service info
$columns = "p.*, u.nama_lengkap, u.email, u.no_telepon, u.alamat, 
            l.nama_layanan, l.deskripsi, l.harga, l.durasi, l.foto as foto_layanan";
if ($has_catatan_admin) {
    $columns .= ", p.catatan_admin";
}

$query = "SELECT $columns
          FROM pesanan p
          JOIN users u ON p.user_id = u.id
          JOIN layanan l ON p.layanan_id = l.id
          WHERE p.id = :id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect(BASE_URL . '/admin/pesanan/');
}

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

$kode_pesanan_pendek = formatKodePendek($order['kode_pesanan']);

// Update status if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = sanitize($_POST['status']);
        $catatan_admin = sanitize($_POST['catatan_admin'] ?? '');
        
        // Cek apakah kolom catatan_admin ada sebelum update
        if ($has_catatan_admin) {
            $query = "UPDATE pesanan SET status = :status, catatan_admin = :catatan_admin, updated_at = NOW() WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':catatan_admin', $catatan_admin);
        } else {
            $query = "UPDATE pesanan SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $conn->prepare($query);
        }
        
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $order['status'] = $new_status;
            if ($has_catatan_admin) {
                $order['catatan_admin'] = $catatan_admin;
            }
            $success = 'Status pesanan berhasil diperbarui!';
        } else {
            $error = 'Gagal memperbarui status pesanan.';
        }
    }
    
    // Kirim kode pesanan ke WhatsApp
    if (isset($_POST['send_whatsapp'])) {
        $phone_number = $order['no_telepon'];
        
        // Format nomor telepon (hilangkan +62 atau 0 di depan)
        $phone_number = preg_replace('/^\+62/', '', $phone_number);
        $phone_number = preg_replace('/^0/', '', $phone_number);
        
        // Pesan yang akan dikirim
        $message = "Halo " . $order['nama_lengkap'] . ",\n\n";
        $message .= "Terima kasih telah memesan layanan di *Lunelle Beauty & Spa Center*.\n\n";
        $message .= "Berikut adalah kode pesanan Anda:\n";
        $message .= "*" . $kode_pesanan_pendek . "*\n\n";
        $message .= "Detail Pesanan:\n";
        $message .= "• Layanan: " . $order['nama_layanan'] . "\n";
        $message .= "• Tanggal: " . formatDate($order['tanggal_pesanan'], 'd/m/Y') . "\n";
        $message .= "• Jam: " . date('H:i', strtotime($order['jam_pesanan'])) . "\n";
        $message .= "• Status: " . ucfirst($order['status']) . "\n\n";
        $message .= "Silakan tunjukkan kode ini kepada CS saat datang ke salon untuk pengecekan pesanan.\n\n";
        $message .= "Terima kasih,\n";
        $message .= "*Lunelle Beauty & Spa Center*";
        
        // Encode pesan untuk URL
        $encoded_message = urlencode($message);
        
        // Buat URL WhatsApp
        $whatsapp_url = "https://wa.me/62" . $phone_number . "?text=" . $encoded_message;
        
        // Redirect ke WhatsApp
        header("Location: " . $whatsapp_url);
        exit();
    }
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
    <title>Detail Pesanan - Admin</title>
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
            --whatsapp-green: #25D366;
            --whatsapp-dark-green: #128C7E;
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
        
        /* =========== ORDER DETAIL STYLES =========== */
        .order-detail-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        .order-header h2 {
            color: var(--dark-pink);
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .order-number {
            font-size: 1.2rem;
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .kode-pendek {
            background: var(--dark-pink);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            box-shadow: 0 4px 10px rgba(216, 27, 96, 0.3);
        }
        
        .status-badge-large {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: var(--light-pink);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        
        .info-section h3 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--primary-pink);
            font-size: 1.3rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--medium-gray);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            flex: 0 0 140px;
            color: var(--dark-pink);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .info-value {
            flex: 1;
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        .service-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 3px solid var(--pastel-pink);
        }
        
        .action-section {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            border: 2px solid var(--pastel-pink);
            box-shadow: var(--shadow);
        }
        
        .action-section h3 {
            color: var(--dark-pink);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-control {
            padding: 12px;
            border: 2px solid var(--pastel-pink);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background-color: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
            line-height: 1.5;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .btn-update {
            background-color: var(--dark-pink);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .btn-update:hover {
            background-color: var(--primary-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-back {
            background-color: var(--white);
            color: var(--dark-pink);
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid var(--dark-pink);
            box-shadow: var(--shadow);
        }
        
        .btn-back:hover {
            background-color: var(--pastel-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #c62828;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #2e7d32;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-print {
            background-color: #28a745;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .btn-print:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        /* Tombol WhatsApp */
        .btn-whatsapp {
            background-color: var(--whatsapp-green);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
            border: none;
            cursor: pointer;
        }
        
        .btn-whatsapp:hover {
            background-color: var(--whatsapp-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.3);
        }
        
        .btn-whatsapp:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-whatsapp:disabled:hover {
            background-color: #cccccc;
            transform: none;
            box-shadow: none;
        }
        
        /* Form WhatsApp */
        .whatsapp-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f0fff4;
            border-radius: 10px;
            border: 1px solid #c6f6d5;
        }
        
        .whatsapp-form h4 {
            color: var(--whatsapp-dark-green);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .whatsapp-preview {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            font-family: 'Segoe UI', sans-serif;
            line-height: 1.5;
            white-space: pre-line;
            font-size: 14px;
            color: #333;
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
        }
        
        @media (max-width: 992px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .status-form {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-back, .btn-print, .btn-whatsapp {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .order-detail-container {
                padding: 20px;
            }
            
            .order-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label {
                flex: none;
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .info-section {
                padding: 15px;
            }
            
            .action-section {
                padding: 20px;
            }
            
            .btn-update {
                width: 100%;
            }
            
            .whatsapp-form {
                padding: 15px;
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
            <div class="order-detail-container">
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="order-header">
                    <div>
                        <h2><i class="fas fa-shopping-cart"></i> Detail Pesanan</h2>
                        <div class="order-number">
                            Kode Pesanan: 
                            <span class="kode-pendek"><?php echo $kode_pesanan_pendek; ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="<?php echo getStatusBadge($order['status']); ?> status-badge-large">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid">
                    <!-- Customer Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-user"></i> Informasi Pelanggan</h3>
                        <div class="info-row">
                            <div class="info-label">Nama Lengkap:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['nama_lengkap']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['email']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">No. Telepon:</div>
                            <div class="info-value">
                                <?php if ($order['no_telepon']): ?>
                                    <?php echo htmlspecialchars($order['no_telepon']); ?>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Tidak tersedia</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Alamat:</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['alamat'] ?: '-')); ?></div>
                        </div>
                    </div>
                    
                    <!-- Service Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-spa"></i> Informasi Layanan</h3>
                        <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($order['foto_layanan'] ?? 'default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($order['nama_layanan']); ?>" 
                             class="service-image"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                        <div class="info-row">
                            <div class="info-label">Nama Layanan:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['nama_layanan']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Deskripsi:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['deskripsi']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Harga:</div>
                            <div class="info-value"><?php echo formatRupiah($order['harga']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Durasi:</div>
                            <div class="info-value"><?php echo $order['durasi']; ?> menit</div>
                        </div>
                    </div>
                    
                    <!-- Order Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-info-circle"></i> Informasi Pesanan</h3>
                        <div class="info-row">
                            <div class="info-label">Tanggal Pemesanan:</div>
                            <div class="info-value"><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Tanggal Layanan:</div>
                            <div class="info-value"><?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Jam Layanan:</div>
                            <div class="info-value"><?php echo date('H:i', strtotime($order['jam_pesanan'])); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Catatan Pelanggan:</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['catatan'] ?: '-')); ?></div>
                        </div>
                        <?php if ($has_catatan_admin): ?>
                        <div class="info-row">
                            <div class="info-label">Catatan Admin:</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($order['catatan_admin'] ?: '-')); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <div class="info-label">Total Harga:</div>
                            <div class="info-value"><strong style="color: var(--dark-pink); font-size: 1.1rem;"><?php echo formatRupiah($order['total_harga']); ?></strong></div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Update Form -->
                <div class="action-section">
                    <h3><i class="fas fa-sync-alt"></i> Update Status Pesanan</h3>
                    <form method="POST" action="" class="status-form">
                        <div class="form-group">
                            <label for="status"><i class="fas fa-tag"></i> Status Baru</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="menunggu" <?php echo $order['status'] === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                <option value="dikonfirmasi" <?php echo $order['status'] === 'dikonfirmasi' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                <option value="diproses" <?php echo $order['status'] === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="selesai" <?php echo $order['status'] === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="dibatalkan" <?php echo $order['status'] === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        
                        <?php if ($has_catatan_admin): ?>
                        <div class="form-group">
                            <label for="catatan_admin"><i class="fas fa-sticky-note"></i> Catatan Admin (Opsional)</label>
                            <textarea id="catatan_admin" name="catatan_admin" class="form-control" 
                                      placeholder="Tambahkan catatan untuk pelanggan..." 
                                      rows="3"><?php echo htmlspecialchars($order['catatan_admin'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="update_status" class="btn-update">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
                
                <!-- Kirim ke WhatsApp -->
                <div class="whatsapp-form">
                    <h4><i class="fab fa-whatsapp"></i> Kirim Kode Pesanan via WhatsApp</h4>
                    
                    <div class="whatsapp-preview">
Halo <?php echo htmlspecialchars($order['nama_lengkap']); ?>,

Terima kasih telah memesan layanan di *Lunelle Beauty & Spa Center*.

Berikut adalah kode pesanan Anda:
*<?php echo $kode_pesanan_pendek; ?>*

Detail Pesanan:
• Layanan: <?php echo htmlspecialchars($order['nama_layanan']); ?>
• Tanggal: <?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?>
• Jam: <?php echo date('H:i', strtotime($order['jam_pesanan'])); ?>
• Status: <?php echo ucfirst($order['status']); ?>

Silakan tunjukkan kode ini kepada CS saat datang ke salon untuk pengecekan pesanan.

Terima kasih,
*Lunelle Beauty & Spa Center*
                    </div>
                    
                    <form method="POST" action="">
                        <button type="submit" name="send_whatsapp" class="btn-whatsapp" 
                                <?php echo !$order['no_telepon'] ? 'disabled' : ''; ?>>
                            <i class="fab fa-whatsapp"></i> 
                            <?php if ($order['no_telepon']): ?>
                                Kirim ke <?php echo htmlspecialchars($order['no_telepon']); ?>
                            <?php else: ?>
                                Nomor WhatsApp Tidak Tersedia
                            <?php endif; ?>
                        </button>
                        <small style="display: block; margin-top: 10px; color: #666;">
                            <i class="fas fa-info-circle"></i> 
                            <?php if ($order['no_telepon']): ?>
                                Klik tombol di atas untuk membuka WhatsApp dengan pesan kode pesanan
                            <?php else: ?>
                                Pelanggan tidak memiliki nomor telepon yang tersedia
                            <?php endif; ?>
                        </small>
                    </form>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
                    </a>
                    
                    <a href="print.php?id=<?php echo $order['id']; ?>&kode=<?php echo urlencode($kode_pesanan_pendek); ?>" class="btn-print" target="_blank">
                        <i class="fas fa-print"></i> Cetak Invoice
                    </a>
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
            
            // Form validation
            const statusForm = document.querySelector('.status-form');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    const status = document.getElementById('status').value;
                    if (!status) {
                        e.preventDefault();
                        alert('Silakan pilih status pesanan.');
                    }
                });
            }
            
            // Konfirmasi sebelum kirim WhatsApp
            const whatsappBtn = document.querySelector('.btn-whatsapp');
            if (whatsappBtn && !whatsappBtn.disabled) {
                whatsappBtn.addEventListener('click', function(e) {
                    if (!confirm('Apakah Anda yakin ingin mengirim kode pesanan ini ke WhatsApp pelanggan?')) {
                        e.preventDefault();
                    }
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
        
        // Initialize menu toggle visibility
        if (window.innerWidth <= 768) {
            document.getElementById('menuToggle').style.display = 'block';
        } else {
            document.getElementById('menuToggle').style.display = 'none';
        }
    </script>
</body>
</html>