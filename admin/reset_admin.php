<?php
// /Volumes/Data Project/Penawaran website Event Lari/Bootcamp_Renold/admin/reset_admin.php

require '../koneksi_db.php';

// Password baru yang diinginkan
$password_baru = 'admin123';
$password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
$username = 'admin';

echo "<h1>Reset Password Admin</h1>";

// 1. Cek apakah user 'admin' sudah ada?
$cek_user = $conn->query("SELECT id FROM users WHERE username = '$username'");

if ($cek_user->num_rows > 0) {
    // A. Jika ada, UPDATE passwordnya
    $sql = "UPDATE users SET password = '$password_hash', role = 'admin' WHERE username = '$username'";
    if ($conn->query($sql)) {
        echo "<p style='color:green'>BERHASIL! Password dan Role untuk user <b>'$username'</b> telah diubah/dipastikan menjadi Admin. Password baru: <b>$password_baru</b></p>";
    } else {
        echo "<p style='color:red'>GAGAL Update: " . $conn->error . "</p>";
    }
} else {
    // B. Jika tidak ada, BUAT user baru
    $email = 'admin@pratama.com';
    $sql = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password_hash', 'admin')";
    if ($conn->query($sql)) {
        echo "<p style='color:green'>BERHASIL! User <b>'$username'</b> tidak ditemukan, jadi dibuat baru dengan password: <b>$password_baru</b></p>";
    } else {
        echo "<p style='color:red'>GAGAL Buat User: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<p>Silakan login kembali di <a href='admin/index.php'>Halaman Admin</a>.</p>";
echo "<p><b>PENTING:</b> Hapus file <code>reset_admin.php</code> ini setelah berhasil login agar aman.</p>";
?>
