<?php
// admin/layanan/toggle_status.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

$conn = getDBConnection();

// Get parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Validasi status
if ($id === 0 || !in_array($status, ['aktif', 'nonaktif'])) {
    redirect(BASE_URL . '/admin/layanan/');
}

// Update status
$query = "UPDATE layanan SET status = :status, updated_at = NOW() WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Status layanan berhasil diubah!";
} else {
    $_SESSION['error_message'] = "Gagal mengubah status layanan.";
}

redirect(BASE_URL . '/admin/layanan/');
?>