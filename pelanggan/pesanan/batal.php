<?php
// pelanggan/pesanan/batal.php
require_once '../../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

$conn = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/pelanggan/pesanan/');
}

$user_id = $_SESSION['user_id'];

// Cek apakah pesanan milik user ini dan status masih menunggu
$query = "SELECT id, status FROM pesanan WHERE id = :id AND user_id = :user_id AND status = 'menunggu'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$pesanan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pesanan) {
    $_SESSION['error_message'] = 'Pesanan tidak ditemukan atau tidak dapat dibatalkan.';
    redirect(BASE_URL . '/pelanggan/pesanan/');
}

// Update status pesanan menjadi dibatalkan
$query = "UPDATE pesanan SET status = 'dibatalkan', updated_at = NOW() WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'Pesanan berhasil dibatalkan!';
} else {
    $_SESSION['error_message'] = 'Gagal membatalkan pesanan.';
}

redirect(BASE_URL . '/pelanggan/pesanan/');
?>