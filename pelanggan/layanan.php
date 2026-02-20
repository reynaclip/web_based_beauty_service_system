<?php
// pelanggan/layanan.php
require_once '../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../database.php';
$db = new Database();
$conn = $db->getConnection();

// Get services
$query = "SELECT * FROM layanan WHERE status = 'aktif' ORDER BY nama_layanan";
$stmt = $conn->query($query);
$layanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan - Pelanggan</title>
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
        
        .btn-back {
            background: var(--medium-gray);
            color: var(--dark-gray);
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-back:hover {
            background: #c1c1c1;
            transform: translateY(-2px);
        }
        
        .page-description {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-pink);
        }
        
        .page-description h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        
        .page-description p {
            color: var(--dark-gray);
            line-height: 1.8;
        }
        
        .filter-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .filter-input, .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 16px;
            background: var(--white);
            transition: var(--transition);
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--dark-pink);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }
        
        .btn-filter {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
            height: 46px;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        .layanan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .layanan-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .layanan-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--medium-gray);
        }
        
        .layanan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-pink);
        }
        
        .layanan-image {
            height: 200px;
            overflow: hidden;
        }
        
        .layanan-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .layanan-card:hover .layanan-image img {
            transform: scale(1.05);
        }
        
        .layanan-info {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .layanan-info h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.3rem;
            min-height: 60px;
        }
        
        .layanan-info p {
            color: var(--dark-gray);
            margin-bottom: 15px;
            font-size: 0.95rem;
            flex: 1;
        }
        
        .layanan-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--medium-gray);
        }
        
        .layanan-harga {
            color: var(--dark-pink);
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .layanan-durasi {
            color: var(--dark-gray);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-pesan {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            padding: 14px 20px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            margin-top: auto;
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
        }
        
        .btn-pesan:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--pastel-pink);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark-pink);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--dark-gray);
            max-width: 500px;
            margin: 0 auto 20px;
            line-height: 1.8;
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
            
            .btn-back {
                align-self: flex-start;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-filter {
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
                    <li><a href="<?php echo BASE_URL; ?>/pelanggan/layanan.php" class="active"><i class="fas fa-spa"></i> Layanan</a></li>
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
                <h1><i class="fas fa-spa"></i> Layanan Salon</h1>
                <a href="<?php echo BASE_URL; ?>/pelanggan/dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            
            <div class="page-description">
                <h3><i class="fas fa-info-circle"></i> Pilih Layanan Favorit Anda</h3>
                <p>Temukan berbagai layanan kecantikan terbaik kami. Pilih layanan yang sesuai dengan kebutuhan Anda dan pesan sekarang juga!</p>
            </div>
            
            <!-- Search Filter -->
            <div class="filter-container">
                <form id="searchForm" method="GET">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search"><i class="fas fa-search"></i> Cari Layanan</label>
                            <input type="text" id="search" name="search" class="filter-input" 
                                   placeholder="Cari berdasarkan nama atau deskripsi...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort"><i class="fas fa-sort"></i> Urutkan Berdasarkan</label>
                            <select id="sort" name="sort" class="filter-select">
                                <option value="nama">Nama A-Z</option>
                                <option value="nama_desc">Nama Z-A</option>
                                <option value="harga">Harga Terendah</option>
                                <option value="harga_desc">Harga Tertinggi</option>
                                <option value="durasi">Durasi Tercepat</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Services Grid -->
            <div class="layanan-grid">
                <?php if (count($layanan) > 0): ?>
                    <?php foreach ($layanan as $item): ?>
                    <div class="layanan-card">
                        <div class="layanan-image">
                            <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . $item['foto']; ?>" 
                                 alt="<?php echo htmlspecialchars($item['nama_layanan']); ?>"
                                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-service.jpg'">
                        </div>
                        
                        <div class="layanan-info">
                            <h3><?php echo htmlspecialchars($item['nama_layanan']); ?></h3>
                            <p><?php echo htmlspecialchars($item['deskripsi']); ?></p>
                            
                            <div class="layanan-meta">
                                <div class="layanan-harga"><?php echo formatRupiah($item['harga']); ?></div>
                                <div class="layanan-durasi">
                                    <i class="far fa-clock"></i>
                                    <?php echo $item['durasi']; ?> menit
                                </div>
                            </div>
                            
                            <a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/buat.php?id=<?php echo $item['id']; ?>" 
                               class="btn-pesan">
                                <i class="fas fa-calendar-check"></i> Pesan Sekarang
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-spa"></i>
                        </div>
                        <h3>Tidak Ada Layanan Tersedia</h3>
                        <p>Maaf, saat ini tidak ada layanan yang tersedia. Silakan coba lagi nanti.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Simple search filter
        document.getElementById('search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.layanan-card');
            
            cards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('p').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Sort functionality
        document.getElementById('sort').addEventListener('change', function(e) {
            const sortValue = e.target.value;
            const container = document.querySelector('.layanan-grid');
            const cards = Array.from(document.querySelectorAll('.layanan-card'));
            
            cards.sort((a, b) => {
                let aValue, bValue;
                
                switch(sortValue) {
                    case 'nama':
                        aValue = a.querySelector('h3').textContent;
                        bValue = b.querySelector('h3').textContent;
                        return aValue.localeCompare(bValue);
                        
                    case 'nama_desc':
                        aValue = a.querySelector('h3').textContent;
                        bValue = b.querySelector('h3').textContent;
                        return bValue.localeCompare(aValue);
                        
                    case 'harga':
                        aValue = parseFloat(a.querySelector('.layanan-harga').textContent.replace(/[^0-9]/g, ''));
                        bValue = parseFloat(b.querySelector('.layanan-harga').textContent.replace(/[^0-9]/g, ''));
                        return aValue - bValue;
                        
                    case 'harga_desc':
                        aValue = parseFloat(a.querySelector('.layanan-harga').textContent.replace(/[^0-9]/g, ''));
                        bValue = parseFloat(b.querySelector('.layanan-harga').textContent.replace(/[^0-9]/g, ''));
                        return bValue - aValue;
                        
                    case 'durasi':
                        aValue = parseInt(a.querySelector('.layanan-durasi').textContent);
                        bValue = parseInt(b.querySelector('.layanan-durasi').textContent);
                        return aValue - bValue;
                        
                    default:
                        return 0;
                }
            });
            
            // Reorder cards
            cards.forEach(card => {
                container.appendChild(card);
            });
        });
        
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
        });
    </script>
</body>
</html>