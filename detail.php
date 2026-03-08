<?php
require_once 'koneksi_db.php';

// Ambil slug dari URL
$slug = $_GET['slug'] ?? '';

// Query Event berdasarkan Slug
$stmt = $conn->prepare("SELECT * FROM events WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

// Jika event tidak ditemukan, kembalikan ke index
if (!$event) {
    header("Location: index.php");
    exit;
}

// Ambil Kategori Tiket
$stmt_ticket = $conn->prepare("SELECT * FROM ticket_categories WHERE event_id = ?");
$stmt_ticket->bind_param("i", $event['id']);
$stmt_ticket->execute();
$tickets = $stmt_ticket->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> - Pratama Digitect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-black text-gray-300 font-sans">

    <!-- Navbar Sederhana -->
    <nav class="fixed w-full z-50 bg-black/90 backdrop-blur-sm border-b border-white/10">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="index.php" class="text-xl font-bold text-white flex items-center gap-2 hover:text-blue-500 transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
            <div class="text-blue-600 font-bold hidden md:block">Pratama Digitect Event Platform</div>
        </div>
    </nav>

    <!-- Hero Event -->
    <header class="relative h-[50vh] min-h-[400px]">
        <img src="<?= htmlspecialchars($event['banner_image']) ?>" class="w-full h-full object-cover opacity-60">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
        <div class="absolute bottom-0 left-0 w-full p-8 md:p-16">
            <div class="container mx-auto">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-600 text-white mb-4 inline-block uppercase tracking-wider"><?= $event['category'] ?></span>
                <h1 class="text-4xl md:text-6xl font-bold text-white mb-4"><?= htmlspecialchars($event['title']) ?></h1>
                <div class="flex flex-wrap gap-6 text-gray-300">
                    <div class="flex items-center gap-2"><i class="fa-regular fa-calendar text-blue-500"></i> <?= date('d F Y, H:i', strtotime($event['event_date'])) ?> WIB</div>
                    <div class="flex items-center gap-2"><i class="fa-solid fa-location-dot text-red-500"></i> <?= htmlspecialchars($event['location']) ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Grid -->
    <div class="container mx-auto px-6 py-12">
        <div class="grid md:grid-cols-3 gap-12">
            
            <!-- Kolom Kiri: Deskripsi -->
            <div class="md:col-span-2">
                <h2 class="text-2xl font-bold text-white mb-6">Tentang Event</h2>
                <div class="prose prose-invert max-w-none text-gray-400 leading-relaxed">
                    <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                </div>
            </div>

            <!-- Kolom Kanan: Tiket -->
            <div>
                <div class="bg-gray-900 rounded-xl p-6 border border-white/10 sticky top-24 shadow-2xl">
                    <h3 class="text-xl font-bold text-white mb-6 border-b border-gray-700 pb-4">Pilih Tiket</h3>
                    
                    <div class="space-y-4">
                        <?php if ($tickets->num_rows > 0): ?>
                            <?php while($ticket = $tickets->fetch_assoc()): ?>
                                <div class="border border-gray-700 rounded-lg p-4 hover:border-blue-500 transition bg-gray-800 group">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h4 class="font-bold text-white group-hover:text-blue-400 transition"><?= htmlspecialchars($ticket['name']) ?></h4>
                                            <p class="text-xs text-gray-500">Sisa Kuota: <?= $ticket['quota'] - $ticket['sold'] ?></p>
                                        </div>
                                        <div class="text-blue-400 font-bold">IDR <?= number_format($ticket['price'], 0, ',', '.') ?></div>
                                    </div>
                                    <button class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded text-sm transition shadow-lg shadow-blue-500/20">
                                        Beli Tiket
                                    </button>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 bg-gray-800 rounded-lg border border-dashed border-gray-700">
                                <p class="text-gray-500">Tiket belum tersedia untuk saat ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-800 text-center">
                        <p class="text-xs text-gray-500 mb-2">Pembayaran aman didukung oleh</p>
                        <div class="flex justify-center gap-4 text-2xl text-gray-600">
                            <i class="fa-brands fa-cc-visa hover:text-white transition"></i>
                            <i class="fa-brands fa-cc-mastercard hover:text-white transition"></i>
                            <i class="fa-solid fa-wallet hover:text-white transition"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>