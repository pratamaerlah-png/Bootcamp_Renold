<?php
ob_start(); // Buffer output agar header() bisa jalan meski ada HTML
require_once 'header.php'; // Load header (session, auth, layout atas)

// Handle Settings (Partners)
$partners_data = '';
$wa_token = '';
$social_links_raw = '';

// Pastikan tabel ada (Lazy migration jika user lupa jalankan setup_db)
$conn->query("CREATE TABLE IF NOT EXISTS site_settings (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(50) UNIQUE NOT NULL, setting_value TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $partners_data = $conn->real_escape_string($_POST['partners_data']);
    $wa_token = $conn->real_escape_string($_POST['wa_token']);
    $social_links_raw = $conn->real_escape_string($_POST['social_links']);

    $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES ('partners_data', '$partners_data') ON DUPLICATE KEY UPDATE setting_value='$partners_data'";
    $conn->query($sql);

    $sql_wa = "INSERT INTO site_settings (setting_key, setting_value) VALUES ('wa_token', '$wa_token') ON DUPLICATE KEY UPDATE setting_value='$wa_token'";
    $conn->query($sql_wa);

    $sql_social = "INSERT INTO site_settings (setting_key, setting_value) VALUES ('social_links', '$social_links_raw') ON DUPLICATE KEY UPDATE setting_value='$social_links_raw'";
    if ($conn->query($sql_social)) {
            log_activity($conn, "Memperbarui Pengaturan Website.");
            header("Location: settings.php?msg=saved");
            exit;
    }
}
$res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='partners_data'");
$partners_data = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['setting_value'] : '';

$res_wa = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='wa_token'");
$wa_token = ($res_wa && $res_wa->num_rows > 0) ? $res_wa->fetch_assoc()['setting_value'] : '';
$res_social = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='social_links'");
$social_links_raw = ($res_social && $res_social->num_rows > 0) ? $res_social->fetch_assoc()['setting_value'] : '';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Pengaturan Website</h1>
        <p class="text-gray-500 mt-1">Kelola konfigurasi umum website Anda.</p>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
    <div id="notification" class="mb-6 p-4 rounded-lg bg-green-900/30 border border-green-500/30 text-green-400 flex items-center gap-3 transition-opacity duration-500">
        <i class="fa-solid fa-circle-check text-xl"></i>
        <span>Pengaturan berhasil disimpan!</span>
    </div>
<?php endif; ?>

<!-- Form Settings -->
<div class="bg-gray-800 rounded-xl shadow-xl p-8 border border-gray-700 max-w-4xl mx-auto">
    <form method="POST" action="settings.php">
        <div class="mb-6">
            <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Partners & Sponsors</h3>
            <label class="block text-sm font-medium text-gray-400 mb-2">Daftar URL Logo (Satu link per baris)</label>
            <textarea name="partners_data" rows="10" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition font-mono text-sm" placeholder="https://contoh.com/logo1.png&#10;https://contoh.com/logo2.png"><?= htmlspecialchars($partners_data) ?></textarea>
            <p class="text-xs text-gray-500 mt-2">Masukkan Direct Link gambar (jpg/png/svg). Gambar akan otomatis ditampilkan di halaman depan.</p>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-400 mb-2">Token WhatsApp (Fonnte)</label>
            <input type="text" name="wa_token" value="<?= htmlspecialchars($wa_token) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Masukkan Token Fonnte Anda">
        </div>
        
        <div class="mb-6">
            <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Link Media Sosial (Dinamis)</h3>
            <label class="block text-sm font-medium text-gray-400 mb-2">Masukkan URL lengkap media sosial, satu link per baris.</label>
            <textarea name="social_links" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition font-mono text-sm" placeholder="https://instagram.com/username&#10;https://facebook.com/username&#10;https://x.com/username"><?= htmlspecialchars($social_links_raw) ?></textarea>
            <p class="text-xs text-gray-500 mt-2">Sistem akan otomatis mendeteksi ikon (Instagram, Facebook, X/Twitter, LinkedIn, YouTube, TikTok).</p>
        </div>
        
        <div class="flex justify-end pt-6 border-t border-gray-700">
            <button type="submit" class="px-8 py-3 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                <i class="fa-solid fa-save mr-2"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<script>
    // Hilangkan notifikasi otomatis setelah 3 detik
    const notification = document.getElementById('notification');
    if (notification) {
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500); // Hapus dari DOM setelah fade out
        }, 3000);
    }
</script>

            </div>
        </main>
    </div>
</body>
</html>