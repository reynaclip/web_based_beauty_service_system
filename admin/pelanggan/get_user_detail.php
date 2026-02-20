<?php
// admin/pelanggan/get_user_detail.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    die('Access denied');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Get user ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    die('Invalid user ID');
}

// Get user details
$query = "SELECT * FROM users WHERE id = :id AND role = 'pelanggan'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found');
}

// Get user's order statistics
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END) as total_spent,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_orders
          FROM pesanan 
          WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="user-detail">
    <img src="<?php echo BASE_URL . '/assets/uploads/users/' . ($user['foto'] ?: 'default.png'); ?>" 
         alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" 
         class="user-detail-avatar">
    
    <div class="user-detail-info">
        <h3><?php echo htmlspecialchars($user['nama_lengkap']); ?></h3>
        
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Telepon:</div>
            <div class="info-value"><?php echo htmlspecialchars($user['no_telepon'] ?: '-'); ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Alamat:</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($user['alamat'] ?: '-')); ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">
                <span class="badge <?php echo getStatusBadge($user['status']); ?>">
                    <?php echo ucfirst($user['status']); ?>
                </span>
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Tanggal Daftar:</div>
            <div class="info-value"><?php echo formatDate($user['created_at'], 'd/m/Y H:i'); ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Terakhir Update:</div>
            <div class="info-value"><?php echo formatDate($user['updated_at'], 'd/m/Y H:i'); ?></div>
        </div>
    </div>
    
    <div class="user-detail-info">
        <h3><i class="fas fa-chart-bar"></i> Statistik Pesanan</h3>
        
        <div class="info-row">
            <div class="info-label">Total Pesanan:</div>
            <div class="info-value"><?php echo $stats['total_orders'] ?: '0'; ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Pesanan Selesai:</div>
            <div class="info-value"><?php echo $stats['completed_orders'] ?: '0'; ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Total Pengeluaran:</div>
            <div class="info-value"><?php echo formatRupiah($stats['total_spent'] ?: '0'); ?></div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn-small">
            <i class="fas fa-edit"></i> Edit Pelanggan
        </a>
    </div>
</div>