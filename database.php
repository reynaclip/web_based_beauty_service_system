<?php
// database.php (versi sederhana)
require_once 'config.php';

class Database {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    public function getConnection() {
        return $this->conn;
    }


    // Fungsi untuk generate kode pesanan pendek
function generateKodePesanan() {
    require_once 'database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Cek jumlah pesanan hari ini
    $today = date('Y-m-d');
    $query = "SELECT COUNT(*) as total FROM pesanan WHERE DATE(created_at) = :today";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = $result['total'] + 1;
    
    // Format: K + tanggal (2 digit hari) + nomor urut (2 digit)
    // Contoh: K2701, K2702, dst (27 = tanggal, 01 = urutan)
    $day = date('d');
    $sequence = str_pad($count, 2, '0', STR_PAD_LEFT);
    
    return 'K' . $day . $sequence;
}

// Fungsi format kode pesanan pendek untuk tampilan
function formatKodePendek($kode) {
    // Jika kode sudah dalam format pendek, langsung return
    if (preg_match('/^K\d{3,4}$/', $kode)) {
        return $kode;
    }
    
    // Jika kode panjang, ekstrak angka terakhir
    preg_match('/\d+$/', $kode, $matches);
    if (!empty($matches[0])) {
        $angka = intval($matches[0]);
        $day = date('d', strtotime('today'));
        return 'K' . $day . str_pad($angka % 100, 2, '0', STR_PAD_LEFT);
    }
    
    // Default: K + tanggal + random
    $day = date('d');
    $random = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
    return 'K' . $day . $random;
}
}

// Buat instance global (opsional)
$db = new Database();
$conn = $db->getConnection();
?>