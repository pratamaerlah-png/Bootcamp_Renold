<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-black border-r border-gray-800 hidden md:flex flex-col">
    <div class="p-6 border-b border-gray-800 flex items-center gap-3">
        <i class="fa-solid fa-flag-checkered text-blue-500 text-xl"></i>
        <span class="text-lg font-bold text-white">Admin Panel</span>
    </div>
    <nav class="flex-1 p-4 space-y-2">
        <a href="index.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'index.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-chart-line w-5"></i> 
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="events.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'events.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-calendar-days w-5"></i> 
            <span class="font-medium">Manajemen Event</span>
        </a>
        <a href="settings.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'settings.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-sliders w-5"></i> 
            <span class="font-medium">Pengaturan Web</span>
        </a>
        <a href="testimonials.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'testimonials.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-comment-dots w-5"></i> 
            <span class="font-medium">Testimoni Klien</span>
        </a>
        <a href="invoices.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'invoices.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5"></i> 
            <span class="font-medium">Invoice & Penagihan</span>
        </a>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <a href="users.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'users.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-users-gear w-5"></i> 
            <span class="font-medium">Manajemen User</span>
        </a>
        <a href="logs.php" class="flex items-center gap-3 py-3 px-4 rounded-lg transition duration-200 <?php echo ($current_page == 'logs.php') ? 'bg-blue-900/30 text-blue-400 border border-blue-500/30' : 'hover:bg-gray-800 text-gray-400'; ?>">
            <i class="fa-solid fa-clipboard-list w-5"></i> 
            <span class="font-medium">Log Aktivitas</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center gap-3 mb-4 px-2">
            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold text-white">AD</div>
            <div class="text-sm">
                <div class="text-white font-bold">Administrator</div>
                <div class="text-xs text-gray-500">Super User</div>
            </div>
        </div>
        <a href="index.php?action=logout" class="block w-full text-center py-2 px-4 bg-red-900/20 text-red-400 hover:bg-red-900/40 rounded-lg transition text-sm font-bold border border-red-900/30">
            <i class="fa-solid fa-sign-out-alt mr-2"></i> Logout
        </a>
    </div>
</aside>