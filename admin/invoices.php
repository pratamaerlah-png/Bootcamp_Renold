<?php
require_once 'header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Create Table (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS invoices (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(50) NOT NULL UNIQUE, client_name VARCHAR(100) NOT NULL, client_email VARCHAR(100) NOT NULL, client_phone VARCHAR(20), invoice_date DATE NOT NULL, due_date DATE NOT NULL, total_amount DECIMAL(15, 2) NOT NULL, status ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid', items_json TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Handle Delete Invoice
if (isset($_GET['delete_inv'])) {
    $del_id = intval($_GET['delete_inv']);
    $res = $conn->query("SELECT invoice_number FROM invoices WHERE id=$del_id");
    $inv_num = $res->fetch_assoc()['invoice_number'] ?? 'N/A';
    log_activity($conn, "Menghapus invoice: '" . $inv_num . "' (ID: " . $del_id . ")");
    $conn->query("DELETE FROM invoices WHERE id=$del_id");
    header("Location: invoices.php?msg=deleted");
    exit;
}

// Handle Mark as Paid
if (isset($_GET['mark_paid'])) {
    $paid_id = intval($_GET['mark_paid']);
    $res = $conn->query("SELECT invoice_number FROM invoices WHERE id=$paid_id");
    $inv_num = $res->fetch_assoc()['invoice_number'] ?? 'N/A';
    log_activity($conn, "Menandai lunas invoice: '" . $inv_num . "' (ID: " . $paid_id . ")");
    $conn->query("UPDATE invoices SET status='paid' WHERE id=$paid_id");
    header("Location: invoices.php?msg=saved");
    exit;
}

// Handle Save Invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_invoice'])) {
    $inv_number = $_POST['invoice_number'];
    $client_name = $_POST['client_name'];
    $client_email = $_POST['client_email'];
    $client_phone = $_POST['client_phone'];
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    
    // Process Items
    $items = [];
    $total = 0;
    if (isset($_POST['item_desc'])) {
        for ($i = 0; $i < count($_POST['item_desc']); $i++) {
            if (!empty($_POST['item_desc'][$i])) {
                $amount = floatval($_POST['item_amount'][$i]);
                $items[] = ['desc' => $_POST['item_desc'][$i], 'amount' => $amount];
                $total += $amount;
            }
        }
    }
    $items_json = json_encode($items);

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE invoices SET client_name=?, client_email=?, client_phone=?, invoice_date=?, due_date=?, total_amount=?, items_json=? WHERE id=?");
        $stmt->bind_param("sssssdsi", $client_name, $client_email, $client_phone, $inv_date, $due_date, $total, $items_json, $id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, client_name, client_email, client_phone, invoice_date, due_date, total_amount, items_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssds", $inv_number, $client_name, $client_email, $client_phone, $inv_date, $due_date, $total, $items_json);
    }
    $stmt->execute();
    log_activity($conn, ($id > 0) ? "Mengedit invoice: '" . $inv_number . "'" : "Membuat invoice baru: '" . $inv_number . "'");
    header("Location: invoices.php?msg=saved");
    exit;
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white"><?= ($id > 0) ? 'Edit Tagihan' : 'Buat Tagihan Baru' ?></h1>
        <p class="text-gray-500 mt-1">Kelola tagihan dan pembayaran klien.</p>
    </div>
</div>

<!-- Invoice Management -->
<div class="grid md:grid-cols-3 gap-8">
    <!-- Form Create Invoice -->
    <div class="md:col-span-1">
        <div class="bg-gray-800 rounded-xl shadow-xl p-6 border border-gray-700 sticky top-6">
            <h3 class="text-lg font-bold text-white mb-4"><?= ($id > 0) ? 'Edit Tagihan' : 'Buat Tagihan Baru' ?></h3>
            <?php
            $edit_inv = [];
            $edit_items = [];
            if ($id > 0) {
                $res = $conn->query("SELECT * FROM invoices WHERE id=$id");
                if ($res) {
                    $edit_inv = $res->fetch_assoc();
                    $edit_items = json_decode($edit_inv['items_json'], true);
                }
            }
            ?>
            <form method="POST" action="invoices.php<?= ($id > 0) ? '?id='.$id : '' ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">No. Invoice</label>
                    <input type="text" name="invoice_number" value="<?= $edit_inv['invoice_number'] ?? 'INV-'.date('Ymd').'-'.rand(100,999) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Nama Klien</label>
                    <input type="text" name="client_name" value="<?= $edit_inv['client_name'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Email</label>
                        <input type="email" name="client_email" value="<?= $edit_inv['client_email'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">WhatsApp</label>
                        <input type="text" name="client_phone" value="<?= $edit_inv['client_phone'] ?? '' ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="628...">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Tgl Invoice</label>
                        <input type="date" name="invoice_date" value="<?= $edit_inv['invoice_date'] ?? date('Y-m-d') ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Jatuh Tempo</label>
                        <input type="date" name="due_date" value="<?= $edit_inv['due_date'] ?? date('Y-m-d', strtotime('+7 days')) ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                
                <div class="mb-4 border-t border-gray-700 pt-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Item Tagihan</label>
                    
                    <!-- Datalist untuk saran deskripsi -->
                    <datalist id="item-suggestions">
                        <option value="Web Design & Development">
                        <option value="Web Hosting/Cloud Hosting">
                        <option value="Content Management System (CMS)">
                        <option value="Server Resource Optimization">
                    </datalist>

                    <div id="invoice-items" class="space-y-2">
                        <?php if (!empty($edit_items)): ?>
                            <?php foreach ($edit_items as $item): ?>
                            <div class="flex gap-2">
                                <input type="text" name="item_desc[]" value="<?= htmlspecialchars($item['desc']) ?>" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                <input type="number" name="item_amount[]" value="<?= $item['amount'] ?>" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp" required>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2">
                                <input type="text" name="item_desc[]" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                <input type="number" name="item_amount[]" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp" required>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addItem()" class="mt-2 text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1"><i class="fa-solid fa-plus"></i> Tambah Item</button>
                </div>

                <div class="flex gap-2">
                    <?php if($id > 0): ?>
                        <a href="invoices.php" class="flex-1 py-2 rounded-lg border border-gray-600 text-center text-gray-400 hover:bg-gray-700">Batal</a>
                    <?php endif; ?>
                    <button type="submit" name="save_invoice" class="flex-1 bg-green-600 text-white font-bold py-2 rounded-lg hover:bg-green-700 transition shadow-lg">Simpan</button>
                </div>
            </form>
            <script>
                function addItem() {
                    const div = document.createElement('div');
                    div.className = 'flex gap-2';
                    div.innerHTML = '<input type="text" name="item_desc[]" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item"><input type="number" name="item_amount[]" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp">';
                    document.getElementById('invoice-items').appendChild(div);
                }
            </script>
        </div>
    </div>

    <!-- List Invoices -->
    <div class="md:col-span-2">
        <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
            <table class="w-full text-left border-collapse">
                <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold">
                    <tr>
                        <th class="p-4 border-b border-gray-700">Invoice</th>
                        <th class="p-4 border-b border-gray-700">Klien</th>
                        <th class="p-4 border-b border-gray-700">Total</th>
                        <th class="p-4 border-b border-gray-700 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php
                    $res = $conn->query("SELECT * FROM invoices ORDER BY id DESC");
                    while($row = $res->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-gray-700/30">
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($row['invoice_number']) ?></div>
                            <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($row['invoice_date'])) ?></div>
                        </td>
                        <td class="p-4 text-white"><?= htmlspecialchars($row['client_name']) ?></td>
                        <td class="p-4">
                            <div class="font-bold text-green-400">Rp <?= number_format($row['total_amount'], 0, ',', '.') ?></div>
                            <?php if($row['status'] == 'paid'): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-green-900 text-green-300 border border-green-700 mt-1">LUNAS</span>
                            <?php else: ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-red-900 text-red-300 border border-red-700 mt-1">BELUM BAYAR</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right">
                            <a href="generate_invoice.php?id=<?= $row['id'] ?>&action=view" target="_blank" class="text-gray-400 hover:text-white mr-3" title="Lihat PDF"><i class="fa-solid fa-file-pdf"></i></a>
                            <a href="generate_invoice.php?id=<?= $row['id'] ?>&action=email" class="text-blue-400 hover:text-blue-300 mr-3" title="Kirim Email" onclick="return confirm('Kirim invoice ke email klien?')"><i class="fa-solid fa-envelope"></i></a>
                            <a href="generate_invoice.php?id=<?= $row['id'] ?>&action=whatsapp" class="text-green-500 hover:text-green-400" title="Kirim WA Otomatis" onclick="return confirm('Kirim notifikasi WhatsApp otomatis?')"><i class="fa-brands fa-whatsapp"></i></a>
                            <a href="invoices.php?id=<?= $row['id'] ?>" class="text-yellow-500 hover:text-yellow-400 ml-3" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="invoices.php?delete_inv=<?= $row['id'] ?>" class="text-red-500 hover:text-red-400 ml-3" title="Hapus" onclick="return confirm('Hapus invoice ini?')"><i class="fa-solid fa-trash"></i></a>
                            <?php if($row['status'] != 'paid'): ?>
                                <a href="invoices.php?mark_paid=<?= $row['id'] ?>" class="text-green-500 hover:text-green-400 ml-3" title="Tandai Lunas" onclick="return confirm('Tandai invoice ini sebagai LUNAS?')"><i class="fa-solid fa-check-circle"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

            </div>
        </main>
    </div>
</body>
</html>