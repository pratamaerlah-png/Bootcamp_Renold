<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Mencegah error blank saat redirect (Headers already sent)
require_once 'header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Create Table (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS invoices (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(50) NOT NULL UNIQUE, client_name VARCHAR(100) NOT NULL, client_email VARCHAR(100) NOT NULL, client_phone VARCHAR(20), invoice_date DATE NOT NULL, due_date DATE NOT NULL, total_amount DECIMAL(15, 2) NOT NULL, status ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid', items_json TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Lazy migration for discount column
$check_col_discount = $conn->query("SHOW COLUMNS FROM invoices LIKE 'discount_percentage'");
if ($check_col_discount->num_rows == 0) {
    $conn->query("ALTER TABLE invoices ADD COLUMN discount_percentage DECIMAL(5, 2) DEFAULT 0.00");
}

// Lazy migration for discount nominal & type
$check_col_discount_type = $conn->query("SHOW COLUMNS FROM invoices LIKE 'discount_type'");
if ($check_col_discount_type->num_rows == 0) {
    $conn->query("ALTER TABLE invoices ADD COLUMN discount_type ENUM('percentage', 'nominal') DEFAULT 'percentage', ADD COLUMN discount_amount DECIMAL(15, 2) DEFAULT 0.00");
}

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

    // Blokir proses edit jika status sudah paid
    if ($id > 0) {
        $res_check = $conn->query("SELECT status FROM invoices WHERE id=$id");
        if ($res_check && $res_check->fetch_assoc()['status'] == 'paid') {
            die("Akses ditolak: Invoice yang sudah LUNAS tidak dapat diedit.");
        }
    }

    $client_name = $_POST['client_name'];
    $client_email = $_POST['client_email'];
    $client_phone = $_POST['client_phone'];
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $discount_percentage = 0;
    $discount_amount = 0;
    if ($discount_type == 'nominal') {
        $discount_amount = $discount_value;
    } else {
        $discount_percentage = $discount_value;
    }
    
    // Process Items
    $items = [];
    $subtotal = 0;
    $adjustments = 0;
    if (isset($_POST['item_desc'])) {
        for ($i = 0; $i < count($_POST['item_desc']); $i++) {
            if (!empty($_POST['item_desc'][$i])) {
                $amount = floatval($_POST['item_amount'][$i]);
                $desc = $_POST['item_desc'][$i];
                $items[] = ['desc' => $desc, 'amount' => $amount];

                // Cek apakah item adalah DP berdasarkan deskripsi.
                if (stripos($desc, 'Down Payment') !== false) {
                    // Selalu anggap sebagai pengurang, bahkan jika user input positif
                    $adjustments += -abs($amount);
                } else {
                    if ($amount >= 0) {
                        $subtotal += $amount;
                    } else {
                        // Untuk item pengurang lainnya (misal: diskon manual)
                        $adjustments += $amount;
                    }
                }
            }
        }
    }
    $items_json = json_encode($items);

    // Calculate final total with discount
    $discount_amount_calc = ($discount_type == 'nominal') ? $discount_amount : ($subtotal * $discount_percentage) / 100;
    $total = $subtotal - $discount_amount_calc + $adjustments;

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE invoices SET client_name=?, client_email=?, client_phone=?, invoice_date=?, due_date=?, total_amount=?, items_json=?, discount_percentage=?, discount_type=?, discount_amount=? WHERE id=?");
        $stmt->bind_param("sssssdsdsdi", $client_name, $client_email, $client_phone, $inv_date, $due_date, $total, $items_json, $discount_percentage, $discount_type, $discount_amount, $id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, client_name, client_email, client_phone, invoice_date, due_date, total_amount, items_json, discount_percentage, discount_type, discount_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdsdsd", $inv_number, $client_name, $client_email, $client_phone, $inv_date, $due_date, $total, $items_json, $discount_percentage, $discount_type, $discount_amount);
    }
    $stmt->execute();
    log_activity($conn, ($id > 0) ? "Mengedit invoice: '" . $inv_number . "'" : "Membuat invoice baru: '" . $inv_number . "'");
    header("Location: invoices.php?msg=saved");
    exit;
}

// Hitung total pendapatan untuk ditampilkan di pojok kanan atas
$total_all = 0;
$total_paid = 0;
try {
    $res_totals = $conn->query("SELECT 
        SUM(total_amount) as total_all,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid
    FROM invoices");
    if ($res_totals) {
        $totals = $res_totals->fetch_assoc();
        $total_all = $totals['total_all'] ?? 0;
        $total_paid = $totals['total_paid'] ?? 0;
    }
} catch (Exception $e) {}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white"><?= ($id > 0) ? 'Edit Tagihan' : 'Buat Tagihan Baru' ?></h1>
        <p class="text-gray-500 mt-1">Kelola tagihan dan pembayaran klien.</p>
    </div>
    <div class="bg-gray-800 border border-gray-700 px-6 py-3 rounded-xl shadow-lg flex gap-6">
        <div>
            <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Semua Tagihan</p>
            <p class="text-lg font-bold text-white">Rp <?= number_format($total_all, 0, ',', '.') ?></p>
        </div>
        <div class="border-l border-gray-700 pl-6">
            <p class="text-xs text-green-500 font-bold uppercase tracking-wider mb-1">Pendapatan (Lunas)</p>
            <p class="text-lg font-bold text-green-400">Rp <?= number_format($total_paid, 0, ',', '.') ?></p>
        </div>
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
            $invoice_number_default = '';
            if ($id > 0) {
                $res = $conn->query("SELECT * FROM invoices WHERE id=$id");
                if ($res) {
                    $edit_inv = $res->fetch_assoc();
                    // Blokir render form edit jika status sudah paid
                    if ($edit_inv['status'] == 'paid') {
                        echo "<script>alert('Invoice yang sudah LUNAS tidak dapat diedit!'); window.location='invoices.php';</script>";
                        exit;
                    }
                    $edit_items = json_decode($edit_inv['items_json'], true);
                }
            } else {
                // Generate new unique invoice number
                $yearMonth = date('Y/m');
                $prefix = "INV/" . $yearMonth . "/";
                
                $stmt_num = $conn->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
                $like_pattern = $prefix . '%';
                $stmt_num->bind_param("s", $like_pattern);
                $stmt_num->execute();
                $res_num = $stmt_num->get_result();
                
                if ($res_num->num_rows > 0) {
                    $last_inv = $res_num->fetch_assoc()['invoice_number'];
                    $last_num = intval(substr($last_inv, strlen($prefix)));
                    $next_num = $last_num + 1;
                } else {
                    $next_num = 1;
                }
                $invoice_number_default = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            }
            ?>
            <form method="POST" action="invoices.php<?= ($id > 0) ? '?id='.$id : '' ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">No. Invoice</label>
                    <input type="text" name="invoice_number" value="<?= $edit_inv['invoice_number'] ?? $invoice_number_default ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none" readonly>
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
                            <div class="flex gap-2 items-center">
                                <span class="drag-handle cursor-move text-gray-500 p-2" title="Geser untuk urutkan"><i class="fa-solid fa-grip-vertical"></i></span>
                                <input type="text" name="item_desc[]" value="<?= htmlspecialchars($item['desc']) ?>" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                <input type="number" name="item_amount[]" value="<?= $item['amount'] ?>" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp" required>
                                <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-400 px-2" title="Hapus Item"><i class="fa-solid fa-trash"></i></button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2 items-center">
                                <span class="drag-handle cursor-move text-gray-500 p-2" title="Geser untuk urutkan"><i class="fa-solid fa-grip-vertical"></i></span>
                                <input type="text" name="item_desc[]" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                <input type="number" name="item_amount[]" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp" required>
                                <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-400 px-2" title="Hapus Item"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="button" onclick="addItem()" class="mt-2 text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1"><i class="fa-solid fa-plus"></i> Tambah Item</button>
                        <button type="button" onclick="addDP()" class="mt-2 text-xs text-yellow-400 hover:text-yellow-300 flex items-center gap-1"><i class="fa-solid fa-money-bill-wave"></i> Tambah DP</button>
                    </div>
                </div>

                <div class="mb-4 border-t border-gray-700 pt-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Diskon</label>
                    <div class="flex gap-2">
                        <select name="discount_type" class="w-1/3 bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="percentage" <?= ($edit_inv['discount_type'] ?? 'percentage') == 'percentage' ? 'selected' : '' ?>>Persentase (%)</option>
                            <option value="nominal" <?= ($edit_inv['discount_type'] ?? '') == 'nominal' ? 'selected' : '' ?>>Nominal (Rp)</option>
                        </select>
                        <input type="number" name="discount_value" value="<?= ($edit_inv['discount_type'] ?? 'percentage') == 'nominal' ? ($edit_inv['discount_amount'] ?? 0) : ($edit_inv['discount_percentage'] ?? 0) ?>" class="flex-1 bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Contoh: 10 atau 50000" step="any" min="0">
                    </div>
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
                    div.className = 'flex gap-2 items-center';
                    div.innerHTML = `<span class="drag-handle cursor-move text-gray-500 p-2" title="Geser untuk urutkan"><i class="fa-solid fa-grip-vertical"></i></span>
                                   <input type="text" name="item_desc[]" list="item-suggestions" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                   <input type="number" name="item_amount[]" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Rp" required>
                                   <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-400 px-2" title="Hapus Item"><i class="fa-solid fa-trash"></i></button>`;
                    document.getElementById('invoice-items').appendChild(div);
                }
                function addDP() {
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center';
                    div.innerHTML = `<span class="drag-handle cursor-move text-gray-500 p-2" title="Geser untuk urutkan"><i class="fa-solid fa-grip-vertical"></i></span>
                                   <input type="text" name="item_desc[]" value="Down Payment (DP)" class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white" placeholder="Deskripsi Item" required>
                                   <input type="number" name="item_amount[]" class="w-24 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white text-right" placeholder="Contoh: -500000" required>
                                   <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-400 px-2" title="Hapus Item"><i class="fa-solid fa-trash"></i></button>`;
                    document.getElementById('invoice-items').appendChild(div);
                }

                // Inisialisasi SortableJS
                new Sortable(document.getElementById('invoice-items'), {
                    animation: 150,
                    handle: '.drag-handle', // Tentukan elemen handle untuk drag
                    ghostClass: 'bg-blue-900/30' // Kelas untuk item bayangan saat digeser
                });
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
                        // Hitung subtotal khusus untuk ditampilkan (Total layanan, abaikan DP dan Diskon)
                        $items_list = json_decode($row['items_json'], true);
                        $display_subtotal = 0;
                        $has_dp = false;
                        if (is_array($items_list)) {
                            foreach ($items_list as $itm) {
                                $amt = floatval($itm['amount']);
                                if (stripos($itm['desc'], 'Down Payment') !== false) {
                                    $has_dp = true;
                                } elseif ($amt >= 0) {
                                    $display_subtotal += $amt;
                                }
                            }
                        }
                    ?>
                    <tr class="hover:bg-gray-700/30">
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($row['invoice_number']) ?></div>
                            <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($row['invoice_date'])) ?></div>
                        </td>
                        <td class="p-4 text-white"><?= htmlspecialchars($row['client_name']) ?></td>
                        <td class="p-4">
                            <div class="font-bold text-green-400">Rp <?= number_format($display_subtotal, 0, ',', '.') ?></div>
                            <?php if($row['status'] == 'paid'): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-green-900 text-green-300 border border-green-700 mt-1">LUNAS</span>
                            <?php elseif($has_dp): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-900 text-yellow-300 border border-yellow-700 mt-1">BELUM LUNAS</span>
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