<?php
session_start();
require_once '../koneksi_db.php'; 

// --- AUTO UPDATE STATUS ---
$conn->query("UPDATE events SET status = 'completed' WHERE event_date < NOW() AND status = 'upcoming'");

// --- LOGIC: LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- LOGIC: LOGIN ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['do_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            header("Location: index.php");
            exit;
        }
    }
    $login_error = "Username atau Password salah!";
}

// Jika belum login, tampilkan Form Login
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Admin - Pratama Digitect</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-900 flex items-center justify-center h-screen font-sans">
        <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-96 border border-gray-700">
            <div class="text-center mb-6">
                <i class="fa-solid fa-user-shield text-4xl text-blue-500 mb-2"></i>
                <h2 class="text-2xl font-bold text-white">Admin Portal</h2>
            </div>
            <?php if($login_error): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-300 p-3 rounded mb-4 text-sm text-center">
                    <?= $login_error ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-400 text-sm mb-2">Username</label>
                    <input type="text" name="username" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="admin" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-400 text-sm mb-2">Password</label>
                    <input type="password" name="password" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="••••••" required>
                </div>
                <button type="submit" name="do_login" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">Masuk Dashboard</button>
            </form>
            <p class="text-center text-gray-600 text-xs mt-6">Pratama Digitect System v1.0</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Pratama Digitect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body class="bg-gray-900 text-gray-300 font-sans">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-gray-900 relative">
            <!-- Topbar Mobile -->
            <div class="md:hidden bg-black p-4 flex justify-between items-center border-b border-gray-800">
                <span class="font-bold text-white">Admin Panel</span>
                <a href="index.php?action=logout" class="text-red-400"><i class="fa-solid fa-sign-out-alt"></i></a>
            </div>
            <div class="p-8 max-w-7xl mx-auto">