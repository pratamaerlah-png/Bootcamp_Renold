<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../koneksi_db.php';

$id = $_GET['id'] ?? 0;
if ($id == 0) die("ID Invoice tidak valid.");

// Ambil data invoice untuk judul dan deskripsi
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) die("Invoice tidak ditemukan.");

// Bangun URL absolut ke PDF view
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']);
$pdf_url = "{$protocol}://{$host}{$path}/generate_invoice.php?id={$id}&action=view";

// Gunakan layanan mShots (dari WordPress) untuk membuat screenshot on-the-fly
$image_preview_url = "https://s0.wp.com/mshots/v1/" . urlencode($pdf_url) . "?w=1200&h=900";

$page_title = "Invoice #" . htmlspecialchars($inv['invoice_number']);
$page_description = "Tagihan untuk " . htmlspecialchars($inv['client_name']) . " sebesar Rp " . number_format($inv['total_amount'], 0, ',', '.') . ". Jatuh tempo: " . date('d M Y', strtotime($inv['due_date']));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>

    <!-- Meta Tags untuk WhatsApp & Social Media Preview -->
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= $page_description ?>">
    <meta property="og:image" content="<?= htmlspecialchars($image_preview_url) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="900">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= "{$protocol}://{$host}{$_SERVER['REQUEST_URI']}" ?>">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Redirect otomatis ke PDF setelah 2 detik -->
    <meta http-equiv="refresh" content="2;url=<?= htmlspecialchars($pdf_url) ?>">

    <style>
        body { font-family: sans-serif; background-color: #111827; color: #d1d5db; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .container { padding: 20px; }
        h1 { color: #fff; }
        a { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mempersiapkan Invoice Anda...</h1>
        <p>Anda akan diarahkan secara otomatis.</p>
        <p>Jika tidak ter-redirect, silakan <a href="<?= htmlspecialchars($pdf_url) ?>">klik di sini</a>.</p>
    </div>
</body>
</html>