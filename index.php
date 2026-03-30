<?php
// --- DEBUGGING: NYALAKAN INI AGAR ERROR MUNCUL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Pastikan file ini disimpan sebagai index.php
require_once 'koneksi_db.php'; // Pastikan file ini ada. Jika nama file Anda 'db_connect.php', ganti nama filenya atau ubah baris ini.

// --- VISITOR TRACKING ---
// --- Fungsi untuk mendapatkan IP Asli Pengunjung (lebih akurat) ---
function get_real_ip() {
    // Cek header dari Cloudflare (paling prioritas jika ada)
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    // Cek header dari proxy lain
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip_array = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim(reset($ip_array)); // Ambil IP pertama jika ada beberapa
    }
    // Fallback ke metode standar
    return $_SERVER['REMOTE_ADDR'];
}

$visitor_ip = get_real_ip();
$today_date = date('Y-m-d');

// Cek apakah IP ini sudah berkunjung hari ini
$check_visit = $conn->query("SELECT id FROM visitor_stats WHERE ip_address = '$visitor_ip' AND visit_date = '$today_date'");
if ($check_visit->num_rows == 0) {
    // --- LAZY MIGRATION: Pastikan kolom city dan province ada ---
    // Cek apakah kolom 'city' sudah ada di tabel visitor_stats
    $check_col = $conn->query("SHOW COLUMNS FROM visitor_stats LIKE 'city'");
    if ($check_col->num_rows == 0) {
        // Jika belum ada, tambahkan kolom city dan province
        $conn->query("ALTER TABLE visitor_stats ADD COLUMN city VARCHAR(100) AFTER ip_address");
        $conn->query("ALTER TABLE visitor_stats ADD COLUMN province VARCHAR(100) AFTER city");
    }
    // -----------------------------------------------------------

    // Catat kunjungan baru tanpa lokasi. Lokasi akan diupdate oleh JavaScript.
    $stmt = $conn->prepare("INSERT INTO visitor_stats (visit_date, ip_address) VALUES (?, ?)");
    $stmt->bind_param("ss", $today_date, $visitor_ip);
    $stmt->execute();
}

// --- CONTACT FORM HANDLER ---
$contact_msg = '';
require_once 'notifikasi_helper.php'; // Panggil helper untuk fungsi kirimEmail

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_contact'])) {
    $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
    $email_pengirim = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $whatsapp = htmlspecialchars($_POST['whatsapp']);
    $nama_event = htmlspecialchars($_POST['nama_event']);

    $tujuan = "pratamaerlah@gmail.com";
    $namaPenerima = "Admin Pratama Digitect";
    $subjek = "Lead Baru dari Website: " . $nama_event;
    
    // Format nomor untuk link (hapus karakter non-angka)
    $wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp);
    
    $isiPesan = "
        <h2>Permintaan Konsultasi Baru</h2>
        <p>Ada calon klien baru yang tertarik dengan platform Anda.</p>
        <hr>
        <p><strong>Nama Lengkap:</strong> {$nama_lengkap}</p>
        <p><strong>Email:</strong> <a href='mailto:{$email_pengirim}'>{$email_pengirim}</a></p>
        <p><strong>No. WhatsApp:</strong> <a href='{$wa_link}'>{$whatsapp}</a> (Klik untuk Chat)</p>
        <p><strong>Nama Event (Rencana):</strong> {$nama_event}</p>
        <hr>
        <p>Mohon untuk segera di-follow up.</p>
    ";

    if (kirimEmail($tujuan, $namaPenerima, $subjek, $isiPesan)) {
        $contact_msg = 'success';
    } else {
        $contact_msg = 'failed';
    }
}

// --- AUTO UPDATE STATUS ---
// Jika waktu event sudah lewat dari sekarang, ubah status jadi 'completed'
$conn->query("UPDATE events SET status = 'completed' WHERE event_date < NOW() AND status = 'upcoming'");

// Ambil data Event Lari
$sql_lari = "SELECT * FROM events WHERE category='lari' AND status = 'upcoming' ORDER BY event_date ASC LIMIT 5";
$result_lari = $conn->query($sql_lari);

// Ambil data Event Konser
$sql_konser = "SELECT * FROM events WHERE category='konser' AND status = 'upcoming' ORDER BY event_date ASC LIMIT 5";
$result_konser = $conn->query($sql_konser);

// Ambil data Event Selesai (Semua Kategori)
$sql_selesai = "SELECT * FROM events WHERE status = 'completed' ORDER BY event_date DESC LIMIT 5";
$result_selesai = $conn->query($sql_selesai);

// Ambil Data Partners
$partners = [];
$check_table = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($check_table && $check_table->num_rows > 0) {
    $res_partners = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='partners_data'");
    if ($res_partners && $res_partners->num_rows > 0) {
        $partners_list = $res_partners->fetch_assoc()['setting_value'];
        $partners = array_filter(explode("\n", str_replace("\r", "", $partners_list)));
    }
}

// Ambil Data Social Media
$social_links_raw = '';
$res_social = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'social_links'");
if ($res_social) {
    $social_links_raw = $res_social->fetch_assoc()['setting_value'] ?? '';
}
$social_links = array_filter(explode("\n", str_replace("\r", "", $social_links_raw)));

// Ambil Data Testimoni (Semua)
$sql_testi = "SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC LIMIT 5"; // Ambil 5 teratas sesuai urutan
$result_testi = $conn->query($sql_testi);
$testimonials = [];
if ($result_testi && $result_testi->num_rows > 0) {
    while($row = $result_testi->fetch_assoc()) {
        $testimonials[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pratama Digitect - Custom Event Platform</title>
    
    <!-- SEO & Social Media Meta Tags -->
    <meta name="description" content="Bangun platform event lari & konser Anda sendiri (White Label). Fitur lengkap: Ticketing, Gate Management, hingga Custom Domain.">
    <meta name="keywords" content="Event Platform, White Label Ticketing, Sistem Event Lari, Konser Musik, Gate Management, Custom Domain Event">
    <meta name="author" content="Pratama Digitect Systems">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://pratamadigitect.com/">
    <meta property="og:title" content="Bangun Platform Event Sendiri">
    <meta property="og:description" content="Solusi White Label untuk Event Lari & Konser. Gunakan domain sendiri, kelola data peserta 100%, dan terima dana tiket langsung (Direct Settlement).">
    <meta property="og:image" content="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?q=80&w=1200&auto=format&fit=crop">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://pratamadigitect.com/">
    <meta property="twitter:title" content="Bangun Platform Event Sendiri">
    <meta property="twitter:description" content="Solusi White Label untuk Event Lari & Konser. Gunakan domain sendiri, kelola data peserta 100%, dan terima dana tiket langsung.">
    <meta property="twitter:image" content="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?q=80&w=1200&auto=format&fit=crop">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-black text-gray-300">

    <!-- Loading Screen -->
    <div id="loading-screen" class="fixed inset-0 z-[100] bg-black flex flex-col items-center justify-center transition-opacity duration-500">
        <div class="text-center">
            <!-- CSS Animated Running Man -->
            <div class="running-man mb-6">
                <div class="head"></div>
                <div class="body"></div>
                <div class="limb arm left"></div>
                <div class="limb arm right"></div>
                <div class="limb leg left"></div>
                <div class="limb leg right"></div>
                <div class="shadow"></div>
            </div>
            <!-- Loading Bar -->
            <div class="h-1 w-32 bg-gray-800 rounded-full mx-auto overflow-hidden relative">
                <div class="absolute inset-0 bg-blue-600 w-full -translate-x-full animate-slide"></div>
            </div>
            <p class="text-gray-500 font-mono text-sm mt-4 animate-pulse">Memuat Platform...</p>
        </div>
    </div>

    <!-- Navbar -->
    <nav id="navbar" class="fixed w-full z-50 transition-all duration-300 bg-transparent border-b border-transparent">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="#" class="text-2xl font-bold text-blue-600 flex items-center gap-2">
                <i class="fa-solid fa-flag-checkered"></i> Pratama Digitect
            </a>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-8 items-center">
                <a href="#events" class="text-gray-300 hover:text-blue-500 transition">Events</a>
                <a href="#fitur" class="text-gray-300 hover:text-blue-500 transition">Fitur</a>
                <a href="#harga" class="text-gray-300 hover:text-blue-500 transition">Harga</a>
                <a href="#faq" class="text-gray-300 hover:text-blue-500 transition">FAQ</a>
                <a href="#kontak" class="bg-blue-600 text-white px-5 py-2 rounded-full hover:bg-blue-700 transition font-medium">Hubungi Kami</a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden text-gray-600 focus:outline-none">
                <i class="fa-solid fa-bars text-2xl"></i>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden bg-black border-t border-white/10 px-6 py-4">
            <a href="#events" class="block py-2 text-gray-300 hover:text-blue-500">Events</a>
            <a href="#fitur" class="block py-2 text-gray-300 hover:text-blue-500">Fitur</a>
            <a href="#harga" class="block py-2 text-gray-300 hover:text-blue-500">Harga</a>
            <a href="#faq" class="block py-2 text-gray-300 hover:text-blue-500">FAQ</a>
            <a href="#kontak" class="block py-2 mt-2 text-blue-600 font-bold">Hubungi Kami</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative min-h-screen flex items-center text-white pt-24 overflow-hidden">
        <!-- Parallax Background Layer -->
        <div id="hero-bg" class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1492684223066-81342ee5ff30?q=80&w=2500&auto=format&fit=crop')] bg-cover bg-center z-0">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-black/80 to-blue-900/50"></div>
        </div>

        <div class="container mx-auto px-6 text-center relative z-10">
            <span class="inline-block py-1 px-3 rounded-full bg-blue-900/50 border border-blue-500/30 text-blue-300 text-sm font-semibold mb-6 backdrop-blur-sm">White Label Event Partner</span>
            <h1 class="text-5xl md:text-7xl font-extrabold mb-6 leading-tight tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-blue-100 to-gray-400">
                Your Event. Your Data.<br> <span class="text-blue-500">Your Own Platform.</span>
            </h1>
            <p class="text-xl md:text-2xl text-gray-300 mb-10 max-w-3xl mx-auto">
                Bangun ekosistem digital Anda sendiri tanpa coding. Solusi <strong>White Label</strong> lengkap untuk Event dengan <strong>Custom Domain</strong> dan kepemilikan data penuh.
            </p>
            <div class="flex flex-col md:flex-row justify-center gap-4">
                <a href="#demo" class="bg-white text-blue-900 px-8 py-4 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-lg">
                    Konsultasi Enterprise
                </a>
                <a href="#fitur" class="border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-blue-900 transition">
                    Eksplorasi Fitur
                </a>
            </div>
            
            <!-- Dashboard Preview Image Placeholder -->
            <div class="mt-20 mx-auto max-w-6xl bg-gray-900 rounded-xl shadow-2xl shadow-blue-500/10 overflow-hidden border border-white/10">
                <!-- Browser Window Header -->
                <div class="bg-gray-800 px-4 py-3 flex items-center gap-2 border-b border-gray-700">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <div class="ml-4 bg-gray-700 rounded-md px-3 py-1 text-xs text-gray-400 font-mono flex-1 text-left">dashboard.pratamadigitect.com</div>
                </div>
                <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80" alt="Dashboard Preview" class="w-full opacity-90">
            </div>
        </div>
    </header>

    <!-- Partners Section -->
    <section class="py-16 bg-black">
        <div class="container mx-auto px-6 reveal reveal-up">
            <p class="text-center text-gray-500 text-sm font-bold tracking-widest uppercase mb-10 reveal reveal-up">Powering Top Tier Events & Organizers</p>
            
            <!-- Marquee Animation -->
            <div class="marquee-container w-full overflow-hidden relative">
                <div class="marquee-content whitespace-nowrap">
                    <?php if (!empty($partners)): ?>
                        <?php for ($i = 0; $i < 2; $i++): // Duplicate loop for seamless marquee ?>
                            <?php foreach ($partners as $url): 
                                $partner_url = trim($url);
                                // Add protocol if missing to prevent loading issues
                                if (!empty($partner_url) && !preg_match("~^(?:f|ht)tps?://~i", $partner_url)) {
                                    $partner_url = "https://" . ltrim($partner_url, '/');
                                }
                            ?>
                                <img src="<?= htmlspecialchars($partner_url) ?>" class="h-12 mx-8 inline-block grayscale opacity-50 hover:grayscale-0 hover:opacity-100 transition duration-300 object-contain" alt="Partner Logo">
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    <?php else: ?>
                        <!-- Default Fallback jika belum ada data -->
                        <?php for ($i = 0; $i < 2; $i++): ?>
                            <span class="inline-block mx-8 text-2xl font-bold text-gray-500"><i class="fa-solid fa-music"></i> SoundFest ID</span>
                            <span class="inline-block mx-8 text-2xl font-bold text-gray-500"><i class="fa-solid fa-person-running"></i> City Marathon</span>
                            <span class="inline-block mx-8 text-2xl font-bold text-gray-500"><i class="fa-solid fa-ticket"></i> TicketMaster Partner</span>
                            <span class="inline-block mx-8 text-2xl font-bold text-gray-500"><i class="fa-solid fa-guitar"></i> Rock Nation</span>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Event Showcase Section -->
    <section id="events" class="py-20 bg-black">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white mb-4 reveal reveal-up">Success Stories</h2>
                <p class="text-gray-400 reveal reveal-up delay-100">Event-event besar yang sukses menggunakan White Label kami.</p>
                
                <!-- Tabs -->
                <div class="flex justify-center mt-8">
                    <div class="bg-gray-900 p-1 rounded-lg inline-flex border border-white/10">
                        <button onclick="switchTab('lari')" id="tab-lari" class="px-6 py-2 rounded-md text-sm font-bold bg-blue-600 text-white shadow-sm transition-all">Event Lari</button>
                        <button onclick="switchTab('konser')" id="tab-konser" class="px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all">Event Konser</button>
                        <button onclick="switchTab('selesai')" id="tab-selesai" class="px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all">Selesai</button>
                    </div>
                </div>
            </div>

            <!-- Content: Event Lari -->
            <div id="content-lari" class="flex flex-wrap justify-center gap-2 md:gap-4 transition-opacity duration-300">
                <?php if ($result_lari->num_rows > 0): ?>
                    <?php while($row = $result_lari->fetch_assoc()): 
                        $status_label = ($row['status'] == 'upcoming') ? 'Open Registration' : (($row['status'] == 'completed') ? 'Selesai' : 'Coming Soon');
                        $badge_color = ($row['status'] == 'upcoming') ? 'bg-green-500' : (($row['status'] == 'completed') ? 'bg-gray-500' : 'bg-yellow-500 text-black');
                        
                        $link_href = !empty($row['event_link']) ? $row['event_link'] : "detail.php?slug=" . $row['slug'];
                        $link_target = !empty($row['event_link']) ? "_blank" : "_self";

                        // Logika Gambar: Prioritas Banner Manual -> Screenshot Website -> Placeholder
                        $display_image = '';
                        // Cek dulu apakah banner_image adalah URL gambar valid
                        if (!empty($row['banner_image'])) {
                            // Hanya gunakan jika URL berakhiran ekstensi gambar
                            if (preg_match('/\.((jpg|jpeg|png|gif|webp|svg)(\?.*)?)$/i', $row['banner_image'])) {
                                $banner_url = $row['banner_image'];
                                if (!preg_match("~^(?:f|ht)tps?://~i", $banner_url)) {
                                    $banner_url = "https://" . ltrim($banner_url, '/');
                                }
                                $display_image = $banner_url;
                            }
                        }
                        // Jika banner_image tidak valid atau kosong, baru gunakan event_link untuk screenshot
                        if (empty($display_image) && !empty($row['event_link'])) {
                            // Pastikan URL memiliki protokol (http/https) agar mShots berhasil
                            $url_target = $row['event_link'];
                            if (!preg_match("~^(?:f|ht)tps?://~i", $url_target)) {
                                $url_target = "https://" . $url_target;
                            }
                            // Menggunakan layanan mShots untuk generate screenshot otomatis
                            $display_image = "https://s0.wp.com/mshots/v1/" . urlencode($url_target) . "?w=800&h=600";
                        }
                        // Final fallback jika semua gagal
                        if (empty($display_image)) {
                            $display_image = "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60";
                        }

                        // Logika Lebar Kartu Dinamis
                        $card_width_class = ($result_lari->num_rows < 5) ? 'lg:flex-1 lg:max-w-[30%]' : 'lg:w-[18%]';
                    ?>
                    <div class="group bg-gray-900 rounded-lg shadow-lg hover:shadow-blue-500/20 transition-shadow duration-300 border border-white/10 overflow-hidden reveal reveal-up w-[31%] md:w-[23%] <?= $card_width_class ?>">
                        <div class="relative h-24 md:h-48 overflow-hidden">
                            <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full h-full">
                                <img src="<?= htmlspecialchars($display_image) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60';">
                                <div class="absolute top-2 right-2 <?= $badge_color ?> text-white text-[8px] md:text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $status_label ?></div>
                            </a>
                        </div>
                        <div class="p-2 md:p-3">
                            <div class="text-[10px] md:text-xs text-blue-500 font-semibold mb-1"><?= date('d M Y', strtotime($row['event_date'])) ?></div>
                            <h3 class="text-xs md:text-sm font-bold mb-1 text-white truncate" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></h3>
                            <p class="text-gray-400 text-[10px] md:text-xs mb-2 truncate"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($row['location']) ?></p>
                            <?php if($row['status'] == 'upcoming'): ?>
                                <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full text-center bg-blue-600 text-white font-bold text-[10px] md:text-xs py-1.5 rounded hover:bg-blue-700 transition">Daftar</a>
                            <?php else: ?>
                                <a href="#" class="block w-full text-center border border-gray-600 text-gray-300 font-bold text-[10px] md:text-xs py-1.5 rounded hover:bg-gray-800 transition">Detail</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-3 lg:col-span-5 text-center py-12 text-gray-500">
                        <i class="fa-solid fa-person-running text-4xl mb-4 opacity-50"></i>
                        <p>Belum ada event lari yang tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Content: Event Konser -->
            <div id="content-konser" class="hidden flex flex-wrap justify-center gap-2 md:gap-4 transition-opacity duration-300">
                <?php if ($result_konser->num_rows > 0): ?>
                    <?php while($row = $result_konser->fetch_assoc()): 
                        $status_label = ($row['status'] == 'upcoming') ? 'Open Registration' : (($row['status'] == 'completed') ? 'Selesai' : 'Coming Soon');
                        $badge_color = ($row['status'] == 'upcoming') ? 'bg-purple-500' : (($row['status'] == 'completed') ? 'bg-gray-500' : 'bg-yellow-500 text-black');

                        $link_href = !empty($row['event_link']) ? $row['event_link'] : "detail.php?slug=" . $row['slug'];
                        $link_target = !empty($row['event_link']) ? "_blank" : "_self";

                        // Logika Gambar (Sama untuk Konser)
                        $display_image = '';
                        // Cek dulu apakah banner_image adalah URL gambar valid
                        if (!empty($row['banner_image'])) {
                            // Hanya gunakan jika URL berakhiran ekstensi gambar
                            if (preg_match('/\.((jpg|jpeg|png|gif|webp|svg)(\?.*)?)$/i', $row['banner_image'])) {
                                $banner_url = $row['banner_image'];
                                if (!preg_match("~^(?:f|ht)tps?://~i", $banner_url)) {
                                    $banner_url = "https://" . ltrim($banner_url, '/');
                                }
                                $display_image = $banner_url;
                            }
                        }
                        // Jika banner_image tidak valid atau kosong, baru gunakan event_link untuk screenshot
                        if (empty($display_image) && !empty($row['event_link'])) {
                            $url_target = $row['event_link'];
                            if (!preg_match("~^(?:f|ht)tps?://~i", $url_target)) {
                                $url_target = "https://" . $url_target;
                            }
                            $display_image = "https://s0.wp.com/mshots/v1/" . urlencode($url_target) . "?w=800&h=600";
                        }
                        // Final fallback jika semua gagal
                        if (empty($display_image)) {
                            $display_image = "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60";
                        }

                        // Logika Lebar Kartu Dinamis
                        $card_width_class = ($result_konser->num_rows < 5) ? 'lg:flex-1 lg:max-w-[30%]' : 'lg:w-[18%]';
                    ?>
                    <div class="group bg-gray-900 rounded-lg shadow-lg hover:shadow-blue-500/20 transition-shadow duration-300 border border-white/10 overflow-hidden reveal reveal-up w-[31%] md:w-[23%] <?= $card_width_class ?>">
                    <div class="relative h-24 md:h-48 overflow-hidden">
                        <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full h-full">
                            <img src="<?= htmlspecialchars($display_image) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60';">
                            <div class="absolute top-2 right-2 <?= $badge_color ?> text-white text-[8px] md:text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $status_label ?></div>
                        </a>
                    </div>
                    <div class="p-2 md:p-3">
                        <div class="text-[10px] md:text-xs text-purple-500 font-semibold mb-1"><?= date('d M Y', strtotime($row['event_date'])) ?></div>
                        <h3 class="text-xs md:text-sm font-bold mb-1 text-white truncate" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></h3>
                        <p class="text-gray-400 text-[10px] md:text-xs mb-2 truncate"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($row['location']) ?></p>
                        <?php if($row['status'] == 'upcoming'): ?>
                            <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full text-center bg-purple-600 text-white font-bold text-[10px] md:text-xs py-1.5 rounded hover:bg-purple-700 transition">Daftar</a>
                        <?php else: ?>
                            <a href="#" class="block w-full text-center border border-gray-600 text-gray-300 font-bold text-[10px] md:text-xs py-1.5 rounded hover:bg-gray-800 transition">Detail</a>
                        <?php endif; ?>
                    </div>
                </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-3 lg:col-span-5 text-center py-12 text-gray-500">
                        <i class="fa-solid fa-music text-4xl mb-4 opacity-50"></i>
                        <p>Belum ada event konser yang tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Content: Event Selesai -->
            <div id="content-selesai" class="hidden flex flex-wrap justify-center gap-2 md:gap-4 transition-opacity duration-300">
                <?php if ($result_selesai->num_rows > 0): ?>
                    <?php while($row = $result_selesai->fetch_assoc()): 
                        $status_label = 'Selesai';
                        $badge_color = 'bg-gray-500';

                        $link_href = !empty($row['event_link']) ? $row['event_link'] : "detail.php?slug=" . $row['slug'];
                        $link_target = !empty($row['event_link']) ? "_blank" : "_self";

                        // Logika Gambar
                        $display_image = '';
                        // Cek dulu apakah banner_image adalah URL gambar valid
                        if (!empty($row['banner_image'])) {
                            // Hanya gunakan jika URL berakhiran ekstensi gambar
                            if (preg_match('/\.((jpg|jpeg|png|gif|webp|svg)(\?.*)?)$/i', $row['banner_image'])) {
                                $banner_url = $row['banner_image'];
                                if (!preg_match("~^(?:f|ht)tps?://~i", $banner_url)) {
                                    $banner_url = "https://" . ltrim($banner_url, '/');
                                }
                                $display_image = $banner_url;
                            }
                        }
                        // Jika banner_image tidak valid atau kosong, baru gunakan event_link untuk screenshot
                        if (empty($display_image) && !empty($row['event_link'])) {
                            $url_target = $row['event_link'];
                            if (!preg_match("~^(?:f|ht)tps?://~i", $url_target)) {
                                $url_target = "https://" . $url_target;
                            }
                            $display_image = "https://s0.wp.com/mshots/v1/" . urlencode($url_target) . "?w=800&h=600";
                        }
                        // Final fallback jika semua gagal
                        if (empty($display_image)) {
                            $display_image = "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60";
                        }

                        // Logika Lebar Kartu Dinamis
                        $card_width_class = ($result_selesai->num_rows < 5) ? 'lg:flex-1 lg:max-w-[30%]' : 'lg:w-[18%]';
                    ?>
                    <div class="group bg-gray-900 rounded-lg shadow-lg hover:shadow-blue-500/20 transition-shadow duration-300 border border-white/10 overflow-hidden reveal reveal-up w-[31%] md:w-[23%] <?= $card_width_class ?>">
                        <div class="relative h-24 md:h-48 overflow-hidden">
                            <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full h-full">
                                <img src="<?= htmlspecialchars($display_image) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition duration-500" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60';">
                                <div class="absolute top-2 right-2 <?= $badge_color ?> text-white text-[8px] md:text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $status_label ?></div>
                            </a>
                        </div>
                        <div class="p-2 md:p-3">
                            <div class="text-[10px] md:text-xs text-gray-500 font-semibold mb-1"><?= date('d M Y', strtotime($row['event_date'])) ?></div>
                            <h3 class="text-xs md:text-sm font-bold mb-1 text-white truncate" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></h3>
                            <p class="text-gray-400 text-[10px] md:text-xs mb-2 truncate"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($row['location']) ?></p>
                            <div class="block w-full text-center bg-gray-800 text-gray-500 font-bold text-[10px] md:text-xs py-1.5 rounded cursor-default">Selesai</div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-3 lg:col-span-5 text-center py-12 text-gray-500">
                        <i class="fa-solid fa-clock-rotate-left text-4xl mb-4 opacity-50"></i>
                        <p>Belum ada event yang selesai.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="events.php" class="inline-flex items-center text-blue-500 font-bold hover:text-blue-400 transition">Lihat Semua Event <i class="fa-solid fa-arrow-right ml-2"></i></a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="py-20 bg-gray-900">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4 reveal reveal-up">Mengapa Harus Custom Platform?</h2>
                <p class="text-gray-400 max-w-3xl mx-auto text-lg reveal reveal-up delay-100">Tinggalkan platform pihak ketiga yang membatasi brand Anda. Beralihlah ke ekosistem digital yang Anda miliki sepenuhnya.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                <!-- Card 1: Event & Tiket -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-blue-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group reveal reveal-left">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-900/20 rounded-bl-full -mr-4 -mt-4 transition group-hover:bg-blue-900/30"></div>
                    <div class="w-12 h-12 bg-blue-600 text-white rounded-lg flex items-center justify-center text-xl mb-6 relative z-10">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-white">Identitas Brand Eksklusif</h3>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-1"></i> <span>Gunakan <strong>Domain Sendiri</strong> (event.brand.com)</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-1"></i> <span>Desain UI/UX Sesuai Brand Guideline</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-1"></i> <span>Email Sender a.n. Event Anda</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-1"></i> <span>Bebas Iklan & Logo Vendor Lain</span></li>
                    </ul>
                </div>

                <!-- Card 2: CRM & Marketing -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-green-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group reveal reveal-up delay-100">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-green-900/20 rounded-bl-full -mr-4 -mt-4 transition group-hover:bg-green-900/30"></div>
                    <div class="w-12 h-12 bg-green-600 text-white rounded-lg flex items-center justify-center text-xl mb-6 relative z-10">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-white">Kepemilikan Data Penuh</h3>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> <span>100% Database Peserta Milik Anda</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> <span>Akses Data Real-time (Bukan H+1)</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> <span>Integrasi API ke CRM Perusahaan</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> <span>Privasi Data Terjamin (Enkripsi)</span></li>
                    </ul>
                </div>

                <!-- Card 3: Race Essentials -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-purple-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group reveal reveal-right">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-purple-900/20 rounded-bl-full -mr-4 -mt-4 transition group-hover:bg-purple-900/30"></div>
                    <div class="w-12 h-12 bg-purple-600 text-white rounded-lg flex items-center justify-center text-xl mb-6 relative z-10">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-white">Teknologi Event Canggih</h3>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-purple-500 mt-1"></i> <span><strong>Custom BIB</strong> (Request & Design)</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-purple-500 mt-1"></i> <span><strong>Check BIB</strong> (Static BI)</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-purple-500 mt-1"></i> <span><strong>AI Customer Service</strong> (24/7)</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-purple-500 mt-1"></i> <span><strong>WhatsApp Follow Up</strong> (Automated)</span></li>
                    </ul>
                </div>

                <!-- Card 4: Direct Settlement -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-yellow-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group reveal reveal-left">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-yellow-900/20 rounded-bl-full -mr-4 -mt-4 transition group-hover:bg-yellow-900/30"></div>
                    <div class="w-12 h-12 bg-yellow-600 text-white rounded-lg flex items-center justify-center text-xl mb-6 relative z-10">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-white">Direct Settlement (Dana Langsung)</h3>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-yellow-500 mt-1"></i> <span>Dana Tiket <strong>Langsung Masuk</strong> Rekening</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-yellow-500 mt-1"></i> <span>Tanpa Drama Dana Platform</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-yellow-500 mt-1"></i> <span>Cashflow Operasional Lebih Lancar</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-yellow-500 mt-1"></i> <span>Bebas Pilih Payment Gateway</span></li>
                    </ul>
                </div>

                <!-- Card 5: Infrastructure -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-red-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group lg:col-span-2 reveal reveal-right">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-red-900/20 rounded-bl-full -mr-4 -mt-4 transition group-hover:bg-red-900/30"></div>
                    <div class="w-12 h-12 bg-red-600 text-white rounded-lg flex items-center justify-center text-xl mb-6 relative z-10">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-white">Dedicated High-Performance Server</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <ul class="space-y-3 text-gray-400">
                            <li class="flex items-start gap-2"><i class="fa-solid fa-check text-red-500 mt-1"></i> <span>Server <strong>Dedicated</strong> (Tidak Sharing)</span></li>
                            <li class="flex items-start gap-2"><i class="fa-solid fa-check text-red-500 mt-1"></i> <span>Siap Menangani <strong>War Ticket</strong> Jutaan Trafik</span></li>
                        </ul>
                        <ul class="space-y-3 text-gray-400">
                            <li class="flex items-start gap-2"><i class="fa-solid fa-check text-red-500 mt-1"></i> <span>Auto-Scaling Infrastructure</span></li>
                            <li class="flex items-start gap-2"><i class="fa-solid fa-check text-red-500 mt-1"></i> <span>Jaminan Uptime 99.9% (SLA)</span></li>
                        </ul>
                    </div>
                </div>

                <!-- Card 4: Analitik & Laporan -->
                <div class="bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-orange-500/20 transition-shadow duration-300 border border-white/10 relative overflow-hidden group lg:col-span-3 reveal reveal-up">
                    <div class="flex flex-col md:flex-row items-center gap-8">
                        <div class="flex-1">
                            <div class="w-12 h-12 bg-orange-500 text-white rounded-lg flex items-center justify-center text-xl mb-6">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-4 text-white">Real-time Command Center Dashboard</h3>
                            <p class="text-gray-400 mb-4">Pantau performa event dari satu layar. Data penjualan tiket, traffic pengunjung, hingga check-in gate tersaji secara <strong>Live</strong>. Siap dipresentasikan kepada sponsor kapan saja.</p>
                            <div class="flex flex-wrap gap-3">
                                <span class="bg-orange-900/50 text-orange-300 px-3 py-1 rounded-full text-sm font-medium">Live Revenue</span>
                                <span class="bg-orange-900/50 text-orange-300 px-3 py-1 rounded-full text-sm font-medium">Heatmap Area</span>
                                <span class="bg-orange-900/50 text-orange-300 px-3 py-1 rounded-full text-sm font-medium">Demografi Peserta</span>
                            </div>
                        </div>
                        <div class="flex-1 w-full bg-gray-900 rounded-xl h-48 flex items-center justify-center border-2 border-dashed border-gray-700">
                            <span class="text-gray-500 font-medium"><i class="fa-solid fa-chart-line mr-2"></i>Preview Grafik Analitik</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="stats-section" class="bg-gradient-to-r from-blue-900/40 to-black text-white py-16 border-y border-white/10">
        <div class="container mx-auto px-6">
            <div class="flex flex-wrap justify-center gap-8 text-center">
                <div class="reveal reveal-zoom hidden w-1/2 md:w-1/5">
                    <div class="text-5xl font-bold mb-2 text-blue-400"><span class="counter" data-target="500">0</span>+</div>
                    <div class="text-gray-400">Event Terselenggara</div>
                </div>
                <div class="reveal reveal-zoom delay-100 w-1/2 md:w-1/5">
                    <div class="text-5xl font-bold mb-2 text-blue-400"><span class="counter" data-target="1000000">1.000.000</span>+</div>
                    <div class="text-gray-400">Peserta Terdaftar</div>
                </div>
                <div class="reveal reveal-zoom delay-200 w-1/2 md:w-1/5">
                    <div class="text-5xl font-bold mb-2 text-blue-400">99.9%</div>
                    <div class="text-gray-400">Uptime Server</div>
                </div>
                <div class="reveal reveal-zoom delay-100 w-1/2 md:w-1/5">
                    <div class="text-5xl font-bold mb-2 text-blue-400">24/7</div>
                    <div class="text-gray-400">Dukungan Teknis</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Calculator Section -->
    <section id="kalkulator" class="py-20 bg-gray-900 border-y border-white/10">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white mb-4 reveal reveal-up">Maksimalkan Profitabilitas & Aset Digital</h2>
                <p class="text-gray-400 reveal reveal-up delay-100">Hitung potensi penghematan biaya dan valuasi data peserta yang menjadi milik Anda sepenuhnya dengan Custom Platform.</p>
            </div>

            <div class="max-w-6xl mx-auto bg-black rounded-2xl p-8 md:p-12 border border-white/10 shadow-2xl reveal reveal-zoom">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <!-- Inputs -->
                    <div class="space-y-8">
                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-gray-400 font-bold text-sm">Jumlah Tiket Terjual</label>
                                <span class="text-blue-400 font-bold" id="display-qty">1.000</span>
                            </div>
                            <input type="range" id="range-qty" class="w-full h-2 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-blue-600" min="100" max="10000" step="100" value="1000">
                        </div>
                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-gray-400 font-bold text-sm">Harga Tiket Rata-rata (IDR)</label>
                                <span class="text-blue-400 font-bold" id="display-price">150.000</span>
                            </div>
                            <input type="range" id="range-price" class="w-full h-2 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-blue-600" min="50000" max="2000000" step="10000" value="150000">
                        </div>
                        
                        <!-- Fee Comparison Inputs -->
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-400 text-xs font-bold mb-2">Fee Platform Aggregator (%)</label>
                                <input type="number" id="fee-other" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white text-center focus:ring-2 focus:ring-red-500 outline-none" value="3" min="0" max="20" step="0.1">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-xs font-bold mb-2">Fee Custom Platform (%)</label>
                                <input type="number" id="fee-us" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white text-center focus:ring-2 focus:ring-green-500 outline-none" value="2" min="0" max="20" step="0.1">
                                <p class="text-[10px] text-green-400 mt-1">*Fleksibel sesuai kesepakatan</p>
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-bl-full -mr-8 -mt-8"></div>
                        
                        <div class="mb-6 pb-6 border-b border-gray-700">
                            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1">Estimasi Pendapatan Kotor</p>
                            <div class="text-3xl font-bold text-white" id="result-revenue">IDR 150.000.000</div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-green-400 text-xs uppercase tracking-wider font-bold">Selisih Keuntungan</p>
                                <span class="bg-green-500/20 text-green-400 text-[10px] px-2 py-0.5 rounded-full font-bold">SAVE MONEY</span>
                            </div>
                            <div class="text-4xl font-bold text-green-400" id="result-savings">IDR 7.500.000</div>
                            <p class="text-xs text-gray-500 mt-2">Namun keuntungan terbesar bukan di sini, melainkan pada <strong>Aset Brand</strong> di bawah ini.</p>
                        </div>

                        <!-- Data Valuation (ROI) -->
                        <div class="mt-8 pt-6 border-t border-gray-700">
                            <p class="text-gray-400 text-xs uppercase tracking-wider mb-2">Valuasi Aset Data Peserta (ROI)</p>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-[10px] text-gray-500">Nilai per Data (IDR)</label>
                                <input type="number" id="data-value" class="w-20 bg-gray-900 border border-gray-600 rounded px-2 py-1 text-right text-xs text-white focus:ring-1 focus:ring-blue-500 outline-none" value="50000">
                            </div>
                            <div class="bg-blue-900/20 rounded-lg p-3 border border-blue-500/30">
                                <div class="flex justify-between items-end">
                                    <div>
                                        <div class="text-[10px] text-blue-300 mb-1">Potensi Nilai Aset Data</div>
                                        <div class="text-xl font-bold text-blue-400" id="result-valuation">IDR 50.000.000</div>
                                    </div>
                                    <i class="fa-solid fa-database text-blue-500/50 text-2xl"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-2 italic">*Estimasi nilai data untuk remarketing & sponsorship masa depan.</p>
                        </div>
                    </div>
                </div>

                <!-- Value Comparison Table -->
                <div class="mt-12 border-t border-white/10 pt-8">
                    <div class="grid md:grid-cols-3 gap-6 text-center md:text-left">
                        <div class="bg-gray-900/50 p-4 rounded-lg border border-white/5">
                            <div class="text-gray-500 text-xs uppercase font-bold mb-1">Platform Aggregator (3%)</div>
                            <div class="text-red-400 font-bold"><i class="fa-solid fa-xmark mr-2"></i>Domain Milik Mereka</div>
                            <div class="text-red-400 font-bold"><i class="fa-solid fa-xmark mr-2"></i>Database Shared</div>
                        </div>
                        <div class="md:col-span-2 bg-blue-900/20 p-4 rounded-lg border border-blue-500/30 flex items-center justify-between">
                            <div>
                                <div class="text-blue-400 text-xs uppercase font-bold mb-1">Pratama Custom Platform (3%)</div>
                                <div class="text-white font-bold text-lg">Anda Mendapatkan: <span class="text-blue-400">Website Sendiri</span> + <span class="text-blue-400">Database Eksklusif</span> + <span class="text-blue-400">Brand Awareness</span></div>
                            </div>
                            <i class="fa-solid fa-check-circle text-3xl text-blue-500"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="harga" class="py-20 bg-black">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4 reveal reveal-up">Investment Plans</h2>
                <p class="text-gray-400 reveal reveal-up delay-100">Skalabilitas infrastruktur sesuai kebutuhan bisnis event Anda.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Starter -->
                <div class="border border-white/10 rounded-2xl p-8 hover:border-white/20 transition bg-gray-900 reveal reveal-up">
                    <h3 class="text-xl font-bold text-white mb-2">Community</h3>
                    <p class="text-gray-400 text-sm mb-6">Untuk Fun Run & Gigs Lokal</p>
                    <div class="text-4xl font-bold text-white mb-6">Starter</div>
                    <ul class="space-y-3 text-gray-400 mb-8">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> Max 500 Peserta</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> E-Sertifikat</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> Subdomain (event.pratama.com)</li>
                    </ul>
                    <a href="#kontak" class="block text-center border border-gray-600 text-gray-300 font-bold py-3 rounded-lg hover:bg-gray-800 transition">Hubungi Sales</a>
                </div>
                <!-- Pro -->
                <div class="border-2 border-blue-500 rounded-2xl p-8 shadow-2xl shadow-blue-500/30 relative transform md:-translate-y-4 bg-gray-900 reveal reveal-up delay-100">
                    <div class="absolute top-0 right-0 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-bl-lg rounded-tr-lg">MOST POPULAR</div>
                    <h3 class="text-xl font-bold text-white mb-2">Professional</h3>
                    <p class="text-gray-400 text-sm mb-6">Standard Event Nasional</p>
                    <div class="text-4xl font-bold text-white mb-6">Business</div>
                    <ul class="space-y-3 text-gray-400 mb-8">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-blue-400 mt-1"></i> <strong>Custom Domain</strong> (brand.com)</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-blue-400 mt-1"></i> Gate Access System (QR)</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-blue-400 mt-1"></i> WhatsApp Blast Integration</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-blue-400 mt-1"></i> Dedicated Server Resource</li>
                    </ul>
                    <a href="#kontak" class="block text-center bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-500 transition">Jadwalkan Demo</a>
                </div>
                <!-- Enterprise -->
                <div class="border border-white/10 rounded-2xl p-8 hover:border-white/20 transition bg-gray-900 reveal reveal-up delay-200">
                    <h3 class="text-xl font-bold text-white mb-2">Enterprise</h3>
                    <p class="text-gray-400 text-sm mb-6">Full Custom & High Traffic</p>
                    <div class="text-4xl font-bold text-white mb-6">Custom</div>
                    <ul class="space-y-3 text-gray-400 mb-8">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> <strong>Full White Label</strong> (No Branding)</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> Custom Feature Development</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> On-Site Technical Support</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-green-500 mt-1"></i> SLA Guarantee 99.9%</li>
                    </ul>
                    <a href="#kontak" class="block text-center border border-blue-500 text-blue-500 font-bold py-3 rounded-lg hover:bg-blue-500 hover:text-white transition">Hubungi Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-gray-900">
        <div class="container mx-auto px-6 max-w-4xl">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white mb-4 reveal reveal-up">Pertanyaan Umum (FAQ)</h2>
            </div>
            <div class="space-y-4">
                <details class="bg-gray-800 p-6 rounded-xl border border-white/10 group cursor-pointer reveal reveal-up">
                    <summary class="flex justify-between items-center font-medium text-lg text-white list-none">
                        <span>Apakah sistem ini mendukung pembayaran online?</span>
                        <span class="transition group-open:rotate-180"><i class="fa-solid fa-chevron-down"></i></span>
                    </summary>
                    <p class="text-gray-400 mt-4">Ya, Pratama Digitect terintegrasi dengan berbagai payment gateway populer di Indonesia (QRIS, Virtual Account, E-Wallet) untuk verifikasi otomatis.</p>
                </details>
                <details class="bg-gray-800 p-6 rounded-xl border border-white/10 group cursor-pointer reveal reveal-up delay-100">
                    <summary class="flex justify-between items-center font-medium text-lg text-white list-none">
                        <span>Apakah mendukung sistem Gate untuk Konser?</span>
                        <span class="transition group-open:rotate-180"><i class="fa-solid fa-chevron-down"></i></span>
                    </summary>
                    <p class="text-gray-400 mt-4">Ya, kami menyediakan aplikasi Gate Management yang bisa scan QR Code tiket peserta. Aplikasi ini bekerja real-time untuk mencegah tiket ganda dan memvalidasi akses masuk konser maupun race.</p>
                </details>
                <details class="bg-gray-800 p-6 rounded-xl border border-white/10 group cursor-pointer reveal reveal-up">
                    <summary class="flex justify-between items-center font-medium text-lg text-white list-none">
                        <span>Apakah bisa custom desain E-Sertifikat?</span>
                        <span class="transition group-open:rotate-180"><i class="fa-solid fa-chevron-down"></i></span>
                    </summary>
                    <p class="text-gray-400 mt-4">Tentu saja. Anda bisa mengunggah template desain sertifikat Anda sendiri, dan sistem kami akan otomatis menempatkan nama, waktu, dan kategori peserta.</p>
                </details>
                <details class="bg-gray-800 p-6 rounded-xl border border-white/10 group cursor-pointer reveal reveal-up delay-100">
                    <summary class="flex justify-between items-center font-medium text-lg text-white list-none">
                        <span>Apakah data peserta aman?</span>
                        <span class="transition group-open:rotate-180"><i class="fa-solid fa-chevron-down"></i></span>
                    </summary>
                    <p class="text-gray-400 mt-4">Keamanan data adalah prioritas kami. Kami menggunakan enkripsi standar industri dan backup berkala untuk memastikan data peserta dan transaksi Anda tetap aman.</p>
                </details>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="kontak" class="py-20 bg-gray-900">
        <div class="container mx-auto px-6">
            <div class="bg-gray-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row border border-white/10 reveal reveal-zoom">
                <div class="md:w-1/2 p-12 flex flex-col justify-center">
                    <h2 class="text-3xl font-bold mb-6 text-white">Ready to Build Your Platform?</h2>
                    <p class="text-gray-400 mb-8">Diskusikan kebutuhan teknis event Anda dengan tim engineer kami. Dapatkan blueprint sistem yang sesuai dengan skala bisnis Anda.</p>
                    
                    <?php if ($contact_msg == 'success'): ?>
                        <div class="bg-green-500/20 border border-green-500 text-green-400 p-4 rounded-lg mb-6">Permintaan terkirim! Tim kami akan menghubungi Anda segera.</div>
                    <?php elseif ($contact_msg == 'failed'): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-400 p-4 rounded-lg mb-6">Gagal mengirim. Silakan coba lagi atau hubungi WhatsApp kami.</div>
                    <?php endif; ?>
                    
                    <form class="space-y-4" method="POST" action="index.php#kontak">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="w-full px-4 py-3 rounded-lg border border-gray-600 bg-gray-900 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Nama Anda" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg border border-gray-600 bg-gray-900 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="nama@email.com" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">No. WhatsApp</label>
                            <input type="text" name="whatsapp" class="w-full px-4 py-3 rounded-lg border border-gray-600 bg-gray-900 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0812..." required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Nama Event (Rencana)</label>
                            <input type="text" name="nama_event" class="w-full px-4 py-3 rounded-lg border border-gray-600 bg-gray-900 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Gorontalo Marathon 2025" required>
                        </div>
                        <button type="submit" name="send_contact" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition">
                            Request Consultation
                        </button>
                    </form>
                </div>
                <div class="md:w-1/2 bg-blue-600 flex items-center justify-center p-12 text-white">
                    <div class="text-center w-full relative">
                        <i class="fa-solid fa-quote-left text-4xl opacity-50 mb-6 block"></i>
                        
                        <!-- Navigation Arrows -->
                        <button onclick="prevTestimonial()" class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 md:-ml-12 text-white/30 hover:text-white transition z-20 p-2">
                            <i class="fa-solid fa-chevron-left text-3xl"></i>
                        </button>
                        <button onclick="nextTestimonial()" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 md:-mr-12 text-white/30 hover:text-white transition z-20 p-2">
                            <i class="fa-solid fa-chevron-right text-3xl"></i>
                        </button>

                        <div id="testimonial-slider" class="grid grid-cols-1 relative overflow-hidden">
                            <?php if (!empty($testimonials)): ?>
                                <?php foreach ($testimonials as $index => $testi): ?>
                                <div class="testimonial-slide col-start-1 row-start-1 transition-opacity duration-500 flex flex-col items-center justify-center <?= $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none' ?>" data-index="<?= $index ?>">
                                    <p class="text-xl font-medium italic mb-6">"<?= htmlspecialchars($testi['content']) ?>"</p>
                                    
                                    <div class="flex items-center gap-4">
                                        <?php if (!empty($testi['photo_url'])): ?>
                                            <?php 
                                            // Cek apakah URL eksternal atau path lokal
                                            $img_src = (strpos($testi['photo_url'], 'http') === 0) ? $testi['photo_url'] : htmlspecialchars($testi['photo_url']);
                                            ?>
                                            <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($testi['name']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-white/30">
                                        <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white font-bold border-2 border-white/30">
                                            <?= strtoupper(substr($testi['name'], 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="text-left">
                                            <div class="font-bold text-white"><?= htmlspecialchars($testi['name']) ?></div>
                                            <div class="text-blue-200 text-sm"><?= htmlspecialchars($testi['role']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xl font-medium italic mb-6">"Belum ada testimoni."</p>
                            <?php endif; ?>
                        </div>

                        <!-- Dots Indicator -->
                        <?php if (count($testimonials) > 1): ?>
                        <div class="flex justify-center gap-2 mt-6">
                            <?php foreach ($testimonials as $index => $testi): ?>
                            <button class="w-2 h-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-white w-6' : 'bg-white/40 hover:bg-white/60' ?>" onclick="showTestimonial(<?= $index ?>)"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-black text-gray-400 py-12 border-t border-white/10">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-6 md:mb-0">
                    <span class="text-2xl font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-flag-checkered"></i> Pratama Digitect
                    </span>
                    <p class="mt-2 text-sm text-gray-500">Partner Event Lari & Konser.</p>
                </div>
                <div class="flex space-x-6">
                    <?php
                    foreach ($social_links as $link) {
                        $link = trim($link);
                        if (empty($link)) continue;

                        $icon_class = 'fa-solid fa-link'; // Default icon
                        if (strpos($link, 'instagram.com') !== false) $icon_class = 'fa-brands fa-instagram';
                        if (strpos($link, 'linkedin.com') !== false) $icon_class = 'fa-brands fa-linkedin';
                        if (strpos($link, 'twitter.com') !== false || strpos($link, 'x.com') !== false) $icon_class = 'fa-brands fa-twitter';
                        if (strpos($link, 'facebook.com') !== false) $icon_class = 'fa-brands fa-facebook';
                        if (strpos($link, 'youtube.com') !== false) $icon_class = 'fa-brands fa-youtube';
                        if (strpos($link, 'tiktok.com') !== false) $icon_class = 'fa-brands fa-tiktok';
                        
                        echo '<a href="' . htmlspecialchars($link) . '" target="_blank" class="text-gray-500 hover:text-white transition"><i class="' . $icon_class . ' text-xl"></i></a>';
                    }
                    ?>
                </div>
            </div>
            <div class="border-t border-white/10 mt-8 pt-8 text-center text-sm text-gray-500">
                &copy; <?= date('Y') ?> Pratama Digitect Systems. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/6285298122890" class="whatsapp-float" target="_blank" rel="noopener noreferrer">
        <i class="fa-brands fa-whatsapp"></i>
    </a>

    <script src="script.js"></script>
</body>
</html>