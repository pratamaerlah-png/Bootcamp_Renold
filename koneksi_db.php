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

/**
 * Mencatat aktivitas user ke dalam database.
 * @param mysqli $conn Objek koneksi database.
 * @param string $action Deskripsi aktivitas yang dilakukan.
 */
function log_activity($conn, $action) {
    // Pastikan session sudah dimulai
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $username = 'System/Guest';

    if ($user_id) {
        $stmt_user = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $username = $stmt_user->get_result()->fetch_assoc()['username'] ?? 'User ID: ' . $user_id;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $username, $action, $ip_address);
    $stmt->execute();
}