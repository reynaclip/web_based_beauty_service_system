<?php
// admin/pesanan/print.php
require_once '../../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

$conn = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/admin/pesanan/');
}

// Get order details
$query = "SELECT p.*, u.nama_lengkap, u.email, u.no_telepon, u.alamat, 
                 l.nama_layanan, l.deskripsi, l.harga, l.durasi
          FROM pesanan p
          JOIN users u ON p.user_id = u.id
          JOIN layanan l ON p.layanan_id = l.id
          WHERE p.id = :id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect(BASE_URL . '/admin/pesanan/');
}

// Fungsi helper
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '-';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['kode_pesanan']; ?> - Admin</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 20mm;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                line-height: 1.4;
                color: #333;
                background: white;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            color: #333;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ff6b9d;
        }
        
        .invoice-header h1 {
            color: #d81b60;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .invoice-header .subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .company-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .company-info h2 {
            color: #d81b60;
            margin: 0 0 10px 0;
            font-size: 22px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .info-section h3 {
            color: #d81b60;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            flex: 0 0 120px;
            font-weight: bold;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .table-container {
            margin-bottom: 40px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #ffe6ee;
            color: #d81b60;
            text-align: left;
            padding: 12px 15px;
            font-weight: bold;
            border-bottom: 2px solid #ff6b9d;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .total-section {
            text-align: right;
            margin-top: 20px;
        }
        
        .total-row {
            display: inline-block;
            text-align: left;
            min-width: 300px;
        }
        
        .total-label {
            font-weight: bold;
            color: #555;
            margin-right: 20px;
            min-width: 150px;
            display: inline-block;
        }
        
        .total-value {
            font-weight: bold;
            color: #d81b60;
            font-size: 18px;
        }
        
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            color: #666;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        
        .btn-print {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-print:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .btn-back:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>INVOICE</h1>
            <div class="subtitle">Lunelle Beauty</div>
        </div>
        
        <div class="company-info">
            <h2>Lunelle Beauty</h2>
            <p>Jl. Beauty No. 123, Kota Cirebon</p>
            <p>Telp: (021) 123-4567 | Email: info@lunellebeauty.com</p>
        </div>
        
        <div class="info-grid">
            <!-- Customer Information -->
            <div class="info-section">
                <h3>Informasi Pelanggan</h3>
                <div class="info-row">
                    <div class="info-label">Nama:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['nama_lengkap']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['email']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Telepon:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['no_telepon'] ?: '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Alamat:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['alamat'] ?: '-')); ?></div>
                </div>
            </div>
            
            <!-- Invoice Information -->
            <div class="info-section">
                <h3>Informasi Invoice</h3>
                <div class="info-row">
                    <div class="info-label">No. Invoice:</div>
                    <div class="info-value"><?php echo $order['kode_pesanan']; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal Pesan:</div>
                    <div class="info-value"><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal Layanan:</div>
                    <div class="info-value"><?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jam Layanan:</div>
                    <div class="info-value"><?php echo date('H:i', strtotime($order['jam_pesanan'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value" style="font-weight: bold; color: <?php 
                        switch($order['status']) {
                            case 'selesai': echo '#28a745'; break;
                            case 'dibatalkan': echo '#dc3545'; break;
                            default: echo '#ffc107';
                        }
                    ?>;">
                        <?php echo ucfirst($order['status']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Details -->
        <div class="table-container">
            <h3 style="color: #d81b60; margin-bottom: 15px;">Detail Layanan</h3>
            <table>
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th>Durasi</th>
                        <th>Harga Satuan</th>
                        <th>Jumlah</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($order['nama_layanan']); ?></strong><br>
                            <small><?php echo htmlspecialchars($order['deskripsi']); ?></small>
                        </td>
                        <td><?php echo $order['durasi']; ?> menit</td>
                        <td><?php echo formatRupiah($order['harga']); ?></td>
                        <td>1</td>
                        <td><strong><?php echo formatRupiah($order['total_harga']); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row">
                    <div style="margin-bottom: 10px;">
                        <span class="total-label">Subtotal:</span>
                        <span class="total-value"><?php echo formatRupiah($order['total_harga']); ?></span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span class="total-label">Pajak (0%):</span>
                        <span class="total-value">Rp 0</span>
                    </div>
                    <div style="margin-bottom: 10px; padding-top: 10px; border-top: 2px solid #eee;">
                        <span class="total-label">Total Pembayaran:</span>
                        <span class="total-value" style="font-size: 22px; color: #d81b60;">
                            <?php echo formatRupiah($order['total_harga']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Catatan -->
        <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h4 style="color: #d81b60; margin-top: 0;">Catatan:</h4>
            <p><strong>Catatan Pelanggan:</strong> <?php echo nl2br(htmlspecialchars($order['catatan'] ?: 'Tidak ada catatan')); ?></p>
            <p><strong>Catatan Admin:</strong> <?php echo nl2br(htmlspecialchars($order['catatan_admin'] ?: 'Tidak ada catatan')); ?></p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Terima kasih atas kepercayaan Anda!</strong></p>
            <p>Invoice ini sah dan dapat digunakan sebagai bukti transaksi.</p>
            <p>Silakan hubungi kami jika ada pertanyaan tentang invoice ini.</p>
            <p style="margin-top: 30px;">
                <strong>Dicetak pada:</strong> <?php echo date('d/m/Y H:i'); ?>
            </p>
        </div>
    </div>
    
    <!-- Action Buttons (Not printed) -->
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak Invoice
        </button>
        <a href="<?php echo BASE_URL; ?>/admin/pesanan/" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
        </a>
    </div>
    
    <script>
        // Auto print when page loads (optional)
        window.onload = function() {
            // Uncomment line below to auto-print
            // window.print();
        };
        
        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .invoice-container, .invoice-container * {
                    visibility: visible;
                }
                .invoice-container {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    box-shadow: none;
                    padding: 0;
                }
                .action-buttons {
                    display: none;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>