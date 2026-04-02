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
    $settings_to_save = [
        'partners_data' => $_POST['partners_data'] ?? '',
        'wa_token' => $_POST['wa_token'] ?? '',
        'social_links' => $_POST['social_links'] ?? '',
        'bank_name' => $_POST['bank_name'] ?? '',
        'bank_account' => $_POST['bank_account'] ?? '',
        'bank_owner' => $_POST['bank_owner'] ?? '',
        'wa_template' => $_POST['wa_template'] ?? ''
    ];

    foreach ($settings_to_save as $key => $val) {
        $val = $conn->real_escape_string($val);
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
    }
    
    log_activity($conn, "Memperbarui Pengaturan Website.");
    header("Location: settings.php?msg=saved");
    exit;
}

$settings = [];
$res = $conn->query("SELECT * FROM site_settings");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$partners_data = $settings['partners_data'] ?? '';
$wa_token = $settings['wa_token'] ?? '';
$social_links_raw = $settings['social_links'] ?? '';

$bank_name = $settings['bank_name'] ?? 'Bank Central Asia (BCA)';
$bank_account = $settings['bank_account'] ?? '7975591638';
$bank_owner = $settings['bank_owner'] ?? 'Rizka Ruhayani Kistanto';

$default_wa = "Halo *[CLIENT_NAME]*,\n\nBerikut adalah rincian tagihan Anda untuk invoice *#[INVOICE_NUMBER]*:\n\n[DETAILS]\n--------------------\n*Sisa Bayar: Rp [TOTAL_SISA]*\n\nMetode Pembayaran:\n[BANK_INFO]\n\nJatuh Tempo: *[DUE_DATE]*\n\nUntuk detail lengkap dan pembayaran, silakan akses link berikut:\n[LINK_INVOICE]\n\nTerima kasih.";
$wa_template = $settings['wa_template'] ?? $default_wa;
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
            <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Informasi Rekening Bank (Invoice)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Nama Bank</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($bank_name) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: Bank Central Asia (BCA)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Nomor Rekening</label>
                    <input type="text" name="bank_account" value="<?= htmlspecialchars($bank_account) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: 1234567890">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Atas Nama (A/N)</label>
                    <input type="text" name="bank_owner" value="<?= htmlspecialchars($bank_owner) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: John Doe">
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Template Pesan WhatsApp (Invoice)</h3>
            <label class="block text-sm font-medium text-gray-400 mb-2">Format pesan untuk notifikasi tagihan ke klien</label>
            <textarea name="wa_template" rows="12" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition font-mono text-sm"><?= htmlspecialchars($wa_template) ?></textarea>
            <p class="text-xs text-gray-500 mt-2">Variabel yang tersedia: <code class="text-blue-400">[CLIENT_NAME]</code>, <code class="text-blue-400">[INVOICE_NUMBER]</code>, <code class="text-blue-400">[DETAILS]</code>, <code class="text-blue-400">[TOTAL_SISA]</code>, <code class="text-blue-400">[BANK_INFO]</code>, <code class="text-blue-400">[DUE_DATE]</code>, <code class="text-blue-400">[LINK_INVOICE]</code></p>
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