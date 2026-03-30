<?php
require_once 'header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Create Table if not exists (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS testimonials (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, role VARCHAR(100) NOT NULL, content TEXT NOT NULL, photo_url VARCHAR(255), sort_order INT(11) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Add sort_order column if not exists (Migration for existing table)
$check_col = $conn->query("SHOW COLUMNS FROM testimonials LIKE 'sort_order'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE testimonials ADD COLUMN sort_order INT(11) DEFAULT 0");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_testi'])) {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $content = $_POST['content'];
    
    // Handle File Upload
    $photo_url = $_POST['existing_photo'] ?? ''; // Default ke foto lama atau kosong
    
    if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] == 0) {
        $target_dir = "../uploads/testimonials/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES["photo_file"]["name"], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["photo_file"]["tmp_name"], $target_file)) {
                $photo_url = "uploads/testimonials/" . $new_filename; // Simpan path relatif
            }
        }
    } elseif (!empty($_POST['photo_link'])) {
        // Jika tidak ada file yang diupload, cek apakah ada input link eksternal
        // Dan pastikan user memang ingin mengganti/mengisi foto dengan link
        $photo_url = $_POST['photo_link'];
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE testimonials SET name=?, role=?, content=?, photo_url=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $role, $content, $photo_url, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO testimonials (name, role, content, photo_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $role, $content, $photo_url);
    }
    $stmt->execute();
    log_activity($conn, ($id > 0) ? "Mengedit testimoni dari: '" . $name . "'" : "Membuat testimoni baru dari: '" . $name . "'");
    header("Location: testimonials.php?msg=saved");
    exit;
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $res = $conn->query("SELECT name FROM testimonials WHERE id=$del_id");
    $name = $res->fetch_assoc()['name'] ?? 'N/A';
    log_activity($conn, "Menghapus testimoni dari: '" . $name . "' (ID: " . $del_id . ")");
    $conn->query("DELETE FROM testimonials WHERE id=$del_id");
    header("Location: testimonials.php?msg=deleted");
    exit;
}

// Handle Reorder (AJAX)
if (isset($_POST['reorder_testimonials'])) {
    $order = json_decode($_POST['order'], true);
    if (is_array($order)) {
        foreach ($order as $position => $id) {
            $conn->query("UPDATE testimonials SET sort_order = $position WHERE id = " . intval($id));
        }
    }
    exit('ok');
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Testimoni Klien</h1>
        <p class="text-gray-500 mt-1">Kelola apa kata klien tentang layanan Anda.</p>
    </div>
</div>

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
            <form method="POST" action="testimonials.php<?= ($id > 0) ? '?id='.$id : '' ?>" enctype="multipart/form-data">
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
                    <label class="block text-sm font-medium text-gray-400 mb-2">Foto Profil</label>
                    <?php if (!empty($edit_data['photo_url'])): ?>
                        <div class="mb-2">
                            <?php 
                                $img_src = (strpos($edit_data['photo_url'], 'http') === 0) ? $edit_data['photo_url'] : '../' . $edit_data['photo_url'];
                            ?>
                            <img src="<?= htmlspecialchars($img_src) ?>" class="w-12 h-12 rounded-full object-cover border border-gray-600">
                            <p class="text-xs text-gray-500 mt-1">Foto saat ini</p>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" name="photo_file" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 mb-2">
                    <input type="text" name="photo_link" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm" placeholder="Atau masukkan URL gambar (https://...)">
                    <input type="hidden" name="existing_photo" value="<?= $edit_data['photo_url'] ?? '' ?>">
                    <p class="text-xs text-gray-500 mt-1">Prioritas: File Upload > URL Link > Foto Lama.</p>
                </div>
                <div class="flex gap-2">
                    <?php if($id > 0): ?>
                        <a href="testimonials.php" class="flex-1 py-2 rounded-lg border border-gray-600 text-center text-gray-400 hover:bg-gray-700">Batal</a>
                    <?php endif; ?>
                    <button type="submit" name="save_testi" class="flex-1 bg-blue-600 text-white font-bold py-2 rounded-lg hover:bg-blue-700 transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Data -->
    <div class="md:col-span-2">
        <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
            <table class="w-full text-left border-collapse" id="testimonials-table">
                <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold">
                    <tr>
                        <th class="p-4 border-b border-gray-700">Klien</th>
                        <th class="p-4 border-b border-gray-700">Testimoni</th>
                        <th class="p-4 border-b border-gray-700 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700" id="sortable-list">
                    <?php
                    $res = $conn->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC");
                    while($row = $res->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-gray-700/30 cursor-move" data-id="<?= $row['id'] ?>">
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="text-xs text-blue-400"><?= htmlspecialchars($row['role']) ?></div>
                        </td>
                        <td class="p-4 text-sm text-gray-400 italic">"<?= htmlspecialchars(substr($row['content'], 0, 80)) ?>..."</td>
                        <td class="p-4 text-right">
                            <a href="testimonials.php?id=<?= $row['id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3"><i class="fa-solid fa-pen"></i></a>
                            <a href="testimonials.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus testimoni ini?')" class="text-red-400 hover:text-red-300"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-2 text-right"><i class="fa-solid fa-arrows-up-down mr-1"></i> Drag & Drop baris untuk mengubah urutan.</p>
        
        <script>
            new Sortable(document.getElementById('sortable-list'), {
                animation: 150,
                onEnd: function (evt) {
                    let order = [];
                    document.querySelectorAll('#sortable-list tr').forEach((row) => {
                        order.push(row.getAttribute('data-id'));
                    });
                    
                    // Send new order via AJAX
                    let formData = new FormData();
                    formData.append('reorder_testimonials', '1');
                    formData.append('order', JSON.stringify(order));
                    
                    fetch('testimonials.php', { method: 'POST', body: formData })
                        .then(response => console.log('Order updated'));
                }
            });
        </script>
    </div>
</div>

            </div>
        </main>
    </div>
</body>
</html>