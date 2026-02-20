<?php
// pelanggan/pesanan/print.php
require_once '../../config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(BASE_URL . '/login.php');
}

require_once '../../database.php';
$db = new Database();
$conn = $db->getConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    redirect(BASE_URL . '/pelanggan/pesanan/');
}

$user_id = $_SESSION['user_id'];

// Get order details
$query = "SELECT p.*, l.nama_layanan, l.deskripsi, l.harga, l.durasi,
                 u.nama_lengkap, u.email, u.no_telepon, u.alamat
          FROM pesanan p
          JOIN layanan l ON p.layanan_id = l.id
          JOIN users u ON p.user_id = u.id
          WHERE p.id = :id AND p.user_id = :user_id AND p.status = 'selesai'";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect(BASE_URL . '/pelanggan/pesanan/');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['kode_pesanan']; ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            
            body {
                margin: 1.6cm;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #ffb6c1;
            border-radius: 10px;
            padding: 30px;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ffb6c1;
        }
        
        .logo-section h1 {
            color: #c2185b;
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        
        .logo-section p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        
        .invoice-info h2 {
            color: #c2185b;
            margin: 0 0 10px 0;
            text-align: right;
            font-size: 20px;
        }
        
        .invoice-details {
            text-align: right;
            font-size: 14px;
            color: #666;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section h3 {
            color: #c2185b;
            margin: 0 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #ffe6e6;
            font-size: 16px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            flex: 0 0 120px;
            color: #c2185b;
            font-weight: 500;
            font-size: 14px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-size: 14px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .invoice-table th {
            background: #ffe6e6;
            color: #c2185b;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            border-bottom: 2px solid #ffb6c1;
        }
        
        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-table tr:last-child td {
            border-bottom: 2px solid #ffb6c1;
        }
        
        .total-section {
            text-align: right;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .total-label {
            width: 150px;
            text-align: right;
            padding-right: 20px;
            color: #666;
        }
        
        .total-value {
            width: 150px;
            text-align: right;
            color: #333;
        }
        
        .grand-total {
            font-size: 18px;
            font-weight: 700;
            color: #c2185b;
            border-top: 2px solid #ffb6c1;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ffb6c1;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-selesai {
            background: #d4edda;
            color: #155724;
        }
        
        .print-actions {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .btn-print {
            background: #c2185b;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-print:hover {
            background: #ffb6c1;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 15px;
        }
        
        .print-only {
            display: none;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(255, 182, 193, 0.1);
            z-index: -1;
            white-space: nowrap;
            font-weight: bold;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">LUNELLE BEAUTY</div>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <h1>Lunelle Beauty</h1>
                <p>Jl. Beauty No. 123, Cirebon</p>
                <p>Telepon: (021) 1234-5678</p>
                <p>Email: info@lunellebeauty.com</p>
            </div>
            
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <div class="invoice-details">
                    <div><strong>No. Invoice:</strong> <?php echo $order['kode_pesanan']; ?></div>
                    <div><strong>Tanggal Invoice:</strong> <?php echo formatDate($order['created_at'], 'd/m/Y'); ?></div>
                    <div><strong>Status:</strong> 
                        <span class="status-badge status-selesai">SELESAI</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Customer Information -->
            <div class="section">
                <h3>INFORMASI PELANGGAN</h3>
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
            
            <!-- Service Information -->
            <div class="section">
                <h3>INFORMASI LAYANAN</h3>
                <div class="info-row">
                    <div class="info-label">Layanan:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['nama_layanan']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Deskripsi:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['deskripsi']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Durasi:</div>
                    <div class="info-value"><?php echo $order['durasi']; ?> menit</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal Layanan:</div>
                    <div class="info-value"><?php echo formatDate($order['tanggal_pesanan'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jam Layanan:</div>
                    <div class="info-value"><?php echo date('H:i', strtotime($order['jam_pesanan'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Invoice Table -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>DESKRIPSI</th>
                    <th>QTY</th>
                    <th>HARGA SATUAN</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($order['nama_layanan']); ?></td>
                    <td>1</td>
                    <td><?php echo formatRupiah($order['harga']); ?></td>
                    <td><?php echo formatRupiah($order['harga']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value"><?php echo formatRupiah($order['harga']); ?></div>
            </div>
            <div class="total-row">
                <div class="total-label">Pajak (0%):</div>
                <div class="total-value">Rp 0</div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">TOTAL:</div>
                <div class="total-value"><?php echo formatRupiah($order['total_harga']); ?></div>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="section">
            <h3>INFORMASI PEMBAYARAN</h3>
            <div class="info-row">
                <div class="info-label">Metode Pembayaran:</div>
                <div class="info-value">Tunai di Tempat</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status Pembayaran:</div>
                <div class="info-value">LUNAS</div>
            </div>
            <div class="info-row">
                <div class="info-label">Catatan:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($order['catatan'] ?: 'Tidak ada catatan')); ?></div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Terima kasih telah menggunakan layanan Lunelle Beauty</strong></p>
            <p>Invoice ini sah dan dapat digunakan sebagai bukti pembayaran</p>
            <p class="print-only">Dicetak pada: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>
    
    <!-- Print Actions -->
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak Invoice
        </button>
        <a href="<?php echo BASE_URL; ?>/pelanggan/pesanan/detail.php?id=<?php echo $id; ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Detail
        </a>
    </div>
    
    <script>
        // Auto print if print parameter exists
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print')) {
            window.print();
        }
        
        // Show print message
        window.onafterprint = function() {
            alert('Invoice berhasil dicetak.');
        };
    </script>
</body>
</html>