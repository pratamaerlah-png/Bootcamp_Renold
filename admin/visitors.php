<?php
require_once 'header.php';

// Hanya admin yang boleh lihat
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='p-8'><div class='bg-red-900/20 text-red-400 p-4 rounded-lg border border-red-500/30'>Akses Ditolak. Hanya Administrator yang dapat mengakses halaman ini.</div></div>";
    exit;
}

// --- LOGIC ---
// Pagination and Limit
$limit = $_GET['limit'] ?? 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$limit_sql = (int)$limit;
if ($limit === 'all') {
    $limit_sql = 999999; // A large number for 'all'
}
$offset = ($page - 1) * $limit_sql;

// Get total records
$total_records_res = $conn->query("SELECT COUNT(*) as total FROM visitor_stats");
$total_records = $total_records_res->fetch_assoc()['total'];
$total_pages = ($limit === 'all') ? 1 : ceil($total_records / $limit_sql);

// Grouping data
$group_result = $conn->query("SELECT province, city, COUNT(*) as total FROM visitor_stats WHERE province IS NOT NULL AND province != '' GROUP BY province, city ORDER BY province ASC, total DESC");
$grouped_data = [];
while ($row = $group_result->fetch_assoc()) {
    $grouped_data[$row['province']][] = ['city' => $row['city'], 'total' => $row['total']];
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Data Pengunjung Website</h1>
        <p class="text-gray-500 mt-1">Daftar pengunjung unik berdasarkan IP Address dan estimasi lokasi.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Kolom Kiri: Ringkasan Lokasi -->
    <div class="lg:col-span-1">
        <div class="bg-gray-800 rounded-xl shadow-xl border border-gray-700 p-6 sticky top-6">
            <h3 class="text-lg font-bold text-white mb-4">Ringkasan Pengunjung per Lokasi</h3>
            <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <?php if (!empty($grouped_data)): ?>
                    <?php foreach ($grouped_data as $province => $cities): ?>
                        <details class="group">
                            <summary class="flex justify-between items-center cursor-pointer list-none">
                                <span class="font-bold text-blue-400"><?= htmlspecialchars($province) ?></span>
                                <span class="text-xs text-gray-500 group-open:rotate-180 transition-transform"><i class="fa-solid fa-chevron-down"></i></span>
                            </summary>
                            <div class="pl-4 mt-2 border-l-2 border-gray-700 space-y-1">
                                <?php foreach ($cities as $city_data): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-300"><?= htmlspecialchars($city_data['city']) ?></span>
                                        <span class="font-mono text-gray-500"><?= $city_data['total'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Data lokasi belum tersedia.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Log Detail -->
    <div class="lg:col-span-2">
        <!-- Kontrol View & Pagination -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-400">Tampilkan:</span>
                <?php $limits = [20, 50, 100, 'all']; ?>
                <?php foreach ($limits as $l): ?>
                    <a href="?limit=<?= $l ?>" class="<?= ($limit == $l) ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?> px-3 py-1 rounded font-bold"><?= ucfirst($l) ?></a>
                <?php endforeach; ?>
            </div>
            <!-- Pagination Links -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center gap-1 text-sm">
                <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>" class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded font-bold <?= ($page <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <span class="text-gray-400 px-2">Hal <?= $page ?> dari <?= $total_pages ?></span>
                <a href="?page=<?= min($total_pages, $page + 1) ?>&limit=<?= $limit ?>" class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded font-bold <?= ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Table List -->
        <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold tracking-wider">
                        <tr>
                            <th class="p-5 border-b border-gray-700">Waktu Kunjungan</th>
                            <th class="p-5 border-b border-gray-700">IP Address</th>
                            <th class="p-5 border-b border-gray-700">Kota</th>
                            <th class="p-5 border-b border-gray-700">Provinsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php
                        $result = $conn->query("SELECT * FROM visitor_stats ORDER BY created_at DESC LIMIT $limit_sql OFFSET $offset");
                        if ($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr class="hover:bg-gray-700/30 transition">
                            <td class="p-4 text-sm text-gray-400 whitespace-nowrap"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                            <td class="p-4 text-gray-300 font-mono text-xs"><?= htmlspecialchars($row['ip_address']) ?></td>
                            <td class="p-4 font-bold text-white"><?= htmlspecialchars($row['city'] ?? 'N/A') ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($row['province'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endwhile; 
                        else: ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500">Belum ada data pengunjung.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

            </div>
        </main>
    </div>
</body>
</html>