<?php
// register.php
require_once 'config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$success = '';
$input_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_data = [
        'nama_lengkap' => sanitize($_POST['nama_lengkap'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'no_telepon' => sanitize($_POST['no_telepon'] ?? ''),
        'alamat' => sanitize($_POST['alamat'] ?? '')
    ];
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi input
    $errors = [];
    
    if (empty($input_data['nama_lengkap'])) {
        $errors[] = 'Nama lengkap harus diisi!';
    }
    
    if (empty($input_data['email'])) {
        $errors[] = 'Email harus diisi!';
    } elseif (!filter_var($input_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid!';
    }
    
    if (empty($input_data['no_telepon'])) {
        $errors[] = 'Nomor telepon harus diisi!';
    } else {
        // Hapus semua karakter non-digit
        $clean_phone = preg_replace('/[^0-9]/', '', $input_data['no_telepon']);
        
        // Hapus awalan 0 jika ada (karena sudah ada prefix +62 di form)
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = substr($clean_phone, 1);
        }
        
        if (strlen($clean_phone) < 10 || strlen($clean_phone) > 13) {
            $errors[] = 'Nomor telepon harus 10-13 digit!';
        } elseif (!preg_match('/^8[0-9]{9,12}$/', $clean_phone)) {
            // Validasi harus diawali dengan 8 dan total 10-13 digit
            $errors[] = 'Nomor telepon harus diawali dengan angka 8!';
        } else {
            // Format ulang nomor telepon dengan awalan 62 (kode negara Indonesia)
            $input_data['no_telepon'] = '62' . $clean_phone;
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password harus diisi!';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter!';
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Konfirmasi password harus diisi!';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Password dan konfirmasi password tidak cocok!';
    }
    
    if (empty($input_data['alamat'])) {
        $errors[] = 'Alamat harus diisi!';
    }
    
    if (count($errors) > 0) {
        $error = implode('<br>', $errors);
    } else {
        try {
            // Gunakan koneksi dari config.php
            $conn = getDBConnection();
            
            // Cek apakah email sudah terdaftar
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $input_data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email sudah terdaftar!';
            } else {
                // Cek apakah nomor telepon sudah terdaftar
                $query = "SELECT id FROM users WHERE no_telepon = :no_telepon";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':no_telepon', $input_data['no_telepon']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Nomor telepon sudah terdaftar!';
                } else {
                    // Upload foto profil jika ada
                    $foto = 'default.jpg';
                    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadImage($_FILES['foto'], 'users');
                        if ($upload_result['success']) {
                            $foto = $upload_result['filename'];
                        }
                    }
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user baru DENGAN telepon
                    $query = "INSERT INTO users (nama_lengkap, email, no_telepon, password, alamat, foto, role, status) 
                              VALUES (:nama_lengkap, :email, :no_telepon, :password, :alamat, :foto, 'pelanggan', 'aktif')";
                    
                    $stmt = $conn->prepare($query);
                    
                    // Bind parameters DENGAN telepon
                    $stmt->bindParam(':nama_lengkap', $input_data['nama_lengkap']);
                    $stmt->bindParam(':email', $input_data['email']);
                    $stmt->bindParam(':no_telepon', $input_data['no_telepon']);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':alamat', $input_data['alamat']);
                    $stmt->bindParam(':foto', $foto);
                    
                    // Execute
                    $result = $stmt->execute();
                    
                    if ($result) {
                        $success = 'Registrasi berhasil! Anda akan diarahkan ke halaman login dalam 3 detik.';
                        
                        // Clear input data
                        $input_data = [];
                        
                        // Redirect ke login setelah 3 detik
                        header("refresh:3;url=" . BASE_URL . "/login.php");
                    } else {
                        $error_info = $stmt->errorInfo();
                        $error = 'Terjadi kesalahan saat registrasi. Silakan coba lagi.';
                        error_log("Register error: " . print_r($error_info, true));
                    }
                }
            }
            
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan pada database. Error: ' . $e->getMessage();
            error_log("Database error in register.php: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan. Silakan coba lagi. Error: ' . $e->getMessage();
            error_log("General error in register.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Salon Kecantikan</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #ff6b9d;
            --light-pink: #ffe6ee;
            --dark-pink: #d81b60;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #ff6b9d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: var(--dark-pink);
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .register-header p {
            color: var(--dark-gray);
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-pink);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group label.required::after {
            content: " *";
            color: #f44336;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background: var(--light-gray);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--dark-pink);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--dark-pink) 0%, var(--primary-pink) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(216, 27, 96, 0.3);
        }
        
        .btn-register:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216, 27, 96, 0.4);
        }
        
        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #d32f2f;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #d32f2f;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .error-message i {
            margin-top: 2px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #2e7d32;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .success-message i {
            margin-top: 2px;
        }
        
        .register-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--medium-gray);
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        .register-footer a {
            color: var(--dark-pink);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .register-footer a:hover {
            color: var(--primary-pink);
            text-decoration: underline;
        }
        
        .register-footer p {
            margin: 10px 0;
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            display: block;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: block;
            padding: 15px;
            background: var(--light-gray);
            border: 2px dashed var(--medium-gray);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            color: var(--dark-gray);
            transition: var(--transition);
        }
        
        .file-upload-label:hover {
            background: var(--medium-gray);
            border-color: var(--dark-pink);
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }
        
        .file-info i {
            color: var(--dark-pink);
            margin-right: 5px;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .strength-weak {
            color: #f44336;
        }
        
        .strength-medium {
            color: #ff9800;
        }
        
        .strength-strong {
            color: #4caf50;
        }
        
        .password-match {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .match-success {
            color: #4caf50;
        }
        
        .match-error {
            color: #f44336;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--light-gray);
            border-top: 5px solid var(--dark-pink);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .phone-prefix {
            display: flex;
            align-items: center;
        }
        
        .phone-prefix .prefix {
            padding: 12px 15px;
            background: var(--medium-gray);
            border: 2px solid var(--medium-gray);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .phone-prefix input {
            border-radius: 0 10px 10px 0;
        }
        
        .phone-error {
            color: #f44336 !important;
            font-size: 0.85rem !important;
            margin-top: 5px !important;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .register-header h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2><i class="fas fa-user-plus"></i> Daftar Akun Baru</h2>
            <p>Bergabunglah dengan Lunelle Beauty</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error:</strong><br>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Sukses!</strong><br>
                    <?php echo $success; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
            <div class="form-group">
                <label for="nama_lengkap" class="required">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" 
                       required placeholder="Masukkan nama lengkap"
                       value="<?php echo htmlspecialchars($input_data['nama_lengkap'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email" class="required">Email</label>
                <input type="email" id="email" name="email" class="form-control" 
                       required placeholder="contoh@email.com"
                       value="<?php echo htmlspecialchars($input_data['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="no_telepon" class="required">Nomor Telepon</label>
                <div class="phone-prefix">
                    <span class="prefix">+62</span>
                    <input type="tel" id="no_telepon" name="no_telepon" class="form-control" 
                           required placeholder="81234567890" 
                           pattern="[0-9]{10,13}"
                           title="Contoh: 81234567890 (10-13 digit, diawali dengan 8)"
                           value="<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $input_data['no_telepon'] ?? '')); ?>">
                </div>
                <small style="color: var(--dark-gray); font-size: 0.85rem; margin-top: 5px; display: block;">
                    <i class="fas fa-info-circle"></i> Contoh: 81234567890 (10-13 digit, harus diawali dengan angka 8)
                </small>
            </div>
            
            <div class="form-group">
                <label for="password" class="required">Password</label>
                <input type="password" id="password" name="password" class="form-control" 
                       required placeholder="Minimal 6 karakter" minlength="6">
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="required">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                       required placeholder="Ulangi password" minlength="6">
                <div class="password-match" id="passwordMatch"></div>
            </div>
            
            <div class="form-group">
                <label for="alamat" class="required">Alamat</label>
                <textarea id="alamat" name="alamat" class="form-control" 
                          required placeholder="Masukkan alamat lengkap"><?php echo htmlspecialchars($input_data['alamat'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="foto">Foto Profil (Opsional)</label>
                <div class="file-upload">
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <label for="foto" class="file-upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Klik untuk memilih foto</span>
                    </label>
                </div>
                <div class="file-info">
                    <i class="fas fa-info-circle"></i>
                    Format: JPG, PNG, GIF, WebP (Maks. 2MB)
                </div>
            </div>
            
            <div style="margin: 25px 0; padding: 15px; background-color: var(--light-pink); border-radius: 10px;">
                <p style="color: var(--dark-pink); font-weight: 600; margin-bottom: 10px;">
                    <i class="fas fa-shield-alt"></i> Keamanan Data
                </p>
                <p style="color: var(--dark-gray); font-size: 0.9rem; margin-bottom: 5px;">
                    <i class="fas fa-check"></i> Data Anda akan dijaga kerahasiaannya
                </p>
                <p style="color: var(--dark-gray); font-size: 0.9rem;">
                    <i class="fas fa-check"></i> Nomor telepon akan digunakan untuk konfirmasi pesanan
                </p>
            </div>
            
            <button type="submit" class="btn-register" id="registerButton">
                <i class="fas fa-user-plus"></i>
                <span>Daftar Sekarang</span>
            </button>
        </form>
        
        <div class="register-footer">
            <p>Sudah punya akun? <a href="<?php echo BASE_URL; ?>/login.php">Login di sini</a></p>
            <p>Kembali ke <a href="<?php echo BASE_URL; ?>">Beranda</a></p>
            <p style="margin-top: 20px; font-size: 0.9rem; color: var(--medium-gray);">
                <i class="fas fa-shield-alt"></i> Data Anda aman bersama kami
            </p>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Sedang memproses pendaftaran...</p>
    </div>
    
    <script>
        // Format nomor telepon
        const phoneInput = document.getElementById('no_telepon');
        
        phoneInput.addEventListener('input', function() {
            // Hapus semua karakter non-digit
            let value = this.value.replace(/[^0-9]/g, '');
            
            // Batasi panjang maksimal 13 digit
            if (value.length > 13) {
                value = value.substring(0, 13);
            }
            
            // Update nilai input
            this.value = value;
            
            // Validasi format
            validatePhoneNumber(value);
        });
        
        function validatePhoneNumber(phone) {
            if (phone.length === 0) {
                clearPhoneError();
                return false;
            }
            
            // Validasi format Indonesia (harus diawali 8)
            if (!phone.startsWith('8')) {
                showPhoneError('Nomor telepon harus diawali dengan angka 8');
                return false;
            }
            
            // Validasi panjang
            if (phone.length < 10) {
                showPhoneError('Nomor telepon minimal 10 digit (contoh: 8123456789)');
                return false;
            }
            
            if (phone.length > 13) {
                showPhoneError('Nomor telepon maksimal 13 digit (contoh: 8123456789012)');
                return false;
            }
            
            // Hapus pesan error jika valid
            clearPhoneError();
            return true;
        }
        
        function showPhoneError(message) {
            let errorDiv = phoneInput.parentNode.querySelector('.phone-error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'phone-error';
                phoneInput.parentNode.appendChild(errorDiv);
            }
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        }
        
        function clearPhoneError() {
            const errorDiv = phoneInput.parentNode.querySelector('.phone-error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = '';
            let strengthClass = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 6) {
                strength = 'Lemah (minimal 6 karakter)';
                strengthClass = 'strength-weak';
            } else if (password.length < 8) {
                strength = 'Sedang';
                strengthClass = 'strength-medium';
            } else if (/[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Kuat';
                strengthClass = 'strength-strong';
            } else {
                strength = 'Cukup';
                strengthClass = 'strength-medium';
            }
            
            if (strength) {
                passwordStrength.innerHTML = `<span class="${strengthClass}">${strength}</span>`;
            } else {
                passwordStrength.innerHTML = '';
            }
            
            // Check password match
            checkPasswordMatch();
        });
        
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length === 0) {
                passwordMatch.innerHTML = '';
            } else if (password === confirmPassword) {
                passwordMatch.innerHTML = '<span class="match-success"><i class="fas fa-check"></i> Password cocok</span>';
            } else {
                passwordMatch.innerHTML = '<span class="match-error"><i class="fas fa-times"></i> Password tidak cocok</span>';
            }
        }
        
        // File upload preview
        const fileInput = document.getElementById('foto');
        const fileLabel = document.querySelector('.file-upload-label span');
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                fileLabel.textContent = fileName;
                
                // Validate file size
                const fileSize = this.files[0].size; // in bytes
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (fileSize > maxSize) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    this.value = '';
                    fileLabel.textContent = 'Klik untuk memilih foto';
                }
            }
        });
        
        // Form validation and submission
        const registerForm = document.getElementById('registerForm');
        const registerButton = document.getElementById('registerButton');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const namaLengkap = document.getElementById('nama_lengkap').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('no_telepon').value.trim();
            const password = document.getElementById('password').value.trim();
            const confirmPassword = document.getElementById('confirm_password').value.trim();
            const alamat = document.getElementById('alamat').value.trim();
            
            // Client-side validation
            let isValid = true;
            let errorMessages = [];
            
            if (!namaLengkap) {
                isValid = false;
                errorMessages.push('Nama lengkap harus diisi!');
            }
            
            if (!email) {
                isValid = false;
                errorMessages.push('Email harus diisi!');
            } else if (!validateEmail(email)) {
                isValid = false;
                errorMessages.push('Format email tidak valid!');
            }
            
            if (!phone) {
                isValid = false;
                errorMessages.push('Nomor telepon harus diisi!');
            } else if (!validatePhoneNumber(phone)) {
                isValid = false;
                // Pesan error sudah ditampilkan oleh fungsi validatePhoneNumber
                if (!phone.startsWith('8')) {
                    errorMessages.push('Nomor telepon harus diawali dengan angka 8!');
                } else if (phone.length < 10) {
                    errorMessages.push('Nomor telepon minimal 10 digit!');
                } else if (phone.length > 13) {
                    errorMessages.push('Nomor telepon maksimal 13 digit!');
                }
            }
            
            if (!password) {
                isValid = false;
                errorMessages.push('Password harus diisi!');
            } else if (password.length < 6) {
                isValid = false;
                errorMessages.push('Password minimal 6 karakter!');
            }
            
            if (!confirmPassword) {
                isValid = false;
                errorMessages.push('Konfirmasi password harus diisi!');
            } else if (password !== confirmPassword) {
                isValid = false;
                errorMessages.push('Password dan konfirmasi password tidak cocok!');
            }
            
            if (!alamat) {
                isValid = false;
                errorMessages.push('Alamat harus diisi!');
            }
            
            if (!isValid) {
                showError(errorMessages.join('<br>'));
                return false;
            }
            
            // Show loading overlay
            loadingOverlay.style.display = 'flex';
            registerButton.disabled = true;
            registerButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Memproses...</span>';
            
            // Submit form after validation
            setTimeout(() => {
                registerForm.submit();
            }, 1000);
            
            return true;
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function showError(message) {
            // Create or update error message display
            let errorDiv = document.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                const container = document.querySelector('.register-container');
                const header = document.querySelector('.register-header');
                container.insertBefore(errorDiv, header.nextSibling);
            }
            
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error:</strong><br>
                    ${message}
                </div>
            `;
            
            // Scroll to error message
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const fields = ['nama_lengkap', 'email', 'no_telepon', 'password', 'confirm_password', 'alamat'];
            for (let fieldId of fields) {
                const field = document.getElementById(fieldId);
                if (field && !field.value) {
                    field.focus();
                    break;
                }
            }
        });
    </script>
</body>
</html>