<?php
require_once 'header.php';

// --- VISITOR STATS LOGIC ---
// Pastikan tabel ada (Lazy migration)
$conn->query("CREATE TABLE IF NOT EXISTS visitor_stats (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, visit_date DATE NOT NULL, ip_address VARCHAR(45) NOT NULL, city VARCHAR(100), province VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

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

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-white">Dashboard</h1>
                        <p class="text-gray-500 mt-1">Ringkasan statistik website Anda.</p>
                    </div>
                </div>
                
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
            </div>
        </main>
    </div>
</body>
</html>
