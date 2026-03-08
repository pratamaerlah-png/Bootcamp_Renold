<?php
/**
 * koneksi_db.php
 * File ini berfungsi untuk membuat koneksi ke database MySQL.
 */

// --- KEAMANAN: CEK AKSES LANGSUNG ---
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Akses langsung ke file ini ditolak.");
}

// --- PENGATURAN KONEKSI DATABASE ---
$db_host = 'localhost';
$db_user = 'pray7787_landingpage_user';
$db_pass = '0PwQo2lk06eMdg1R';
$db_name = 'pray7787_Landing_Page';

// ==========================================================
// === TAMBAHAN KRUSIAL: AKTIFKAN LAPORAN ERROR SEBAGAI EXCEPTION ===
// ==========================================================
// Baris ini memberitahu MySQLi untuk melempar Exception jika ada query yang gagal.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- MEMBUAT KONEKSI MENGGUNAKAN MySQLi ---
try {
    // Menggunakan variabel $conn agar kompatibel dengan file lain (index.php, admin/index.php)
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Mengatur charset ke utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    // Tulis log error jika ada, jangan tampilkan ke pengguna
    file_put_contents(__DIR__ . '/db_connection_log.txt', date('[Y-m-d H:i:s] ') . 'Koneksi Gagal: ' . $e->getMessage() . "\n", FILE_APPEND);
    // Hentikan eksekusi skrip secara diam-diam untuk keamanan
    die("Koneksi Database Bermasalah. Silakan hubungi administrator.");
}