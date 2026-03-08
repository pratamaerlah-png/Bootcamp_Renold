<?php
require_once 'koneksi_db.php';

// Ambil SEMUA data Event (Limit besar)
$sql_lari = "SELECT * FROM events WHERE category='lari' AND status = 'upcoming' ORDER BY event_date ASC LIMIT 100";
$result_lari = $conn->query($sql_lari);

$sql_konser = "SELECT * FROM events WHERE category='konser' AND status = 'upcoming' ORDER BY event_date ASC LIMIT 100";
$result_konser = $conn->query($sql_konser);

$sql_selesai = "SELECT * FROM events WHERE status = 'completed' ORDER BY event_date DESC LIMIT 100";
$result_selesai = $conn->query($sql_selesai);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Event - Pratama Digitect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-black text-gray-300 font-sans">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 bg-black/90 backdrop-blur-sm border-b border-white/10">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="index.php" class="text-xl font-bold text-white flex items-center gap-2 hover:text-blue-500 transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Home
            </a>
            <div class="text-blue-600 font-bold hidden md:block">Pratama Digitect Events</div>
        </div>
    </nav>

    <!-- Header -->
    <header class="pt-32 pb-12 text-center px-6">
        <h1 class="text-4xl font-bold text-white mb-4">Daftar Semua Event</h1>
        <p class="text-gray-400">Temukan event lari dan konser terbaik untuk Anda.</p>
        
        <!-- Tabs -->
        <div class="flex justify-center mt-8">
            <div class="bg-gray-900 p-1 rounded-lg inline-flex border border-white/10">
                <button onclick="switchTab('lari')" id="tab-lari" class="px-6 py-2 rounded-md text-sm font-bold bg-blue-600 text-white shadow-sm transition-all">Event Lari</button>
                <button onclick="switchTab('konser')" id="tab-konser" class="px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all">Event Konser</button>
                <button onclick="switchTab('selesai')" id="tab-selesai" class="px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all">Selesai</button>
            </div>
        </div>
    </header>

    <!-- Content Grid -->
    <div class="container mx-auto px-6 pb-20">
        
        <!-- Helper Function untuk Card -->
        <?php
        function renderEventCard($row, $type) {
            $status_label = ($row['status'] == 'upcoming') ? 'Open Registration' : (($row['status'] == 'completed') ? 'Selesai' : 'Coming Soon');
            $badge_color = ($row['status'] == 'upcoming') ? ($type == 'lari' ? 'bg-green-500' : 'bg-purple-500') : 'bg-gray-500';
            
            $link_href = !empty($row['event_link']) ? $row['event_link'] : "detail.php?slug=" . $row['slug'];
            $link_target = !empty($row['event_link']) ? "_blank" : "_self";

            $display_image = !empty($row['banner_image']) ? $row['banner_image'] : '';
            if (empty($display_image) && !empty($row['event_link'])) {
                $url_target = $row['event_link'];
                if (!preg_match("~^(?:f|ht)tps?://~i", $url_target)) $url_target = "https://" . $url_target;
                $display_image = "https://s0.wp.com/mshots/v1/" . urlencode($url_target) . "?w=800&h=600";
            }
            if (empty($display_image)) $display_image = "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60";
            
            // Di halaman ini kita pakai ukuran standar grid
            ?>
            <div class="group bg-gray-900 rounded-lg shadow-lg hover:shadow-blue-500/20 transition-shadow duration-300 border border-white/10 overflow-hidden w-[31%] md:w-[23%] lg:w-[18%]">
                <div class="relative h-24 md:h-48 overflow-hidden">
                    <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full h-full">
                        <img src="<?= htmlspecialchars($display_image) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=60';">
                        <div class="absolute top-2 right-2 <?= $badge_color ?> text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $status_label ?></div>
                    </a>
                </div>
                <div class="p-2 md:p-3">
                    <div class="text-[10px] md:text-xs <?= $type == 'lari' ? 'text-blue-500' : ($type == 'konser' ? 'text-purple-500' : 'text-gray-500') ?> font-semibold mb-1"><?= date('d M Y', strtotime($row['event_date'])) ?></div>
                    <h3 class="text-xs md:text-sm font-bold mb-1 text-white line-clamp-3"><?= htmlspecialchars($row['title']) ?></h3>
                    <p class="text-gray-400 text-[10px] md:text-xs mb-3 truncate"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($row['location']) ?></p>
                    <?php if($row['status'] == 'upcoming'): ?>
                        <a href="<?= htmlspecialchars($link_href) ?>" target="<?= $link_target ?>" class="block w-full text-center <?= $type == 'lari' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-purple-600 hover:bg-purple-700' ?> text-white font-bold text-xs py-2 rounded transition">Daftar</a>
                    <?php else: ?>
                        <div class="block w-full text-center bg-gray-800 text-gray-500 font-bold text-xs py-2 rounded cursor-default">Selesai</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        ?>

        <!-- Tab Lari -->
        <div id="content-lari" class="flex flex-wrap justify-center gap-4">
            <?php if ($result_lari->num_rows > 0): ?>
                <?php while($row = $result_lari->fetch_assoc()) renderEventCard($row, 'lari'); ?>
            <?php else: ?>
                <p class="text-gray-500 py-12">Tidak ada event lari.</p>
            <?php endif; ?>
        </div>

        <!-- Tab Konser -->
        <div id="content-konser" class="hidden flex flex-wrap justify-center gap-4">
            <?php if ($result_konser->num_rows > 0): ?>
                <?php while($row = $result_konser->fetch_assoc()) renderEventCard($row, 'konser'); ?>
            <?php else: ?>
                <p class="text-gray-500 py-12">Tidak ada event konser.</p>
            <?php endif; ?>
        </div>

        <!-- Tab Selesai -->
        <div id="content-selesai" class="hidden flex flex-wrap justify-center gap-4">
            <?php if ($result_selesai->num_rows > 0): ?>
                <?php while($row = $result_selesai->fetch_assoc()) renderEventCard($row, 'selesai'); ?>
            <?php else: ?>
                <p class="text-gray-500 py-12">Belum ada event selesai.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-black text-gray-400 py-8 border-t border-white/10 text-center text-sm">
        &copy; <?= date('Y') ?> Pratama Digitect Systems.
    </footer>

    <script>
        function switchTab(category) {
            const tabs = ['lari', 'konser', 'selesai'];
            tabs.forEach(tab => {
                const content = document.getElementById(`content-${tab}`);
                const button = document.getElementById(`tab-${tab}`);
                if (content && button) {
                    if (tab === category) {
                        content.classList.remove('hidden');
                        button.className = "px-6 py-2 rounded-md text-sm font-bold bg-blue-600 text-white shadow-sm transition-all";
                    } else {
                        content.classList.add('hidden');
                        button.className = "px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all";
                    }
                }
            });
        }
    </script>
</body>
</html>