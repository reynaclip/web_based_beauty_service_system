<?php
// admin/download_backup.php
require_once '../config.php';

// Cek login dan role
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/pelanggan/dashboard.php');
    exit;
}

// Ambil nama file dari URL
$file = $_GET['file'] ?? '';

if (empty($file)) {
    die('File tidak ditemukan');
}

// Security: bersihkan nama file (hindari path traversal)
$file = basename($file);

// Tentukan path folder backup
$backupDir = __DIR__ . '/../backups/';
$filepath = $backupDir . $file;

// Cek apakah file benar-benar ada
if (!file_exists($filepath)) {
    die('File tidak ditemukan di server');
}

// Cek ekstensi file harus .sql
if (pathinfo($filepath, PATHINFO_EXTENSION) !== 'sql') {
    die('File tidak valid');
}

// Set header untuk download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Baca file dan kirim ke output
readfile($filepath);
exit;
?>