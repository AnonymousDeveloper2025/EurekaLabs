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

// Configuração da API
const API_BASE_URL = 'https://eureka-labs-backend.onrender.com';

// Partículas Globais
if (document.getElementById('particles-js')) {
    particlesJS('particles-js', {
        "particles": {
            "number": { "value": 50, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": ["#3b82f6", "#8b5cf6"] },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.3, "random": true },
            "size": { "value": 2, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#3b82f6", "opacity": 0.1, "width": 1 },
            "move": { "enable": true, "speed": 1.5, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
        },
        "interactivity": {
            "detect_on": "canvas",
            "events": { "onhover": { "enable": true, "mode": "bubble" }, "onclick": { "enable": true, "mode": "push" }, "resize": true },
            "modes": { "bubble": { "distance": 200, "size": 4, "duration": 2, "opacity": 0.8, "speed": 3 }, "push": { "particles_nb": 4 } }
        },
        "retina_detect": true
    });
}

// Menu Toggle Logic Global
document.addEventListener('click', (e) => {
    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.getElementById('overlay');
    const menuToggle = document.getElementById('menuToggle');
    const closeMenu = document.getElementById('closeMenu');

    if (menuToggle && menuToggle.contains(e.target)) {
        sideMenu.style.right = '0';
        overlay.style.display = 'block';
    } else if (closeMenu && closeMenu.contains(e.target) || (overlay && overlay.contains(e.target))) {
        sideMenu.style.right = '-300px';
        overlay.style.display = 'none';
    }
});
