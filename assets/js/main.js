document.addEventListener('DOMContentLoaded', () => {
    // Menu Logic
    const menuToggle = document.getElementById('menuToggle');
    const closeMenu = document.getElementById('closeMenu');
    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.getElementById('overlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sideMenu.classList.add('active');
            overlay.classList.add('active');
        });
    }

    if (closeMenu) {
        closeMenu.addEventListener('click', () => {
            sideMenu.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sideMenu.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Auth Protection
    const protectedPages = ['inventory.html', 'profile.html', 'questions.html', 'result.html'];
    const currentPage = window.location.pathname.split('/').pop();
    
    if (protectedPages.includes(currentPage)) {
        const user = localStorage.getItem('idefy_user');
        if (!user) {
            window.location.href = 'login.html';
        }
    }

    // Lucide Icons refresh if needed
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

window.logout = () => {
    if (confirm('Queres realmente sair?')) {
        localStorage.removeItem('idefy_user');
        document.cookie = "idefy_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        window.location.href = 'login.html';
    }
};
