<?php
// /Volumes/Data Project/Penawaran website Event Lari/Bootcamp_Renold/admin/index.php

session_start();
// Menghubungkan ke database (asumsi file koneksi ada di folder root/luar folder admin)
require_once '../koneksi_db.php'; 

// --- AUTO UPDATE STATUS ---
$conn->query("UPDATE events SET status = 'completed' WHERE event_date < NOW() AND status = 'upcoming'");

// --- LOGIC: LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- LOGIC: LOGIN ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['do_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Cek user di database
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        // Verifikasi password hash
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            header("Location: index.php");
            exit;
        }
    }
    $login_error = "Username atau Password salah!";
}

// Jika belum login, tampilkan Form Login
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Admin - Pratama Digitect</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-900 flex items-center justify-center h-screen font-sans">
        <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-96 border border-gray-700">
            <div class="text-center mb-6">
                <i class="fa-solid fa-user-shield text-4xl text-blue-500 mb-2"></i>
                <h2 class="text-2xl font-bold text-white">Admin Portal</h2>
            </div>
            <?php if($login_error): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-300 p-3 rounded mb-4 text-sm text-center">
                    <?= $login_error ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-400 text-sm mb-2">Username</label>
                    <input type="text" name="username" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="admin" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-400 text-sm mb-2">Password</label>
                    <input type="password" name="password" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="••••••" required>
                </div>
                <button type="submit" name="do_login" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">Masuk Dashboard</button>
            </form>
            <p class="text-center text-gray-600 text-xs mt-6">Pratama Digitect System v1.0</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- LOGIC: DASHBOARD & CRUD ---

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// 1. Handle Simpan Data (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_event'])) {
    $title = $_POST['title'];
    $slug = $_POST['slug']; 
    $event_link = $_POST['event_link'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $location = $_POST['location'];
    $category = $_POST['category'];
    $status = $_POST['status'];
    $banner_image = $_POST['banner_image']; 

    if ($id > 0) {
        // Update Existing
        $stmt = $conn->prepare("UPDATE events SET title=?, slug=?, event_link=?, description=?, event_date=?, location=?, category=?, status=?, banner_image=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $title, $slug, $event_link, $description, $event_date, $location, $category, $status, $banner_image, $id);
    } else {
        // Insert New
        $stmt = $conn->prepare("INSERT INTO events (title, slug, event_link, description, event_date, location, category, status, banner_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $title, $slug, $event_link, $description, $event_date, $location, $category, $status, $banner_image);
    }
    
    if ($stmt->execute()) {
        header("Location: index.php?msg=saved");
        exit;
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// 2. Handle Delete
if ($action == 'delete' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: index.php?msg=deleted");
    exit;
}

// 3. Fetch Data untuk Edit
$event_data = [];
if (($action == 'edit') && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $event_data = $stmt->get_result()->fetch_assoc();
}

// 4. Handle Settings (Partners)
$partners_data = '';
if ($action == 'settings') {
    // Pastikan tabel ada (Lazy migration jika user lupa jalankan setup_db)
    $conn->query("CREATE TABLE IF NOT EXISTS site_settings (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(50) UNIQUE NOT NULL, setting_value TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $partners_data = $conn->real_escape_string($_POST['partners_data']);
        $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES ('partners_data', '$partners_data') ON DUPLICATE KEY UPDATE setting_value='$partners_data'";
        if ($conn->query($sql)) {
             header("Location: index.php?action=settings&msg=saved");
             exit;
        }
    }
    $res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='partners_data'");
    $partners_data = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['setting_value'] : '';
}

// 5. Handle Testimonials
if ($action == 'testimonials') {
    // Create Table if not exists (Lazy migration)
    $conn->query("CREATE TABLE IF NOT EXISTS testimonials (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, role VARCHAR(100) NOT NULL, content TEXT NOT NULL, photo_url VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    // Handle Save
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_testi'])) {
        $name = $_POST['name'];
        $role = $_POST['role'];
        $content = $_POST['content'];
        $photo_url = $_POST['photo_url'];
        
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE testimonials SET name=?, role=?, content=?, photo_url=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $role, $content, $photo_url, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (name, role, content, photo_url) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $role, $content, $photo_url);
        }
        $stmt->execute();
        header("Location: index.php?action=testimonials&msg=saved");
        exit;
    }

    // Handle Delete
    if (isset($_GET['delete_id'])) {
        $del_id = intval($_GET['delete_id']);
        $conn->query("DELETE FROM testimonials WHERE id=$del_id");
        header("Location: index.php?action=testimonials&msg=deleted");
        exit;
    }
}

// --- VISITOR STATS LOGIC ---
// Pastikan tabel ada (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS visitor_stats (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, visit_date DATE NOT NULL, ip_address VARCHAR(45) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Hitung Pengunjung Hari Ini
$today = date('Y-m-d');
$res_today = $conn->query("SELECT COUNT(*) as total FROM visitor_stats WHERE visit_date = '$today'");
$count_today = $res_today->fetch_assoc()['total'];

// Hitung Pengunjung Kemarin
$yesterday = date('Y-m-d', strtotime("-1 days"));
$res_yesterday = $conn->query("SELECT COUNT(*) as total FROM visitor_stats WHERE visit_date = '$yesterday'");
$count_yesterday = $res_yesterday->fetch_assoc()['total'];

// Hitung Total Pengunjung
$res_total = $conn->query("SELECT COUNT(*) as total FROM visitor_stats");
$count_total = $res_total->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Pratama Digitect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-300 font-sans">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-black border-r border-gray-800 hidden md:flex flex-col">
            <div class="p-6 border-b border-gray-800 flex items-center gap-3">
                <i class="fa-solid fa-flag-checkered text-blue-500 text-xl"></i>
                <span class="text-lg font-bold text-white">Admin Panel</span>
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <a href="index.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($action == 'list') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
                    <i class="fa-solid fa-calendar-days w-5"></i> 
                    <span class="font-medium">Manajemen Event</span>
                </a>
                <a href="index.php?action=settings" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($action == 'settings') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
                    <i class="fa-solid fa-sliders w-5"></i> 
                    <span class="font-medium">Pengaturan Web</span>
                </a>
                <a href="index.php?action=testimonials" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($action == 'testimonials') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
                    <i class="fa-solid fa-comment-dots w-5"></i> 
                    <span class="font-medium">Testimoni Klien</span>
                </a>
                <!-- Menu Placeholder -->
                <a href="#" class="flex items-center gap-3 py-3 px-4 rounded-lg hover:bg-gray-800 transition duration-200 text-gray-500 cursor-not-allowed">
                    <i class="fa-solid fa-users w-5"></i> 
                    <span>Data Peserta (Soon)</span>
                </a>
                <a href="#" class="flex items-center gap-3 py-3 px-4 rounded-lg hover:bg-gray-800 transition duration-200 text-gray-500 cursor-not-allowed">
                    <i class="fa-solid fa-ticket w-5"></i> 
                    <span>Tiket & Kategori (Soon)</span>
                </a>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center gap-3 mb-4 px-2">
                    <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold text-white">AD</div>
                    <div class="text-sm">
                        <div class="text-white font-bold">Administrator</div>
                        <div class="text-xs text-gray-500">Super User</div>
                    </div>
                </div>
                <a href="index.php?action=logout" class="block w-full text-center py-2 px-4 bg-red-900/20 text-red-400 hover:bg-red-900/40 rounded-lg transition text-sm font-bold border border-red-900/30">
                    <i class="fa-solid fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-gray-900 relative">
            
            <!-- Topbar Mobile -->
            <div class="md:hidden bg-black p-4 flex justify-between items-center border-b border-gray-800">
                <span class="font-bold text-white">Admin Panel</span>
                <a href="index.php?action=logout" class="text-red-400"><i class="fa-solid fa-sign-out-alt"></i></a>
            </div>

            <div class="p-8 max-w-7xl mx-auto">
                <!-- Header Page -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-white">
                            <?php 
                            if ($action == 'add') echo 'Tambah Event Baru';
                            elseif ($action == 'edit') echo 'Edit Event';
                            else echo 'Daftar Event';
                            if ($action == 'settings') echo 'Pengaturan Website';
                            if ($action == 'testimonials') echo 'Testimoni Klien';
                            ?>
                        </h1>
                        <p class="text-gray-500 mt-1">Kelola semua event lari dan konser Anda di sini.</p>
                    </div>
                    
                    <?php if ($action == 'list'): ?>
                    <a href="index.php?action=add" class="bg-blue-600 text-white px-5 py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 flex items-center gap-2 font-bold">
                        <i class="fa-solid fa-plus"></i> Tambah Event
                    </a>
                    <?php elseif ($action == 'settings'): ?>
                    <!-- No Button -->
                    <?php elseif ($action == 'testimonials'): ?>
                    <!-- Button handled inside section -->
                    <?php else: ?>
                    <a href="index.php" class="bg-gray-800 text-gray-300 px-5 py-3 rounded-lg hover:bg-gray-700 transition border border-gray-700 flex items-center gap-2 font-medium">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Notification -->
                <?php if (isset($_GET['msg'])): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-900/30 border border-green-500/30 text-green-400 flex items-center gap-3">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>
                            <?php 
                            if($_GET['msg']=='saved') echo "Data event berhasil disimpan!";
                            if($_GET['msg']=='deleted') echo "Data berhasil dihapus.";
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Content Area -->
                <?php if ($action == 'list'): ?>
                    
                    <!-- Visitor Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Pengunjung Hari Ini</p>
                                <h3 class="text-3xl font-bold text-white"><?= number_format($count_today) ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-blue-900/30 rounded-lg flex items-center justify-center text-blue-400 text-xl"><i class="fa-solid fa-user-clock"></i></div>
                        </div>
                        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Pengunjung Kemarin</p>
                                <h3 class="text-3xl font-bold text-white"><?= number_format($count_yesterday) ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-purple-900/30 rounded-lg flex items-center justify-center text-purple-400 text-xl"><i class="fa-solid fa-calendar-minus"></i></div>
                        </div>
                        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Pengunjung</p>
                                <h3 class="text-3xl font-bold text-white"><?= number_format($count_total) ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-green-900/30 rounded-lg flex items-center justify-center text-green-400 text-xl"><i class="fa-solid fa-users"></i></div>
                        </div>
                    </div>

                    <!-- Table List -->
                    <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold tracking-wider">
                                    <tr>
                                        <th class="p-5 border-b border-gray-700">Info Event</th>
                                        <th class="p-5 border-b border-gray-700">Jadwal & Lokasi</th>
                                        <th class="p-5 border-b border-gray-700">Kategori</th>
                                        <th class="p-5 border-b border-gray-700">Status</th>
                                        <th class="p-5 border-b border-gray-700 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php
                                    $result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
                                    if ($result->num_rows > 0):
                                        while($row = $result->fetch_assoc()):
                                    ?>
                                    <tr class="hover:bg-gray-700/30 transition group">
                                        <td class="p-5">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 rounded-lg bg-gray-700 overflow-hidden flex-shrink-0">
                                                    <?php if($row['banner_image']): ?>
                                                        <img src="<?= htmlspecialchars($row['banner_image']) ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center text-gray-500"><i class="fa-solid fa-image"></i></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-white text-lg"><?= htmlspecialchars($row['title']) ?></div>
                                                    <div class="text-xs text-gray-500 font-mono">/<?= htmlspecialchars($row['slug']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-5">
                                            <div class="text-sm text-gray-300 mb-1"><i class="fa-regular fa-calendar mr-2 text-blue-500"></i><?= date('d M Y, H:i', strtotime($row['event_date'])) ?></div>
                                            <div class="text-xs text-gray-500"><i class="fa-solid fa-location-dot mr-2 text-red-500"></i><?= htmlspecialchars($row['location']) ?></div>
                                        </td>
                                        <td class="p-5">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold border <?= $row['category'] == 'lari' ? 'bg-blue-900/20 text-blue-400 border-blue-500/30' : 'bg-purple-900/20 text-purple-400 border-purple-500/30' ?>">
                                                <?= strtoupper($row['category']) ?>
                                            </span>
                                        </td>
                                        <td class="p-5">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold flex items-center w-fit gap-2
                                                <?php 
                                                if($row['status']=='upcoming') echo 'bg-green-900/20 text-green-400 border border-green-500/30';
                                                elseif($row['status']=='completed') echo 'bg-gray-700/50 text-gray-400 border border-gray-600';
                                                else echo 'bg-yellow-900/20 text-yellow-400 border border-yellow-500/30';
                                                ?>">
                                                <span class="w-2 h-2 rounded-full <?php 
                                                if($row['status']=='upcoming') echo 'bg-green-500 animate-pulse';
                                                elseif($row['status']=='completed') echo 'bg-gray-500';
                                                else echo 'bg-yellow-500';
                                                ?>"></span>
                                                <?= strtoupper($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="p-5 text-right">
                                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <a href="index.php?action=edit&id=<?= $row['id'] ?>" class="w-8 h-8 rounded bg-blue-600 text-white flex items-center justify-center hover:bg-blue-500 transition" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                                <a href="index.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus event ini? Data tidak bisa dikembalikan.')" class="w-8 h-8 rounded bg-red-600 text-white flex items-center justify-center hover:bg-red-500 transition" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                    else: ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-gray-500">Belum ada data event. Silakan tambah event baru.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                    <!-- Form Add/Edit -->
                    <div class="bg-gray-800 rounded-xl shadow-xl p-8 border border-gray-700 max-w-4xl mx-auto">
                        <form method="POST" action="index.php?action=<?= $action ?>&id=<?= $id ?>">
                            
                            <!-- Section 1: Info Dasar -->
                            <div class="mb-8">
                                <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Informasi Dasar</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Nama Event</label>
                                        <input type="text" name="title" value="<?= $event_data['title'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: Jakarta Marathon 2024" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Slug URL (Unik)</label>
                                        <input type="text" name="slug" value="<?= $event_data['slug'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="jakarta-marathon-2024" required>
                                        <p class="text-xs text-gray-500 mt-1">Digunakan untuk link: domain.com/event/<b>slug</b></p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Link Website Eksternal (Opsional)</label>
                                        <input type="url" name="event_link" value="<?= $event_data['event_link'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="https://website-event-anda.com">
                                        <p class="text-xs text-gray-500 mt-1">Jika diisi, tombol 'Daftar' di halaman depan akan langsung mengarah ke link ini.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Kategori</label>
                                        <select name="category" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition">
                                            <option value="lari" <?= ($event_data['category'] ?? '') == 'lari' ? 'selected' : '' ?>>🏃 Lari (Run)</option>
                                            <option value="konser" <?= ($event_data['category'] ?? '') == 'konser' ? 'selected' : '' ?>>🎵 Konser (Music)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Waktu & Tempat -->
                            <div class="mb-8">
                                <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Waktu & Tempat</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Tanggal & Waktu</label>
                                        <input type="datetime-local" name="event_date" value="<?= isset($event_data['event_date']) ? date('Y-m-d\TH:i', strtotime($event_data['event_date'])) : '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Lokasi Venue</label>
                                        <input type="text" name="location" value="<?= $event_data['location'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: GBK, Jakarta" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Detail & Visual -->
                            <div class="mb-8">
                                <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Detail & Visual</h3>
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Status Event</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="status" value="draft" class="accent-blue-500" <?= ($event_data['status'] ?? 'draft') == 'draft' ? 'checked' : '' ?>>
                                                <span class="text-gray-300">Draft (Sembunyi)</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="status" value="upcoming" class="accent-green-500" <?= ($event_data['status'] ?? '') == 'upcoming' ? 'checked' : '' ?>>
                                                <span class="text-green-400 font-bold">Upcoming (Tayang)</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="status" value="completed" class="accent-gray-500" <?= ($event_data['status'] ?? '') == 'completed' ? 'checked' : '' ?>>
                                                <span class="text-gray-400">Completed (Selesai)</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Deskripsi Lengkap</label>
                                        <textarea name="description" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Jelaskan detail event di sini..."><?= $event_data['description'] ?? '' ?></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-400 mb-2">URL Banner Image (Opsional)</label>
                                        <div class="flex gap-2">
                                            <input type="text" name="banner_image" value="<?= $event_data['banner_image'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="https://images.unsplash.com/...">
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Kosongkan jika ingin menggunakan screenshot otomatis dari Link Website Eksternal.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end gap-4 pt-6 border-t border-gray-700">
                                <a href="index.php" class="px-6 py-3 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 transition font-medium">Batal</a>
                                <button type="submit" name="save_event" class="px-8 py-3 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                                    <i class="fa-solid fa-save mr-2"></i> Simpan Event
                                </button>
                            </div>
                        </form>
                    </div>
                
                <?php elseif ($action == 'settings'): ?>
                    <!-- Form Settings -->
                    <div class="bg-gray-800 rounded-xl shadow-xl p-8 border border-gray-700 max-w-4xl mx-auto">
                        <form method="POST" action="index.php?action=settings">
                            <div class="mb-6">
                                <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">Partners & Sponsors</h3>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Daftar URL Logo (Satu link per baris)</label>
                                <textarea name="partners_data" rows="10" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition font-mono text-sm" placeholder="https://contoh.com/logo1.png&#10;https://contoh.com/logo2.png"><?= htmlspecialchars($partners_data) ?></textarea>
                                <p class="text-xs text-gray-500 mt-2">Masukkan Direct Link gambar (jpg/png/svg). Gambar akan otomatis ditampilkan di halaman depan.</p>
                            </div>
                            
                            <div class="flex justify-end pt-6 border-t border-gray-700">
                                <button type="submit" class="px-8 py-3 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                                    <i class="fa-solid fa-save mr-2"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($action == 'testimonials'): ?>
                    <!-- Testimonials Management -->
                    <div class="grid md:grid-cols-3 gap-8">
                        <!-- Form Input -->
                        <div class="md:col-span-1">
                            <div class="bg-gray-800 rounded-xl shadow-xl p-6 border border-gray-700 sticky top-6">
                                <h3 class="text-lg font-bold text-white mb-4"><?= ($id > 0) ? 'Edit Testimoni' : 'Tambah Testimoni' ?></h3>
                                <?php
                                $edit_data = [];
                                if ($id > 0) {
                                    $res = $conn->query("SELECT * FROM testimonials WHERE id=$id");
                                    if ($res) $edit_data = $res->fetch_assoc();
                                }
                                ?>
                                <form method="POST" action="index.php?action=testimonials<?= ($id > 0) ? '&id='.$id : '' ?>">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Nama Klien</label>
                                        <input type="text" name="name" value="<?= $edit_data['name'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Sarah Wijaya" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Role / Jabatan</label>
                                        <input type="text" name="role" value="<?= $edit_data['role'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Promotor SoundFest" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">Isi Testimoni</label>
                                        <textarea name="content" rows="4" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" required><?= $edit_data['content'] ?? '' ?></textarea>
                                    </div>
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-400 mb-2">URL Foto (Opsional)</label>
                                        <input type="text" name="photo_url" value="<?= $edit_data['photo_url'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="https://...">
                                    </div>
                                    <div class="flex gap-2">
                                        <?php if($id > 0): ?>
                                            <a href="index.php?action=testimonials" class="flex-1 py-2 rounded-lg border border-gray-600 text-center text-gray-400 hover:bg-gray-700">Batal</a>
                                        <?php endif; ?>
                                        <button type="submit" name="save_testi" class="flex-1 bg-blue-600 text-white font-bold py-2 rounded-lg hover:bg-blue-700 transition">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- List Data -->
                        <div class="md:col-span-2">
                            <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold">
                                        <tr>
                                            <th class="p-4 border-b border-gray-700">Klien</th>
                                            <th class="p-4 border-b border-gray-700">Testimoni</th>
                                            <th class="p-4 border-b border-gray-700 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-700">
                                        <?php
                                        $res = $conn->query("SELECT * FROM testimonials ORDER BY id DESC");
                                        while($row = $res->fetch_assoc()):
                                        ?>
                                        <tr class="hover:bg-gray-700/30">
                                            <td class="p-4">
                                                <div class="font-bold text-white"><?= htmlspecialchars($row['name']) ?></div>
                                                <div class="text-xs text-blue-400"><?= htmlspecialchars($row['role']) ?></div>
                                            </td>
                                            <td class="p-4 text-sm text-gray-400 italic">"<?= htmlspecialchars(substr($row['content'], 0, 80)) ?>..."</td>
                                            <td class="p-4 text-right">
                                                <a href="index.php?action=testimonials&id=<?= $row['id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3"><i class="fa-solid fa-pen"></i></a>
                                                <a href="index.php?action=testimonials&delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus testimoni ini?')" class="text-red-400 hover:text-red-300"><i class="fa-solid fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

</body>
</html>
