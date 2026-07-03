// CONFIGURAÇÃO GLOBAL EUREKA LABS ELITE
const API_BASE_URL = 'https://eureka-labs-backend.onrender.com/backend';

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar Partículas (Subtis e Profissionais)
    if (document.getElementById('particles-js')) {
        particlesJS('particles-js', {
            "particles": {
                "number": { "value": 35, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#3b82f6" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.15, "random": true },
                "size": { "value": 1.5, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#8b5cf6", "opacity": 0.08, "width": 1 },
                "move": { "enable": true, "speed": 0.8, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": false } },
                "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 0.25 } } }
            },
            "retina_detect": true
        });
    }

    // Menu Lateral Lógica
    const menuToggle = document.getElementById('menuToggle');
    const closeMenu = document.getElementById('closeMenu');
    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.getElementById('overlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sideMenu.classList.add('active');
            overlay.classList.add('active');
            if (typeof gsap !== 'undefined') {
                gsap.from('.side-menu ul li', { x: 30, opacity: 0, stagger: 0.1, duration: 0.4 });
            }
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

    // Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

// Logout
function logout() {
    localStorage.removeItem('idefy_user');
    document.cookie = "idefy_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    window.location.href = 'login.html';
}
