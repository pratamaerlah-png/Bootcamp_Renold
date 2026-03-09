<?php
require_once 'header.php';

// Hanya admin yang boleh lihat log
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='p-8'><div class='bg-red-900/20 text-red-400 p-4 rounded-lg border border-red-500/30'>Akses Ditolak.</div></div>";
    exit;
}

// Pastikan tabel activity_logs ada (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    username VARCHAR(50),
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Log Aktivitas User</h1>
        <p class="text-gray-500 mt-1">Rekam jejak semua aktivitas di panel admin.</p>
    </div>
</div>

<!-- Table List -->
<div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-black/50 text-gray-400 uppercase text-xs font-bold tracking-wider">
                <tr>
                    <th class="p-5 border-b border-gray-700">Waktu</th>
                    <th class="p-5 border-b border-gray-700">User</th>
                    <th class="p-5 border-b border-gray-700">Aktivitas</th>
                    <th class="p-5 border-b border-gray-700">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php
                $result = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 200"); // Limit 200 log terbaru
                if ($result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                ?>
                <tr class="hover:bg-gray-700/30 transition">
                    <td class="p-4 text-sm text-gray-400 whitespace-nowrap"><?= date('d M Y, H:i:s', strtotime($row['created_at'])) ?></td>
                    <td class="p-4 font-bold text-white"><?= htmlspecialchars($row['username']) ?></td>
                    <td class="p-4 text-gray-300"><?= htmlspecialchars($row['action']) ?></td>
                    <td class="p-4 text-gray-500 font-mono text-xs"><?= htmlspecialchars($row['ip_address']) ?></td>
                </tr>
                <?php endwhile; 
                else: ?>
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-500">Belum ada aktivitas tercatat.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

            </div>
        </main>
    </div>
</body>
</html>