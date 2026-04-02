<?php
// Nyalakan error reporting untuk debugging jika fatal error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai Output Buffering (PENTING: Mencegah whitespace merusak PDF)
ob_start();

require_once '../koneksi_db.php';
require_once '../notifikasi_helper.php'; // Include helper notifikasi

// --- LOAD DOMPDF LIBRARY ---
// 1. Cek Composer (Vendor) terlebih dahulu
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 2. Jika Class Dompdf belum ada (berarti tidak ada di vendor), cek folder manual 'dompdf'
if (!class_exists('Dompdf\Dompdf') && file_exists(__DIR__ . '/../dompdf/autoload.inc.php')) {
    require_once __DIR__ . '/../dompdf/autoload.inc.php';
}

if (!class_exists('Dompdf\Dompdf')) {
    die("FATAL ERROR: Class Dompdf tidak ditemukan. Kemungkinan library belum terinstall via Composer. Coba jalankan 'composer require dompdf/dompdf' di terminal.");
}

// Fix: Pastikan class Options juga terload (Fallback untuk masalah autoloader/case-sensitive)
if (!class_exists('Dompdf\Options')) {
    $opt_paths = [
        '../dompdf/src/Options.php',
        'dompdf/src/Options.php',
        '../vendor/dompdf/dompdf/src/Options.php'
    ];
    foreach ($opt_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? 'view';

if ($id == 0) die("ID Invoice tidak valid.");

// Ambil Data Invoice
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) die("Invoice tidak ditemukan.");
$discount_percentage = floatval($inv['discount_percentage'] ?? 0);
$discount_type = $inv['discount_type'] ?? 'percentage';
$discount_amount_db = floatval($inv['discount_amount'] ?? 0);

$items = json_decode($inv['items_json'], true);

// Proses item terlebih dahulu untuk mengetahui apakah ada DP
$subtotal = 0;
$adjustments = 0;
$positive_items = [];
$adjustment_items = [];
$has_dp = false;

foreach ($items as $item) {
    $amount = floatval($item['amount']);
    $desc = $item['desc'];

    // Cek apakah item adalah DP berdasarkan deskripsi. Jika ya, paksa menjadi pengurang.
    if (stripos($desc, 'Down Payment') !== false) {
        $corrected_amount = -abs($amount);
        $adjustments += $corrected_amount;
        $item['amount'] = $corrected_amount;
        $adjustment_items[] = $item;
        $has_dp = true; // Tandai bahwa invoice ini mengandung DP
    } else {
        if ($amount >= 0) {
            $subtotal += $amount;
            $positive_items[] = $item;
        } else {
            $adjustments += $amount;
            $adjustment_items[] = $item;
        }
    }
}

$discount_amount_calc = ($discount_type == 'nominal') ? $discount_amount_db : ($subtotal * $discount_percentage) / 100;
$total_final = $subtotal - $discount_amount_calc + $adjustments;

// Load Settings for Bank & WA
$settings = [];
$res_settings = $conn->query("SELECT * FROM site_settings");
if ($res_settings) {
    while($row = $res_settings->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$bank_name = $settings['bank_name'] ?? 'Bank Central Asia (BCA)';
$bank_account = $settings['bank_account'] ?? '7975591638';
$bank_owner = $settings['bank_owner'] ?? 'Rizka Ruhayani Kistanto';

// Tentukan Teks dan Warna Status
$status_text = 'BELUM BAYAR';
$status_color = '#dc2626';
if ($inv['status'] == 'paid') {
    $status_text = 'LUNAS';
    $status_color = '#059669';
} elseif ($has_dp) {
    $status_text = 'BELUM LUNAS';
}

// Load Logo (Base64 agar aman di dompdf)
$logo_path = __DIR__ . '/../image/logoweb.png'; // Cek folder 'image'
if (!file_exists($logo_path)) {
    $logo_path = __DIR__ . '/../images/logoweb.png'; // Fallback ke 'images'
}

$logo_data = '';
if (file_exists($logo_path)) {
    $type = pathinfo($logo_path, PATHINFO_EXTENSION);
    $data = file_get_contents($logo_path);
    $logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// HTML Template untuk PDF (Converted to Table-based layout for Dompdf compatibility)
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @import url(\'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap\');
        @page { margin: 0px; }
        body { font-family: \'Poppins\', sans-serif; color: #334155; font-size: 12px; line-height: 1.3; margin: 0; background-color: #f8fafc; }
        .container { padding: 20px 30px; max-width: 800px; margin: 0 auto; background-color: #ffffff; }
        
        /* Header Section */
        .header-bg { background-color: #1e293b; color: white; padding: 20px 30px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        
        .company-name { font-size: 20px; font-weight: bold; margin: 0; letter-spacing: -0.5px; }
        .company-desc { font-size: 11px; color: #cbd5e1; margin-top: 3px; }
        
        .company-address { text-align: right; font-size: 11px; line-height: 1.3; color: #e2e8f0; }
        
        /* Content Padding */
        .content { padding: 20px; }
        
        /* Info Section */
        .info-table { width: 100%; margin-bottom: 20px; margin-top: 20px; }
        .info-table td { vertical-align: top; width: 50%; }
        
        .label-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #059669; font-weight: bold; margin-bottom: 5px; display: block; }
        .client-name { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0; }
        .client-detail { font-size: 12px; color: #64748b; margin: 1px 0; }
        
        .invoice-title { font-size: 28px; font-weight: 300; text-transform: uppercase; color: #cbd5e1; margin: 0 0 5px 0; text-align: right; }
        .invoice-detail-row { text-align: right; font-size: 12px; margin-bottom: 2px; }
        .invoice-detail-label { color: #94a3b8; margin-right: 10px; }
        .invoice-detail-value { color: #0f172a; font-weight: 600; font-family: monospace; }
        .highlight-red { color: #dc2626; }
        
        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: bold; letter-spacing: 1px; }
        .items-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #334155; }
        .item-name { font-weight: 600; color: #0f172a; display: block; margin-bottom: 2px; }
        .item-desc { font-size: 10px; color: #64748b; font-style: italic; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Summary Section */
        .summary-container { background-color: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { vertical-align: top; }
        
        .payment-info h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin: 0 0 5px 0; }
        .payment-info p { margin: 0 0 3px 0; font-size: 12px; color: #475569; }
        .bank-account { font-family: monospace; font-size: 14px; font-weight: bold; color: #059669; letter-spacing: -0.5px; }
        
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 4px 0; font-size: 12px; color: #64748b; }
        .totals-table .final td { border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 16px; font-weight: 800; color: #059669; }
        
        /* Footer */
        /* Signature */
        .signature-section { margin-top: 40px; text-align: right; }
        .signature-line { display: inline-block; width: 150px; border-bottom: 1px solid #cbd5e1; margin-bottom: 10px; }
        .signature-name { font-weight: bold; font-size: 13px; color: #0f172a; margin: 0; }
        .signature-role { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin: 0; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; background-color: #2563eb; color: white; text-align: center; padding: 15px 0; font-size: 11px; letter-spacing: 1px; }
        .footer a { color: white; text-decoration: none; font-weight: bold; }
        .footer a:hover { text-decoration: underline; }
                /* Paid Stamp */
        .stamp-paid {
            position: absolute;
            top: 120px;
            right: 40px;
            border: 3px solid #059669;
            color: #059669;
            font-size: 30px;
            font-weight: bold;
            padding: 5px 15px;
            text-transform: uppercase;
            letter-spacing: 4px;
            transform: rotate(-15deg);
            opacity: 0.8;
            z-index: 100;
        }
        .stamp-unpaid {
            position: absolute;
            top: 120px;
            right: 40px;
            border: 3px solid #dc2626;
            color: #dc2626;
            font-size: 30px;
            font-weight: bold;
            padding: 5px 15px;
            text-transform: uppercase;
            letter-spacing: 4px;
            transform: rotate(-15deg);
            opacity: 0.8;
            z-index: 100;
        }


    </style>
</head>
<body>';
// Logic for Paid Stamp (Diletakkan di luar string HTML)
if (isset($inv['status']) && $inv['status'] == 'paid') {
    $html .= '<div class="stamp-paid">LUNAS</div>';
} elseif ($has_dp) {
    $html .= '<div class="stamp-unpaid">BELUM LUNAS</div>';
}

$html .= '
    <!-- Header Background -->
    <div class="header-bg">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="company-name">Pratama Digitect</h1>
                    <p class="company-desc">White Label Event Partner</p>
                </td>
                <td class="company-address">
                    Jalan Anoa<br>
                    Kota Gorontalo, Gorontalo<br>
                    support@pratamadigitect.com<br>
                    +6285298122890
                </td>
            </tr>
        </table>
    </div>

    <div class="container">
        <!-- Info Section -->
        <table class="info-table">
            <tr>
                <td>
                    <span class="label-title">Ditagihkan Kepada</span>
                    <h2 class="client-name">' . $inv['client_name'] . '</h2>
                    <p class="client-detail">' . $inv['client_email'] . '</p>
                    <p class="client-detail">' . $inv['client_phone'] . '</p>
                </td>
                <td>
                    <h1 class="invoice-title">INVOICE</h1>
                    <div class="invoice-detail-row">
                        <span class="invoice-detail-label">Nomor:</span>
                        <span class="invoice-detail-value">' . $inv['invoice_number'] . '</span>
                    </div>
                    <div class="invoice-detail-row">
                        <span class="invoice-detail-label">Tanggal:</span>
                        <span class="invoice-detail-value">' . date('d M Y', strtotime($inv['invoice_date'])) . '</span>
                    </div>
                    <div class="invoice-detail-row">
                        <span class="invoice-detail-label">Jatuh Tempo:</span>
                        <span class="invoice-detail-value highlight-red">' . date('d M Y', strtotime($inv['due_date'])) . '</span>
                    </div>
                    <div class="invoice-detail-row">
                        <span class="invoice-detail-label">Status:</span>
                        <span class="invoice-detail-value" style="color: ' . $status_color . ';">' . $status_text . '</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="50%">Deskripsi Layanan</th>
                    <th width="15%" class="text-center">Qty</th>
                    <th width="20%" class="text-right">Harga</th>
                    <th width="15%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';

foreach ($positive_items as $item) {
    $amount = floatval($item['amount']);
    $html .= '
            <tr>
                <td>
                    <span class="item-name">' . htmlspecialchars($item['desc']) . '</span>
                </td>
                <td class="text-center">1</td>
                <td class="text-right">Rp ' . number_format($amount, 0, ',', '.') . '</td>
                <td class="text-right" style="font-weight:bold;">Rp ' . number_format($amount, 0, ',', '.') . '</td>
            </tr>';
}
$html .= '
            </tbody>
        </table>

        <!-- Summary & Payment -->
        <div class="summary-container">
            <table class="summary-table">
                <tr>
                    <td width="55%">
                        <div class="payment-info">
                            <h4>Metode Pembayaran</h4>
                            <p>' . htmlspecialchars($bank_name) . '</p>
                            <p class="bank-account">' . htmlspecialchars($bank_account) . '</p>
                            <p style="font-size:11px; font-style:italic;">A/N ' . htmlspecialchars($bank_owner) . '</p>
                        </div>
                    </td>
                    <td width="5%"></td>
                    <td width="40%">
                        <table class="totals-table">
            <tr>
                                <td>Subtotal</td>
                                <td class="text-right">Rp ' . number_format($subtotal, 0, ',', '.') . '</td>
            </tr>';
if ($discount_amount_calc > 0) {
    $discount_label = ($discount_type == 'percentage') ? 'Diskon (' . rtrim(rtrim(number_format($discount_percentage, 2, '.', ''), '0'), '.') . '%)' : 'Diskon';
    $html .= '
            <tr>
                <td>' . $discount_label . '</td>
                <td class="text-right" style="color: #dc2626;">- Rp ' . number_format($discount_amount_calc, 0, ',', '.') . '</td>
            </tr>';
}

foreach ($adjustment_items as $item) {
    $amount = floatval($item['amount']);
    $html .= '
            <tr>
                <td>' . htmlspecialchars($item['desc']) . '</td>
                <td class="text-right" style="color: #dc2626;">- Rp ' . number_format(abs($amount), 0, ',', '.') . '</td>
            </tr>';
}

$html .= '
            <tr class="final">
                                <td>Total/Sisa Bayar </td>
                                <td class="text-right">Rp ' . number_format($total_final, 0, ',', '.') . '</td>
                            </tr>
        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Footer (Dipindahkan keluar container agar full width) -->
    <div class="footer">
        <a href="https://wa.me/6285298122890">Pratama Digitect</a> &bull; Dicetak pada: ' . date('d M Y H:i') . '<br>
        <a href="https://' . $_SERVER['HTTP_HOST'] . '" target="_blank">' . ((strpos($_SERVER['HTTP_HOST'], 'www.') === 0) ? $_SERVER['HTTP_HOST'] : 'www.' . $_SERVER['HTTP_HOST']) . '</a>
    </div>
</body>
</html>';

// Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf_content = $dompdf->output();

// Bersihkan buffer output sebelum mengirim header PDF
ob_end_clean();

if ($action == 'view') {
    // Tampilkan di Browser
    $dompdf->stream("Invoice-" . $inv['invoice_number'] . ".pdf", ["Attachment" => false]);

} elseif ($action == 'email') {
    // Kirim Email dengan Attachment
    $to = $inv['client_email'];
    $subject = "Tagihan Invoice #" . $inv['invoice_number'];
    $message = "Halo " . $inv['client_name'] . ",\n\nTerlampir invoice untuk layanan kami. Mohon segera melakukan pembayaran sebelum tanggal jatuh tempo.\n\nTerima kasih.";
    $namaPenerima = $inv['client_name'];
    
    // Menggunakan fungsi kirimEmail dari notifikasi_helper.php yang menggunakan PHPMailer
    // Note: Fungsi kirimEmail di notifikasi_helper.php saat ini belum mendukung attachment secara langsung via parameter.
    // Namun, untuk solusi cepat, kita bisa memodifikasi notifikasi_helper.php atau menggunakan PHPMailer langsung di sini.
    // Mengingat notifikasi_helper.php sudah meload PHPMailer, kita bisa instansiasi manual di sini untuk attachment.
    
    // Opsi Terbaik: Gunakan PHPMailer manual di sini karena butuh attachment string (PDF content)
    // Kita gunakan kredensial dari kunci_rahasia.php yang sudah di-load via notifikasi_helper.php
    
    global $smtp_host, $smtp_user, $smtp_pass, $smtp_port; // Dari kunci_rahasia.php

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($smtp_user, 'Pratama Digitect Finance');
        $mail->addAddress($to, $namaPenerima);

        // Attachments
        $mail->addStringAttachment($pdf_content, "Invoice-" . $inv['invoice_number'] . ".pdf");

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        echo "<script>alert('Invoice berhasil dikirim ke email!'); window.location='index.php?action=invoices';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Gagal mengirim email. Error: {$mail->ErrorInfo}'); window.location='index.php?action=invoices';</script>";
    }
} elseif ($action == 'whatsapp') {
    // Kirim Notifikasi WhatsApp
    // Format nomor HP (hapus karakter non-angka, ganti 0 di depan dengan 62)
    $to = preg_replace('/[^0-9]/', '', $inv['client_phone']);
    if (substr($to, 0, 1) == '0') $to = '62' . substr($to, 1);

    $link_invoice = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/view_invoice.php?id=" . $id;
    
    // --- Membangun pesan WhatsApp yang lebih detail ---
    $details_text = "Subtotal: Rp " . number_format($subtotal, 0, ',', '.') . "\n";

    if ($discount_amount_calc > 0) {
        $discount_label = ($discount_type == 'percentage') ? 'Diskon (' . rtrim(rtrim(number_format($discount_percentage, 2, '.', ''), '0'), '.') . '%)' : 'Diskon';
        $details_text .= $discount_label . ": - Rp " . number_format($discount_amount_calc, 0, ',', '.') . "\n";
    }

    foreach ($adjustment_items as $item) {
        $amount = floatval($item['amount']);
        $desc = htmlspecialchars($item['desc']);
        // Tampilkan sebagai pengurang
        $details_text .= $desc . ": - Rp " . number_format(abs($amount), 0, ',', '.') . "\n";
    }
    $details_text = rtrim($details_text);

    $bank_info_text = $bank_name . "\nNo. Rekening: *" . $bank_account . "*\nA/N: " . $bank_owner;

    $default_wa = "Halo *[CLIENT_NAME]*,\n\nBerikut adalah rincian tagihan Anda untuk invoice *#[INVOICE_NUMBER]*:\n\n[DETAILS]\n--------------------\n*Sisa Bayar: Rp [TOTAL_SISA]*\n\nMetode Pembayaran:\n[BANK_INFO]\n\nJatuh Tempo: *[DUE_DATE]*\n\nUntuk detail lengkap dan pembayaran, silakan akses link berikut:\n[LINK_INVOICE]\n\nTerima kasih.";
    $wa_template = $settings['wa_template'] ?? $default_wa;

    $pesan = str_replace(
        ['[CLIENT_NAME]', '[INVOICE_NUMBER]', '[DETAILS]', '[TOTAL_SISA]', '[BANK_INFO]', '[DUE_DATE]', '[LINK_INVOICE]'],
        [
            $inv['client_name'], 
            $inv['invoice_number'], 
            $details_text, 
            number_format($total_final, 0, ',', '.'), 
            $bank_info_text, 
            date('d M Y', strtotime($inv['due_date'])), 
            $link_invoice
        ],
        $wa_template
    );

    // Redirect langsung ke WhatsApp Web / App
    $wa_url = "https://wa.me/" . $to . "?text=" . urlencode($pesan);
    header("Location: " . $wa_url);
    exit;
}
?>