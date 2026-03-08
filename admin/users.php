<?php
require_once 'header.php';

// Cek apakah user yang login adalah admin (hanya admin yang boleh kelola user)
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='p-8'><div class='bg-red-900/20 text-red-400 p-4 rounded-lg border border-red-500/30'>Akses Ditolak. Hanya Administrator yang dapat mengakses halaman ini.</div></div>";
    require_once 'sidebar.php'; // Load sidebar agar layout tidak rusak total
    exit;
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// 1. Handle Simpan Data (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    if ($id > 0) {
        // Update Existing
        if (!empty($password)) {
            // Jika password diisi, update password juga
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $username, $email, $role, $password_hash, $id);
        } else {
            // Jika password kosong, jangan update password
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $email, $role, $id);
        }
    } else {
        // Insert New
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $password_hash, $role);
    }
    
    if ($stmt->execute()) {
        header("Location: users.php?msg=saved");
        exit;
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// 2. Handle Delete
if ($action == 'delete' && $id > 0) {
    // Cegah hapus diri sendiri
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Anda tidak dapat menghapus akun sendiri!'); window.location='users.php';</script>";
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: users.php?msg=deleted");
    exit;
}

// 3. Fetch Data untuk Edit
$user_data = [];
if (($action == 'edit') && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Manajemen User</h1>
        <p class="text-gray-500 mt-1">Kelola akun administrator dan staff.</p>
    </div>
    
    <?php if ($action == 'list'): ?>
    <a href="users.php?action=add" class="bg-blue-600 text-white px-5 py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 flex items-center gap-2 font-bold">
        <i class="fa-solid fa-user-plus"></i> Tambah User
    </a>
    <?php else: ?>
    <a href="users.php" class="bg-gray-800 text-gray-300 px-5 py-3 rounded-lg hover:bg-gray-700 transition border border-gray-700 flex items-center gap-2 font-medium">
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
            if($_GET['msg']=='saved') echo "Data user berhasil disimpan!";
            if($_GET['msg']=='deleted') echo "Data user berhasil dihapus.";
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
                        <th class="p-5 border-b border-gray-700">Username</th>
                        <th class="p-5 border-b border-gray-700">Email</th>
                        <th class="p-5 border-b border-gray-700">Role</th>
                        <th class="p-5 border-b border-gray-700 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php
                    $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
                    if ($result->num_rows > 0):
                        while($row = $result->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-gray-700/30 transition">
                        <td class="p-5 font-bold text-white"><?= htmlspecialchars($row['username']) ?></td>
                        <td class="p-5 text-gray-300"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-xs font-bold border <?= $row['role'] == 'admin' ? 'bg-purple-900/20 text-purple-400 border-purple-500/30' : 'bg-blue-900/20 text-blue-400 border-blue-500/30' ?>">
                                <?= strtoupper($row['role']) ?>
                            </span>
                        </td>
                        <td class="p-5 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="users.php?action=edit&id=<?= $row['id'] ?>" class="w-8 h-8 rounded bg-blue-600 text-white flex items-center justify-center hover:bg-blue-500 transition" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus user ini?')" class="w-8 h-8 rounded bg-red-600 text-white flex items-center justify-center hover:bg-red-500 transition" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; 
                    else: ?>
                    <tr>
                        <td colspan="4" class="p-8 text-center text-gray-500">Belum ada data user.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <!-- Form Add/Edit -->
    <div class="bg-gray-800 rounded-xl shadow-xl p-8 border border-gray-700 max-w-2xl mx-auto">
        <form method="POST" action="users.php?action=<?= $action ?>&id=<?= $id ?>">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-400 mb-2">Username</label>
                <input type="text" name="username" value="<?= $user_data['username'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-400 mb-2">Email</label>
                <input type="email" name="email" value="<?= $user_data['email'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-400 mb-2">Role</label>
                <select name="role" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition">
                    <option value="admin" <?= ($user_data['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin (Full Access)</option>
                    <option value="eo" <?= ($user_data['role'] ?? '') == 'eo' ? 'selected' : '' ?>>EO (Event Organizer)</option>
                    <option value="staff" <?= ($user_data['role'] ?? '') == 'staff' ? 'selected' : '' ?>>Staff (Limited)</option>
                </select>
            </div>
            <div class="mb-8">
                <label class="block text-sm font-medium text-gray-400 mb-2">Password <?= ($id > 0) ? '(Kosongkan jika tidak ingin mengubah)' : '' ?></label>
                <input type="password" name="password" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" <?= ($id == 0) ? 'required' : '' ?>>
            </div>

            <div class="flex justify-end gap-4 pt-6 border-t border-gray-700">
                <a href="users.php" class="px-6 py-3 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 transition font-medium">Batal</a>
                <button type="submit" name="save_user" class="px-8 py-3 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                    <i class="fa-solid fa-save mr-2"></i> Simpan User
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