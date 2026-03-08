<?php
// notifikasi_helper.php (Versi Final dengan Instalasi Manual)

// Memuat library PHPMailer secara manual
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// Memuat file kredensial Anda
require_once __DIR__ . '/../kunci_rahasia.php';

// =================================================================
// === FUNGSI PENGIRIMAN EMAIL =====================================
// =================================================================

function kirimEmail($tujuan, $namaPenerima, $subjek, $isiPesan) {
    // PERBAIKAN: Memastikan menggunakan nama variabel yang sama dengan kunci_rahasia.php
    global $smtp_host, $smtp_user, $smtp_pass, $smtp_port;

    if (empty($smtp_host) || empty($smtp_user) || empty($smtp_pass) || empty($smtp_port)) {
        write_log_email("Gagal: Konfigurasi SMTP tidak lengkap atau nama variabel salah.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Pengaturan Server SMTP
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';

        // Pengaturan Pengirim dan Penerima
        $mail->setFrom($smtp_user, 'Panitia Gorontalo Half Marathon');
        $mail->addAddress($tujuan, $namaPenerima);
        $mail->addReplyTo($smtp_user, 'Informasi');

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body    = $isiPesan;
        $mail->AltBody = strip_tags($isiPesan);

        $mail->send();
        
        write_log_email("Email berhasil dikirim ke: {$tujuan} | Subjek: {$subjek}");
        return true;
    } catch (Exception $e) {
        write_log_email("Gagal mengirim email ke: {$tujuan}. PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function write_log_email($message) {
    file_put_contents(__DIR__ . '/email_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}


// =================================================================
// === FUNGSI PENGIRIMAN WHATSAPP ==================================
// =================================================================

// PERBAIKAN 1: Pindahkan fungsi log ke luar dan letakkan sebelum fungsi utama.
function write_log_whatsapp($message) {
    $log_file = __DIR__ . '/whatsapp_log.txt';
    $log_dir = __DIR__;
    
    // Cek apakah direktori bisa ditulisi sebelum mencoba
    if (is_writable($log_dir)) {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    } else {
        // Jika tidak bisa, kirim error ke log utama PHP daripada membuat fatal error
        error_log("PERINGATAN DARI NOTIFIKASI HELPER: Direktori log tidak dapat ditulisi. Pesan: " . $message);
    }
}


function kirimWhatsApp($koneksi, $nomorTujuan, $pesan) {
    // Memuat token backup dari file
    global $fonnte_tokens, $wa_gateway_url, $wa_gateway_key; 

    // 1. Ambil Token Device UTAMA dari Database
    $stmt = $koneksi->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = ?");
    $setting_key = 'fonnte_token';
    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $pengaturan = $result->fetch_assoc();
    $stmt->close();
    $token_utama_db = $pengaturan['setting_value'] ?? null;

    // 2. Siapkan daftar token untuk dicoba
    $tokens_to_try = [];
    if (!empty($token_utama_db)) {
        $tokens_to_try[] = $token_utama_db;
    }
    if (!empty($fonnte_tokens) && is_array($fonnte_tokens)) {
        foreach ($fonnte_tokens as $backup_token) {
            if ($backup_token !== $token_utama_db) {
                $tokens_to_try[] = $backup_token;
            }
        }
    }

    if (empty($tokens_to_try)) {
        write_log_whatsapp("Gagal: Tidak ada token yang tersedia (baik di DB maupun di file).");
        return false;
    }

    // PERBAIKAN 2 (REKOMENDASI): Menggunakan fungsi format nomor yang lebih baik
    $nomorTujuanFormatted = preg_replace('/[^0-9]/', '', $nomorTujuan); // Hapus semua selain angka
    if (substr($nomorTujuanFormatted, 0, 1) === '0') {
        $nomorTujuanFormatted = '62' . substr($nomorTujuanFormatted, 1);
    }

    // --- LOGIKA BARU: PRIORITASKAN CUSTOM VPS GATEWAY ---
    if (!empty($wa_gateway_url)) {
        $payload = [
            'sessionId' => $token_utama_db, // TAMBAHAN: Kirim ID Sesi agar VPS tahu pengirimnya siapa
            'number' => $nomorTujuanFormatted, // Format umum whatsapp-web.js
            'message' => $pesan,
            'target' => $nomorTujuanFormatted // Fallback field
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $wa_gateway_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "x-api-key: " . ($wa_gateway_key ?? '') // Header keamanan
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($http_code == 200 && !$error) {
            write_log_whatsapp("Sukses kirim WA via VPS ke {$nomorTujuanFormatted}.");
            
            // --- TAMBAHAN: SIMPAN KE RIWAYAT AI AGAR BOT PAHAM KONTEKS ---
            try {
                $stmt_hist = $koneksi->prepare("INSERT INTO ai_chat_history (session_id, role, message) VALUES (?, 'model', ?)");
                $stmt_hist->bind_param("ss", $nomorTujuanFormatted, $pesan);
                $stmt_hist->execute();
                $stmt_hist->close();
            } catch (Exception $e) { /* Abaikan error log history agar tidak ganggu notif */ }
            
            return true;
        } else {
            write_log_whatsapp("Gagal kirim via VPS. Error: $error. Response: $response. Mencoba fallback ke Fonnte...");
            // Jangan return false, biarkan lanjut ke Fonnte sebagai backup
        }
    }

    // 3. Loop melalui setiap token yang tersedia
    foreach ($tokens_to_try as $index => $token) {
        $payload = ['target' => $nomorTujuanFormatted, 'message' => $pesan];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.fonnte.com/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ["Authorization: " . $token],
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            write_log_whatsapp("Gagal (cURL) dengan Token ke-" . ($index + 1) . ". Mencoba token berikutnya. Error: " . $error);
            continue; 
        }
        
        $response_data = json_decode($response, true);
        
        if ($response_data && isset($response_data['status']) && $response_data['status'] === true) {
            $sumber_token = ($index == 0 && !empty($token_utama_db)) ? "Database (Utama)" : "File (Backup)";
            write_log_whatsapp("Sukses kirim WA ke {$nomorTujuanFormatted} menggunakan Token dari {$sumber_token}.");
            
            // --- TAMBAHAN: SIMPAN KE RIWAYAT AI ---
            try {
                $stmt_hist = $koneksi->prepare("INSERT INTO ai_chat_history (session_id, role, message) VALUES (?, 'model', ?)");
                $stmt_hist->bind_param("ss", $nomorTujuanFormatted, $pesan);
                $stmt_hist->execute();
                $stmt_hist->close();
            } catch (Exception $e) { }
            
            return true;
        } else {
            $error_detail = $response_data['reason'] ?? $response;
            write_log_whatsapp("Gagal (API) dengan Token ke-" . ($index + 1) . ". Mencoba token berikutnya. Respon Fonnte: " . $error_detail);
        }
    }

    // PERBAIKAN 3: Hapus definisi fungsi dari sini
    // Jika semua token sudah dicoba dan tidak ada yang berhasil
    write_log_whatsapp("Gagal kirim WA ke {$nomorTujuanFormatted}: Semua token gagal.");
    return false;
}

// FUNGSI BARU: KIRIM EFEK MENGETIK (TYPING...)
function kirimTyping($koneksi, $nomorTujuan) {
    global $wa_gateway_url, $wa_gateway_key;
    
    // Hanya bekerja jika menggunakan VPS Gateway
    if (empty($wa_gateway_url)) return false;

    // Ambil Token Utama
    $stmt = $koneksi->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'fonnte_token'");
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc()['setting_value'] ?? null;
    $stmt->close();

    if (!$token) return false;

    $nomorFormatted = preg_replace('/[^0-9]/', '', $nomorTujuan);
    if (substr($nomorFormatted, 0, 1) === '0') $nomorFormatted = '62' . substr($nomorFormatted, 1);

    // Ubah URL /send menjadi /send-typing
    $base_url = preg_replace('/\/send\/?$/', '', $wa_gateway_url);
    $url = rtrim($base_url, '/') . '/send-typing';

    $payload = [
        'sessionId' => $token,
        'number' => $nomorFormatted
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-api-key: " . ($wa_gateway_key ?? '')],
        CURLOPT_TIMEOUT => 1 // Timeout sangat singkat (fire and forget) agar tidak memperlambat bot
    ]);
    curl_exec($ch);
    curl_close($ch);
    return true;
}
?>