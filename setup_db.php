<?php
// /Volumes/Data Project/Penawaran website Event Lari/Bootcamp_Renold/setup_db.php

include 'koneksi_db.php';

echo "<h1>Pratama Digitect - Database Setup</h1>";
echo "<hr>";

// 1. Buat Tabel Users (Admin/EO)
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'eo', 'staff') DEFAULT 'eo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'users' (Admin)");

// 2. Buat Tabel Events
$sql = "CREATE TABLE IF NOT EXISTS events (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE,
    event_link VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(255),
    category ENUM('lari', 'konser') NOT NULL,
    banner_image VARCHAR(255),
    status ENUM('upcoming', 'ongoing', 'completed', 'draft') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'events' (Acara)");

// 3. Buat Tabel Tiket Kategori
$sql = "CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT(11) UNSIGNED,
    name VARCHAR(100) NOT NULL, -- Misal: Presale 1, VIP, 10K Run
    price DECIMAL(15, 2) NOT NULL,
    quota INT(11) NOT NULL,
    sold INT(11) DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
)";
exec_sql($conn, $sql, "Tabel 'ticket_categories' (Tiket)");

// 4. Buat Tabel Peserta (Participants)
$sql = "CREATE TABLE IF NOT EXISTS participants (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT(11) UNSIGNED,
    ticket_id INT(11) UNSIGNED,
    order_id VARCHAR(50) NOT NULL, -- Referensi ke Payment Gateway
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    bib_number VARCHAR(20) NULL, -- Khusus Lari
    seat_number VARCHAR(20) NULL, -- Khusus Konser
    qr_code_string VARCHAR(255) UNIQUE,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    checkin_status TINYINT(1) DEFAULT 0,
    checkin_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES ticket_categories(id)
)";
exec_sql($conn, $sql, "Tabel 'participants' (Peserta)");

// 5. Buat Tabel Pengaturan (Site Settings)
$sql = "CREATE TABLE IF NOT EXISTS site_settings (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'site_settings' (Pengaturan)");

// 6. Buat Tabel Testimoni
$sql = "CREATE TABLE IF NOT EXISTS testimonials (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    photo_url VARCHAR(255),
    sort_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'testimonials' (Testimoni)");

// 7. Buat Tabel Statistik Pengunjung
$sql = "CREATE TABLE IF NOT EXISTS visitor_stats (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'visitor_stats' (Pengunjung)");

// 8. Buat Tabel Invoices
$sql = "CREATE TABLE IF NOT EXISTS invoices (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    client_name VARCHAR(100) NOT NULL,
    client_email VARCHAR(100) NOT NULL,
    client_phone VARCHAR(20),
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    status ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid',
    items_json TEXT, -- Simpan item dalam JSON sederhana
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
exec_sql($conn, $sql, "Tabel 'invoices' (Tagihan)");

echo "<hr>";
echo "<h3>Mengisi Data Dummy (Sampel)...</h3>";

// Insert Admin Default
$pass = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, role) 
        SELECT * FROM (SELECT 'admin', 'admin@pratama.com', '$pass', 'admin') AS tmp
        WHERE NOT EXISTS (
            SELECT username FROM users WHERE username = 'admin'
        ) LIMIT 1";
exec_sql($conn, $sql, "User Admin Default (Pass: admin123)");

// Insert Event Lari (Sesuai HTML)
$sql = "INSERT INTO events (slug, title, description, event_date, location, category, status, banner_image)
        VALUES 
        ('jakarta-marathon-2024', 'Jakarta City Marathon', 'Lari marathon terbesar di Jakarta.', '2024-11-12 06:00:00', 'Monas, Jakarta', 'lari', 'upcoming', 'run_banner.jpg'),
        ('merdeka-run-10k', 'Merdeka Run 10K', 'Lari memperingati kemerdekaan.', '2024-08-20 06:00:00', 'Bandung, Jawa Barat', 'lari', 'completed', 'merdeka_banner.jpg')
        ON DUPLICATE KEY UPDATE title=title";
exec_sql($conn, $sql, "Data Event Lari");

// Insert Event Konser (Sesuai HTML)
$sql = "INSERT INTO events (slug, title, description, event_date, location, category, status, banner_image)
        VALUES 
        ('neon-music-fest', 'Neon Music Fest', 'Festival musik elektronik terbesar.', '2024-12-15 19:00:00', 'GBK, Jakarta', 'konser', 'upcoming', 'neon_banner.jpg'),
        ('rock-nation-tour', 'Rock Nation Tour', 'Konser rock legendaris.', '2024-10-10 19:00:00', 'JCC Senayan', 'konser', 'completed', 'rock_banner.jpg')
        ON DUPLICATE KEY UPDATE title=title";
exec_sql($conn, $sql, "Data Event Konser");

// Insert Kategori Tiket untuk Jakarta Marathon (ID 1 asumsi auto increment reset/baru)
// Kita pakai subquery untuk mencari ID agar aman
$sql = "INSERT INTO ticket_categories (event_id, name, price, quota)
        SELECT id, 'Early Bird 10K', 150000, 500 FROM events WHERE slug = 'jakarta-marathon-2024'
        UNION ALL
        SELECT id, 'Regular 10K', 250000, 1000 FROM events WHERE slug = 'jakarta-marathon-2024'";
exec_sql($conn, $sql, "Tiket Jakarta Marathon");

// Insert Dummy Testimonial
$sql = "INSERT INTO testimonials (name, role, content, photo_url)
        SELECT * FROM (SELECT 'Sarah Wijaya', 'Promotor SoundFest', 'Sistem ticketing dan gate management dari Pratama Digitect sangat stabil menangani ribuan pengunjung festival kami.', '') AS tmp
        WHERE NOT EXISTS (SELECT id FROM testimonials LIMIT 1)";
exec_sql($conn, $sql, "Data Dummy Testimoni");

echo "<hr>";
echo "<h2 style='color:blue'>SETUP SELESAI!</h2>";
echo "<p>Silakan hapus file <code>setup_db.php</code> jika sudah di production agar tidak direset orang lain.</p>";

// Helper Function
function exec_sql($conn, $sql, $msg) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>[OK] $msg berhasil.</p>";
    } else {
        echo "<p style='color:red'>[FAIL] $msg: " . $conn->error . "</p>";
    }
}
?>
