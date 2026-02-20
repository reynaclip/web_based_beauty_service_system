<?php
// lib/functions.php

/**
 * Fungsi backup database
 * @param string $backupName Nama file backup
 * @return array Status backup
 */
function backupDatabase($backupName = 'backup')
{
    // ===== KONFIGURASI DATABASE =====
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'db_kecantikan'; // GANTI sesuai database kamu

    // ===== FOLDER BACKUP =====
    $backupDir = __DIR__ . '/../backups';

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // ===== NAMA FILE BACKUP =====
    $filename = $backupName . '_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    // ===== COMMAND MYSQLDUMP =====
    $command = "\"C:\\xampp\\mysql\\bin\\mysqldump\" -h $dbHost -u $dbUser";

    if ($dbPass != '') {
        $command .= " -p$dbPass";
    }

    $command .= " $dbName > \"$filepath\"";

    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($filepath)) {
        return [
            'success' => true,
            'file' => $filename,
            'filename' => $filename,
            'message' => 'Database berhasil dibackup'
        ];
    }

    return [
        'success' => false,
        'message' => 'Backup gagal',
        'file' => null
    ];
}

/**
 * Format ukuran file
 * @param int $bytes Ukuran file dalam bytes
 * @return string Ukuran file yang sudah diformat
 */
function formatFileSize($bytes)
{
    if ($bytes <= 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Dapatkan daftar file backup
 * @return array Daftar file backup dengan informasi lengkap
 */
function getBackupFiles()
{
    $files = [];
    
    // ===== FOLDER BACKUP =====
    $backupDir = __DIR__ . '/../backups';
    
    if (is_dir($backupDir)) {
        $scan = scandir($backupDir, SCANDIR_SORT_DESCENDING);
        
        foreach ($scan as $file) {
            // Hanya file .sql
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filepath = $backupDir . '/' . $file;
                $files[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'size_formatted' => formatFileSize(filesize($filepath)),
                    'date' => date('d/m/Y H:i:s', filemtime($filepath)),
                    'timestamp' => filemtime($filepath)
                ];
            }
        }
    }
    
    return $files;
}

/**
 * Hapus file backup lama (opsional)
 * @param int $keep Jumlah file backup terbaru yang dipertahankan
 * @return array Hasil penghapusan
 */
function cleanupOldBackups($keep = 10)
{
    $files = getBackupFiles();
    $deleted = [];
    
    // Urutkan berdasarkan timestamp (terbaru ke terlama)
    usort($files, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Hapus file yang lebih dari $keep
    if (count($files) > $keep) {
        $toDelete = array_slice($files, $keep);
        $backupDir = __DIR__ . '/../backups';
        
        foreach ($toDelete as $file) {
            $filepath = $backupDir . '/' . $file['name'];
            if (unlink($filepath)) {
                $deleted[] = $file['name'];
            }
        }
    }
    
    return [
        'deleted' => $deleted,
        'count' => count($deleted),
        'remaining' => min(count($files), $keep)
    ];
}
?>