<?php
// admin/pesanan/update_status.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

$conn = getDBConnection();

// Get parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Validasi status
$allowed_statuses = ['menunggu', 'dikonfirmasi', 'diproses', 'selesai', 'dibatalkan'];
if ($id === 0 || !in_array($status, $allowed_statuses)) {
    redirect(BASE_URL . '/admin/pesanan/');
}

// Verifikasi pesanan ada
$query = "SELECT * FROM pesanan WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$pesanan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pesanan) {
    redirect(BASE_URL . '/admin/pesanan/');
}

// Update status
$query = "UPDATE pesanan SET status = :status, updated_at = NOW() WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);

if ($stmt->execute()) {
    // Log activity (opsional)
    $log_message = "Pesanan #{$pesanan['kode_pesanan']} diubah status menjadi {$status}";
    
    // Redirect dengan pesan sukses
    $_SESSION['success_message'] = "Status pesanan berhasil diubah menjadi " . ucfirst($status) . "!";
} else {
    $_SESSION['error_message'] = "Gagal mengubah status pesanan.";
}

redirect(BASE_URL . '/admin/pesanan/');
?>