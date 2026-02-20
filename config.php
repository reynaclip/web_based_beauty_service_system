<?php
// config.php
session_start();

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
        $_ENV[trim($key)] = trim($value);
    }
}

// Define constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'db_kecantikan');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/kecantikan');
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_PATH . 'layanan/')) {
    mkdir(UPLOAD_PATH . 'layanan/', 0777, true);
}
if (!file_exists(UPLOAD_PATH . 'users/')) {
    mkdir(UPLOAD_PATH . 'users/', 0777, true);
}

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper functions dengan function_exists() check
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('isPelanggan')) {
    function isPelanggan() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'pelanggan';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
        return date($format, strtotime($date));
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $badges = [
            'menunggu' => 'badge-menunggu',
            'dikonfirmasi' => 'badge-dikonfirmasi',
            'diproses' => 'badge-diproses',
            'selesai' => 'badge-selesai',
            'dibatalkan' => 'badge-dibatalkan',
            'aktif' => 'badge-aktif',
            'nonaktif' => 'badge-nonaktif'
        ];
        
        return isset($badges[$status]) ? $badges[$status] : 'badge-secondary';
    }
}

if (!function_exists('validateImage')) {
    function validateImage($file) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = MAX_FILE_SIZE;
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Error dalam upload file.';
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            return 'Format file tidak didukung. Hanya JPG, PNG, GIF, dan WebP.';
        }
        
        if ($file['size'] > $max_size) {
            return 'Ukuran file terlalu besar. Maksimal 2MB.';
        }
        
        return true;
    }
}

if (!function_exists('uploadImage')) {
    function uploadImage($file, $directory) {
        $validation = validateImage($file);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $destination = UPLOAD_PATH . $directory . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true, 'filename' => $filename];
        }
        
        return ['success' => false, 'error' => 'Gagal mengupload file.'];
    }
}

if (!function_exists('getPagination')) {
    function getPagination($total_records, $current_page, $per_page = 10) {
        $total_pages = ceil($total_records / $per_page);
        $prev_page = ($current_page > 1) ? $current_page - 1 : null;
        $next_page = ($current_page < $total_pages) ? $current_page + 1 : null;
        
        return [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'per_page' => $per_page,
            'prev_page' => $prev_page,
            'next_page' => $next_page,
            'offset' => ($current_page - 1) * $per_page
        ];
    }
}

if (!function_exists('logout')) {
    function logout() {
        // Hapus semua data session
        $_SESSION = array();
        
        // Hapus session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], 
                $params["domain"], 
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Hancurkan session
        session_destroy();
    }
}

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout($timeout_minutes = 30) {
        if (isset($_SESSION['LAST_ACTIVITY'])) {
            $timeout = $timeout_minutes * 60; // Convert to seconds
            if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout) {
                logout();
                redirect(BASE_URL . '/login.php?session=expired');
            }
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

if (!function_exists('regenerateSession')) {
    function regenerateSession() {
        if (!isset($_SESSION['CREATED'])) {
            $_SESSION['CREATED'] = time();
        } elseif (time() - $_SESSION['CREATED'] > 1800) { // Regenerate every 30 minutes
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
    }
}

// Execute session security functions
regenerateSession();
checkSessionTimeout();

// Autoload classes (if using OOP)
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Database connection function
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        static $conn = null;
        if ($conn === null) {
            try {
                $conn = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return $conn;
    }
}
?>