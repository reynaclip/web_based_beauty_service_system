<?php
// admin/layanan/hapus.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil ID layanan dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/admin/layanan/');
}

// Verifikasi CSRF token jika menggunakan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        redirect(BASE_URL . '/admin/layanan/');
    }
    
    // Ambil data layanan untuk menghapus foto
    $query = "SELECT foto FROM layanan WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $layanan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($layanan) {
        // Hapus foto jika ada dan bukan default
        if ($layanan['foto'] && $layanan['foto'] !== 'default.jpg') {
            $file_path = UPLOAD_PATH . 'layanan/' . $layanan['foto'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Hapus data dari database
        $query = "DELETE FROM layanan WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Layanan berhasil dihapus!';
        } else {
            $_SESSION['error_message'] = 'Terjadi kesalahan saat menghapus layanan.';
        }
    }
    
    redirect(BASE_URL . '/admin/layanan/');
} else {
    // Jika bukan POST, tampilkan konfirmasi
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Hapus - Admin</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, var(--pastel-pink) 0%, var(--soft-pink) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .confirmation-box {
                background: var(--white);
                padding: 40px;
                border-radius: 15px;
                box-shadow: var(--shadow);
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            
            .warning-icon {
                font-size: 4rem;
                color: #ff9800;
                margin-bottom: 20px;
            }
            
            .confirmation-box h2 {
                color: var(--dark-pink);
                margin-bottom: 15px;
            }
            
            .confirmation-box p {
                color: var(--dark-gray);
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
            }
            
            .btn-cancel {
                background-color: var(--medium-gray);
                color: var(--dark-gray);
                padding: 12px 30px;
                border-radius: 5px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: var(--transition);
            }
            
            .btn-cancel:hover {
                background-color: #d6d6d6;
            }
            
            .btn-confirm-delete {
                background-color: #dc3545;
                color: white;
                padding: 12px 30px;
                border-radius: 5px;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: var(--transition);
                font-size: 16px;
            }
            
            .btn-confirm-delete:hover {
                background-color: #c82333;
            }
        </style>
    </head>
    <body>
        <div class="confirmation-box">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h2>Konfirmasi Penghapusan</h2>
            
            <p>
                Apakah Anda yakin ingin menghapus layanan ini? 
                <br>
                <strong>Tindakan ini tidak dapat dibatalkan.</strong>
                <br><br>
                Semua data yang terkait dengan layanan ini akan dihapus permanen.
            </p>
            
            <form method="POST" action="">
                <input type="hidden" name="confirm" value="yes">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <div class="action-buttons">
                    <a href="<?php echo BASE_URL; ?>/admin/layanan/" class="btn-cancel">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn-confirm-delete">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
}
?>