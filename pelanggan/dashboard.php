<?php
// pelanggan/dashboard.php
require_once '../config.php';

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

// Ambil data pesanan pelanggan
$query = "SELECT p.*, l.nama_layanan, l.foto as foto_layanan 
          FROM pesanan p 
          JOIN layanan l ON p.layanan_id = l.id 
          WHERE p.user_id = :user_id 
          ORDER BY p.created_at DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total pesanan
$query = "SELECT COUNT(*) as total FROM pesanan WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$total_pesanan = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung pesanan yang sedang diproses
$query = "SELECT COUNT(*) as total FROM pesanan WHERE user_id = :user_id AND status IN ('dikonfirmasi', 'diproses')";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$pesanan_diproses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ambil data profil
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$profil = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pelanggan</title>
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
        
        .welcome-section {
            background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            color: var(--dark-pink);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        
        .welcome-text h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }
        
        .table-container h2 {
            color: var(--dark-pink);
            margin-bottom: 25px;
            font-size: 1.5rem;
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
            padding: 15px;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-pink);
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        tr:hover {
            background-color: var(--light-pink);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .layanan-image-small {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 20px;
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
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php"><i class="fas fa-spa"></i> Layanan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/"><i class="fas fa-shopping-cart"></i> Pesanan Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/profil.php"><i class="fas fa-user"></i> Profil Saya</a></li>
                    <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Kunjungi Situs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Pelanggan</h1>
                <div class="user-info">
                    <span style="font-weight: 500;">Halo, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Pelanggan')[0]); ?></span>
                    <div class="user-avatar">
                        <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($profil['foto'] ?? 'default.jpg'); ?>" 
                             alt="User Avatar"
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
                    </div>
                </div>
            </div>
            
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-text">
                    <h2>Selamat Datang, <?php echo htmlspecialchars(explode(' ', $_SESSION['nama'] ?? 'Pelanggan')[0]); ?>!</h2>
                    <p>Nikmati berbagai layanan kecantikan terbaik kami. Pesan sekarang dan dapatkan penampilan terbaik Anda!</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="btn-primary">
                    <i class="fas fa-spa"></i> Lihat Layanan
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Pesanan</h3>
                        <div class="stat-number"><?php echo $total_pesanan; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Sedang Diproses</h3>
                        <div class="stat-number"><?php echo $pesanan_diproses; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pesanan Selesai</h3>
                        <div class="stat-number">
                            <?php 
                            $query = "SELECT COUNT(*) as total FROM pesanan WHERE user_id = :user_id AND status = 'selesai'";
                            $stmt = $conn->prepare($query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->execute();
                            echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pesanan Terbaru -->
            <div class="table-container">
                <h2><i class="fas fa-history"></i> Pesanan Terbaru</h2>
                <?php if (count($pesanan) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Layanan</th>
                            <th>Tanggal Pesan</th>
                            <th>Jam</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan as $item): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($item['foto_layanan'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama_layanan']); ?>" 
                                         class="layanan-image-small"
                                         onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                                    <span><?php echo htmlspecialchars($item['nama_layanan']); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($item['tanggal_pesanan'])); ?></td>
                            <td><?php echo date('H:i', strtotime($item['jam_pesanan'])); ?></td>
                            <td><?php echo formatRupiah($item['total_harga']); ?></td>
                            <td>
                                <?php 
                                $badge_class = '';
                                switch($item['status']) {
                                    case 'menunggu': $badge_class = 'badge-menunggu'; break;
                                    case 'dikonfirmasi': $badge_class = 'badge-dikonfirmasi'; break;
                                    case 'diproses': $badge_class = 'badge-diproses'; break;
                                    case 'selesai': $badge_class = 'badge-selesai'; break;
                                    case 'dibatalkan': $badge_class = 'badge-dibatalkan'; break;
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Belum Ada Pesanan</h3>
                    <p>Anda belum membuat pesanan apapun. Ayo pesan layanan kecantikan pertama Anda!</p>
                    <a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-spa"></i> Lihat Layanan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle (untuk responsif)
        document.addEventListener('DOMContentLoaded', function() {
            // Tambahkan menu toggle untuk mobile
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
        });
        
        // Auto refresh setiap 30 detik untuk update status
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>