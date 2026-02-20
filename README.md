# Lunelle Beauty - Sistem Manajemen Layanan Kecantikan

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![License](https://img.shields.io/badge/license-MIT-green)

Sistem manajemen layanan kecantikan berbasis website untuk salon Lunelle Beauty. Aplikasi ini memungkinkan pelanggan melakukan pemesanan layanan secara online dan administrator mengelola data layanan, pelanggan, serta pemesanan.

Daftar Isi
- [Fitur Utama](#fitur-utama)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Struktur Database](#struktur-database)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi](#konfigurasi)
- [Cara Penggunaan](#cara-penggunaan)
- [Struktur Folder](#struktur-folder)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

**Fitur Utama**
Untuk Pelanggan
- **Registrasi & Login** – Pendaftaran akun baru dan autentikasi pengguna
- **Lihat Layanan** – Menampilkan semua layanan aktif dalam bentuk kartu
- **Pemesanan Online** – Memilih layanan, tanggal, dan jam yang tersedia
- **Riwayat Pesanan** – Melihat daftar semua pemesanan yang pernah dilakukan
- **Detail Pesanan** – Informasi lengkap pesanan dengan timeline status
- **Pembatalan Pesanan** – Membatalkan pesanan yang masih berstatus menunggu
- **Cetak Invoice** – Mencetak bukti pemesanan untuk pesanan selesai
- **Manajemen Profil** – Mengubah data pribadi dan password

Untuk Administrator
- **Dashboard Statistik** – Ringkasan jumlah pelanggan, layanan, pemesanan, dan pendapatan
- **Manajemen Layanan** – Tambah, edit, hapus, dan ubah status layanan
- **Manajemen Pelanggan** – Melihat daftar dan detail pelanggan
- **Manajemen Pemesanan** – Melihat semua pesanan, mengubah status, menambah catatan
- **Backup Database** – Membuat cadangan database dan mengunduh file backup
- **Manajemen Profil** – Mengubah data pribadi dan password

 Teknologi yang Digunakan
- **Backend**: PHP 7.4+ (Prosedural dengan PDO)
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Native)
- **Library**: Font Awesome 6.4.0
- **Server**: Apache (XAMPP / LAMP / MAMP)

Struktur Database
Sistem menggunakan 3 tabel utama:

Tabel `users`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | INT(11) | Primary Key |
| nama_lengkap | VARCHAR(100) | Nama lengkap |
| email | VARCHAR(100) | Email (unique) |
| password | VARCHAR(255) | Password terenkripsi |
| no_telepon | VARCHAR(15) | Nomor telepon |
| alamat | TEXT | Alamat |
| foto | VARCHAR(255) | Foto profil |
| role | ENUM | admin/pelanggan |
| status | ENUM | aktif/nonaktif |
| created_at | DATETIME | Waktu daftar |
| updated_at | DATETIME | Waktu update |
| last_login | DATETIME | Login terakhir |

Tabel `layanan`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | INT(11) | Primary Key |
| nama_layanan | VARCHAR(100) | Nama layanan |
| deskripsi | TEXT | Deskripsi |
| harga | DECIMAL(10,2) | Harga |
| durasi | INT(11) | Durasi (menit) |
| foto | VARCHAR(255) | Foto layanan |
| status | ENUM | aktif/nonaktif |
| created_at | DATETIME | Waktu tambah |
| updated_at | DATETIME | Waktu update |

Tabel `pesanan`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | INT(11) | Primary Key |
| kode_pesanan | VARCHAR(50) | Kode unik |
| user_id | INT(11) | Foreign Key (users) |
| layanan_id | INT(11) | Foreign Key (layanan) |
| tanggal_pesanan | DATE | Tanggal layanan |
| jam_pesanan | TIME | Jam layanan |
| catatan | TEXT | Catatan pelanggan |
| catatan_admin | TEXT | Catatan admin |
| total_harga | DECIMAL(10,2) | Total harga |
| status | ENUM | menunggu/dikonfirmasi/diproses/selesai/dibatalkan |
| created_at | DATETIME | Waktu pesan |
| updated_at | DATETIME | Waktu update |

Persyaratan Sistem
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web Server (Apache / Nginx)
- Ekstensi PHP: PDO, MySQLi, GD (untuk upload gambar)
- Browser modern (Chrome, Firefox, Edge, Safari)
