<?php
require_once 'header.php';

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
        header("Location: events.php?msg=saved");
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
    header("Location: events.php?msg=deleted");
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
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">
            <?php 
            if ($action == 'add') echo 'Tambah Event Baru';
            elseif ($action == 'edit') echo 'Edit Event';
            else echo 'Daftar Event';
            ?>
        </h1>
        <p class="text-gray-500 mt-1">Kelola semua event lari dan konser Anda di sini.</p>
    </div>
    
    <?php if ($action == 'list'): ?>
    <a href="events.php?action=add" class="bg-blue-600 text-white px-5 py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 flex items-center gap-2 font-bold">
        <i class="fa-solid fa-plus"></i> Tambah Event
    </a>
    <?php else: ?>
    <a href="events.php" class="bg-gray-800 text-gray-300 px-5 py-3 rounded-lg hover:bg-gray-700 transition border border-gray-700 flex items-center gap-2 font-medium">
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

<?php if ($action == 'list'): ?>
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
                                <a href="events.php?action=edit&id=<?= $row['id'] ?>" class="w-8 h-8 rounded bg-blue-600 text-white flex items-center justify-center hover:bg-blue-500 transition" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="events.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus event ini? Data tidak bisa dikembalikan.')" class="w-8 h-8 rounded bg-red-600 text-white flex items-center justify-center hover:bg-red-500 transition" title="Hapus"><i class="fa-solid fa-trash"></i></a>
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
        <form method="POST" action="events.php?action=<?= $action ?>&id=<?= $id ?>">
            
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
                <a href="events.php" class="px-6 py-3 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 transition font-medium">Batal</a>
                <button type="submit" name="save_event" class="px-8 py-3 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                    <i class="fa-solid fa-save mr-2"></i> Simpan Event
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

            </div>
        </main>
    </div>
</body>
</html>