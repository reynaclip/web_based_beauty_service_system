<?php
// index.php
require_once 'config.php';

// Gunakan koneksi database langsung dari config atau database.php
require_once 'database.php';

// Karena $conn sudah dibuat di database.php, kita bisa langsung gunakan
// atau jika tidak ada, buat koneksi baru
if (!isset($conn)) {
    $db = new Database();
    $conn = $db->getConnection();
}

// Ambil data layanan untuk ditampilkan di halaman utama
$query = "SELECT * FROM layanan WHERE status = 'aktif' ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$layanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lunelle Beauty - Beranda</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Definisikan variabel CSS */
        :root {
            --primary-pink: #ffb6c1;
            --light-pink: #ffe6e6;
            --pastel-pink: #ffd6e7;
            --soft-pink: #f9c5d1;
            --dark-pink: #c2185b;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #495057;
            --text-color: #333333;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --radius: 10px;
            --radius-lg: 15px;
        }
        
        /* Reset dasar */
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
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Button Styles */
        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            padding: 12px 30px;
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .btn-small {
            display: inline-block;
            background-color: var(--primary-pink);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-small:hover {
            background-color: var(--dark-pink);
            transform: translateY(-2px);
        }
        
        /* Tambahan style untuk halaman utama */
        .header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 15px 0;
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo h1 {
            color: var(--dark-pink);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .navbar ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }

        .navbar a {
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 20px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .navbar a:hover,
        .navbar a.active {
            background-color: var(--primary-pink);
            color: var(--white);
        }

        .hero {
            background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
            padding: 100px 0;
            text-align: center;
            color: var(--dark-pink);
        }

        .hero-content h2 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .hero-content p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
            color: var(--dark-gray);
        }

        .section-title {
            text-align: center;
            color: var(--dark-pink);
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .section-subtitle {
            text-align: center;
            color: var(--dark-gray);
            margin-bottom: 50px;
            font-size: 1.1rem;
        }

        .layanan-section {
            padding: 80px 0;
            background-color: var(--white);
        }

        .layanan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .layanan-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .layanan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
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
            transform: scale(1.1);
        }

        .layanan-info {
            padding: 20px;
        }

        .layanan-info h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.4rem;
        }

        .layanan-info p {
            color: var(--dark-gray);
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .layanan-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--medium-gray);
        }

        .harga {
            color: var(--dark-pink);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .durasi {
            color: var(--dark-gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .about-section {
            padding: 80px 0;
            background-color: var(--light-pink);
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .about-text h3 {
            color: var(--dark-pink);
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .about-text p {
            color: var(--dark-gray);
            margin-bottom: 20px;
        }

        .about-text ul {
            list-style: none;
        }

        .about-text li {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .about-text li i {
            color: var(--dark-pink);
        }

        .about-image img {
            width: 100%;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .contact-section {
            padding: 80px 0;
            background-color: var(--white);
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .contact-item {
            text-align: center;
            padding: 30px 20px;
            background: var(--light-pink);
            border-radius: 15px;
            transition: var(--transition);
        }

        .contact-item:hover {
            background: var(--pastel-pink);
            transform: translateY(-5px);
        }

        .contact-item i {
            font-size: 2.5rem;
            color: var(--dark-pink);
            margin-bottom: 15px;
        }

        .contact-item h3 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .footer {
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            padding: 60px 0 20px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-logo {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .footer-logo img {
            height: 60px;
            width: auto;
        }

        .footer-logo p {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .footer-links h3,
        .footer-social h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--light-pink);
            padding-left: 5px;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: var(--white);
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .social-icons a:hover {
            background-color: var(--white);
            color: var(--dark-pink);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .header .container {
                flex-direction: column;
                gap: 15px;
            }
            
            .navbar ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero-content h2 {
                font-size: 2rem;
            }
            
            .about-content {
                grid-template-columns: 1fr;
            }
            
            .layanan-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Badge styles */
        .badge {
            display: inline-block;
            padding: 5px 10px;
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
        
        .badge-aktif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-nonaktif {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="<?php echo BASE_URL; ?>/assets/images/lunelle.jpg" alt="Logo Salon Kecantikan">
                <h1>Lunelle Beauty</h1>
            </div>
            <nav class="navbar">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>" class="active"><i class="fas fa-home"></i> Beranda</a></li>
                    <li><a href="#layanan"><i class="fas fa-spa"></i> Layanan</a></li>
                    <li><a href="#tentang"><i class="fas fa-info-circle"></i> Tentang Kami</a></li>
                    <li><a href="#kontak"><i class="fas fa-phone"></i> Kontak</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo BASE_URL; ?>/pelanggan/dashboard.php"><i class="fas fa-user"></i> Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Selamat Datang di Lunelle Beauty</h2>
                <p>Temukan keindahan sejati dengan layanan profesional kami. Perawatan kecantikan terbaik dengan harga terjangkau.</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn-primary">Daftar Sekarang</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Layanan Section -->
    <section id="layanan" class="layanan-section">
        <div class="container">
            <h2 class="section-title">Layanan Kami</h2>
            <p class="section-subtitle">Berbagai perawatan kecantikan untuk membuat Anda tampil lebih percaya diri</p>
            
            <div class="layanan-grid">
                <?php if (count($layanan) > 0): ?>
                    <?php foreach ($layanan as $item): ?>
                    <div class="layanan-card">
                        <div class="layanan-image">
                            <img src="<?php echo BASE_URL . '/assets/uploads/layanan/' . ($item['foto'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['nama_layanan']); ?>">
                        </div>
                        <div class="layanan-info">
                            <h3><?php echo htmlspecialchars($item['nama_layanan']); ?></h3>
                            <p><?php echo htmlspecialchars($item['deskripsi']); ?></p>
                            <div class="layanan-footer">
                                <span class="harga"><?php echo formatRupiah($item['harga']); ?></span>
                                <span class="durasi"><i class="far fa-clock"></i> <?php echo $item['durasi']; ?> menit</span>
                            </div>
                            <?php if (isLoggedIn() && !isAdmin()): ?>
                                <a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/buat.php?id=<?php echo $item['id']; ?>" class="btn-small">Pesan Sekarang</a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="<?php echo BASE_URL; ?>/login.php" class="btn-small">Login untuk Pesan</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <p>Tidak ada layanan yang tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Tentang Kami Section -->
    <section id="tentang" class="about-section">
        <div class="container">
            <h2 class="section-title">Tentang Kami</h2>
            <div class="about-content">
                <div class="about-text">
                    <h3>Lunelle Beauty Terpercaya Sejak 2020</h3>
                    <p>Kami adalah salon kecantikan profesional yang berkomitmen untuk memberikan layanan terbaik dengan menggunakan produk berkualitas tinggi dan teknologi terkini. Tim ahli kami siap membantu Anda mencapai penampilan terbaik.</p>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Staf profesional bersertifikat</li>
                        <li><i class="fas fa-check-circle"></i> Produk berkualitas premium</li>
                        <li><i class="fas fa-check-circle"></i> Peralatan steril dan modern</li>
                        <li><i class="fas fa-check-circle"></i> Pelayanan ramah dan nyaman</li>
                    </ul>
                </div>
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1560066984-138dadb4c035?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Salon Interior">
                </div>
            </div>
        </div>
    </section>

    <!-- Kontak Section -->
    <section id="kontak" class="contact-section">
        <div class="container">
            <h2 class="section-title">Hubungi Kami</h2>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Alamat</h3>
                    <p>Jl. Beauty No. 123, Cirebon</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <h3>Telepon</h3>
                    <p>(021) 1234-5678</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p>info@lunellebeauty.com</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <h3>Jam Operasional</h3>
                    <p>Senin - Sabtu: 09:00 - 18:00</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="<?php echo BASE_URL; ?>/assets/images/lunelle.jpg" alt="Logo Salon Kecantikan">
                    <p>Lunelle Beauty</p>
                </div>
                <div class="footer-links">
                    <h3>Tautan Cepat</h3>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>">Beranda</a></li>
                        <li><a href="#layanan">Layanan</a></li>
                        <li><a href="#tentang">Tentang Kami</a></li>
                        <li><a href="#kontak">Kontak</a></li>
                    </ul>
                </div>
                <div class="footer-social">
                    <h3>Ikuti Kami</h3>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Lunelle Beauty. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll untuk anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            if (window.scrollY > 50) {
                header.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
                header.style.padding = '10px 0';
            } else {
                header.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
                header.style.padding = '15px 0';
            }
        });
        
        // Jika file script.js tidak ada
        if (typeof window.onload === 'function') {
            window.onload();
        }
    </script>
</body>
</html>