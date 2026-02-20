<?php
// logout.php
require_once 'config.php';

// Periksa apakah user sudah login
if (!isLoggedIn()) {
    // Jika tidak login, redirect ke halaman login
    redirect(BASE_URL . '/login.php');
}

// Simpan informasi user sebelum logout
$userName = $_SESSION['nama'] ?? 'Pengguna';

// Lakukan logout
logout();

// Redirect ke halaman login dengan pesan sukses
redirect(BASE_URL . '/login.php?logout=success');
?>