// Mobile Menu Toggle
const btn = document.getElementById('mobile-menu-btn');
const menu = document.getElementById('mobile-menu');

if (btn && menu) {
    btn.addEventListener('click', () => {
        menu.classList.toggle('hidden');
    });
}

// Event Tabs Logic
function switchTab(tab) {
    const upcomingContent = document.getElementById('content-upcoming');
    const pastContent = document.getElementById('content-past');
    const upcomingTab = document.getElementById('tab-upcoming');
    const pastTab = document.getElementById('tab-past');

    if (tab === 'upcoming') {
        upcomingContent.classList.remove('hidden');
        pastContent.classList.add('hidden');
        upcomingTab.className = "px-6 py-2 rounded-md text-sm font-bold bg-white text-blue-600 shadow-sm transition-all";
        pastTab.className = "px-6 py-2 rounded-md text-sm font-bold text-gray-500 hover:text-gray-700 transition-all";
    } else {
        upcomingContent.classList.add('hidden');
        pastContent.classList.remove('hidden');
        pastTab.className = "px-6 py-2 rounded-md text-sm font-bold bg-white text-blue-600 shadow-sm transition-all";
        upcomingTab.className = "px-6 py-2 rounded-md text-sm font-bold text-gray-500 hover:text-gray-700 transition-all";
    }
}