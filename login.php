<?php
// login.php - VERSION WITH DETAILED DEBUGGING
require_once 'config.php';

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session
echo "<!-- Session started: " . session_id() . " -->";
echo "<!-- Is logged in? " . (isLoggedIn() ? 'YES' : 'NO') . " -->";

// Redirect jika sudah login
if (isLoggedIn()) {
    echo "<!-- User is already logged in, redirecting -->";
    if (isAdmin()) {
        redirect(BASE_URL . '/admin/dashboard.php');
    } else {
        redirect(BASE_URL . '/pelanggan/dashboard.php');
    }
}

$error = '';
$email_value = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- Form submitted -->";
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<!-- Email entered: " . htmlspecialchars($email) . " -->";
    echo "<!-- Password length: " . strlen($password) . " -->";
    
    // Validasi input
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi!';
        $email_value = $email;
        echo "<!-- Validation failed: empty fields -->";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
        $email_value = $email;
        echo "<!-- Validation failed: invalid email -->";
    } else {
        try {
            echo "<!-- Trying database connection... -->";
            
            // Koneksi database
            $conn = getDBConnection();
            echo "<!-- Database connected successfully -->";
            
            // Test query
            $test = $conn->query("SELECT 1");
            echo "<!-- Test query executed -->";
            
            // Cari user berdasarkan email
            $stmt = $conn->prepare("SELECT id, nama_lengkap, email, password, role, status, foto FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<!-- User FOUND in database -->";
                echo "<!-- User ID: " . $user['id'] . " -->";
                echo "<!-- User email: " . $user['email'] . " -->";
                echo "<!-- User role: " . $user['role'] . " -->";
                echo "<!-- User status: " . $user['status'] . " -->";
                echo "<!-- Password hash: " . $user['password'] . " -->";
                echo "<!-- Hash length: " . strlen($user['password']) . " -->";
                
                // Debug password verification step by step
                $password_correct = password_verify($password, $user['password']);
                echo "<!-- password_verify() result: " . ($password_correct ? 'TRUE' : 'FALSE') . " -->";
                
                if ($password_correct) {
                    echo "<!-- Password is CORRECT -->";
                    
                    // Cek status user
                    if ($user['status'] !== 'aktif') {
                        $error = 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.';
                        $email_value = $email;
                        echo "<!-- Account is not active -->";
                    } else {
                        echo "<!-- Setting session variables -->";
                        
                        // Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['nama'] = $user['nama_lengkap'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['foto'] = $user['foto'] ?? 'default.jpg';
                        $_SESSION['login_time'] = time();
                        
                        echo "<!-- Session variables set -->";
                        echo "<!-- user_id: " . $_SESSION['user_id'] . " -->";
                        echo "<!-- nama: " . $_SESSION['nama'] . " -->";
                        echo "<!-- email: " . $_SESSION['email'] . " -->";
                        echo "<!-- role: " . $_SESSION['role'] . " -->";
                        
                        // Update last login
                        try {
                            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $updateStmt->execute([$user['id']]);
                            echo "<!-- Last login updated -->";
                        } catch (Exception $e) {
                            echo "<!-- Last login update failed: " . $e->getMessage() . " -->";
                        }
                        
                        // Regenerate session untuk keamanan
                        session_regenerate_id(true);
                        echo "<!-- Session regenerated -->";
                        
                        // Redirect berdasarkan role
                        if ($user['role'] === 'admin') {
                            echo "<!-- Redirecting to admin dashboard -->";
                            redirect(BASE_URL . '/admin/dashboard.php');
                        } else {
                            echo "<!-- Redirecting to pelanggan dashboard -->";
                            redirect(BASE_URL . '/pelanggan/dashboard.php');
                        }
                    }
                } else {
                    $error = 'Email atau password salah!';
                    $email_value = $email;
                    echo "<!-- Password is INCORRECT -->";
                    
                    // Untuk debugging, coba hash input password
                    $input_hash = password_hash($password, PASSWORD_DEFAULT);
                    echo "<!-- Input password hash: " . $input_hash . " -->";
                    echo "<!-- Compare: DB hash starts with: " . substr($user['password'], 0, 30) . " -->";
                    echo "<!-- Compare: Input hash starts with: " . substr($input_hash, 0, 30) . " -->";
                }
            } else {
                $error = 'Email atau password salah!';
                $email_value = $email;
                echo "<!-- User NOT found in database -->";
                
                // Debug: cek semua email yang ada
                try {
                    $allStmt = $conn->query("SELECT email FROM users");
                    $allUsers = $allStmt->fetchAll();
                    echo "<!-- Users in database: ";
                    foreach ($allUsers as $u) {
                        echo $u['email'] . ", ";
                    }
                    echo " -->";
                } catch (Exception $e) {
                    echo "<!-- Could not fetch all users -->";
                }
            }
            
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            echo "<!-- DATABASE ERROR: " . $e->getMessage() . " -->";
            echo "<!-- Error in file: " . $e->getFile() . " -->";
            echo "<!-- Error on line: " . $e->getLine() . " -->";
            error_log('Login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lunelle Beauty</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #ff6b9d;
            --light-pink: #ffe6ee;
            --dark-pink: #d81b60;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-gray: #495057;
            --text-color: #333333;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 15px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .login-header p {
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-pink);
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .input-with-icon input:focus {
            outline: none;
            border-color: var(--dark-pink);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c62828;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #2e7d32;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }
        
        .login-footer a {
            color: var(--dark-pink);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--dark-gray);
            cursor: pointer;
        }
        
        .demo-info {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .demo-info h4 {
            color: var(--dark-pink);
            margin-bottom: 10px;
        }
        
        .debug-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .debug-link a {
            color: #666;
            font-size: 12px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-spa"></i> WELCOME</h2>
            <p>Masuk ke sistem Lunelle Beauty</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['session']) && $_GET['session'] === 'expired'): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> Sesi Anda telah berakhir. Silakan login kembali.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Anda telah berhasil logout.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['register']) && $_GET['register'] === 'success'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Pendaftaran berhasil! Silakan login dengan akun Anda.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required 
                           placeholder="contoh@email.com" 
                           value="<?php echo htmlspecialchars($email_value); ?>"
                           autocomplete="email"
                           autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required 
                           placeholder="Masukkan password"
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="login-footer">
            <p>Belum punya akun? <a href="<?php echo BASE_URL; ?>/register.php">Daftar di sini</a></p>
            <p>Kembali ke <a href="<?php echo BASE_URL; ?>">Beranda</a></p>
        </div>
        
        
        <div class="demo-info">
            <h4>Akun Demo:</h4>
            <p><strong>Admin:</strong> admin@salon.com / admin123</p>
            <p><strong>User1:</strong> user1@gmail.com / 1234567</p>
            <p><strong>User2:</strong> user2@gmail.com / 123456</p>
            <p><strong>User3:</strong> user3@gmail.com / 123456</p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = togglePassword.querySelector('i');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email) {
                e.preventDefault();
                alert('Email harus diisi!');
                document.getElementById('email').focus();
                return false;
            }
            
            if (!validateEmail(email)) {
                e.preventDefault();
                alert('Format email tidak valid!');
                document.getElementById('email').focus();
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Password harus diisi!');
                document.getElementById('password').focus();
                return false;
            }
            
            return true;
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>