// Sticky Header, Active Menu Highlight, and Announcement Bar for BusyLancer

document.addEventListener('DOMContentLoaded', function () {
    // Sticky Header
    var header = document.querySelector('.theme-main-menu');
    var stickyClass = 'fixed';
    function handleSticky() {
        if (window.scrollY > 60) {
            header.classList.add(stickyClass);
            header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
        } else {
            header.classList.remove(stickyClass);
            header.style.boxShadow = 'none';
        }
    }
    window.addEventListener('scroll', handleSticky, { passive: true });
    handleSticky(); // Run on load

    // Active Menu Item
    var navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    var currentPage = window.location.pathname.split('/').pop() || 'index.html';
    navLinks.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && (href === currentPage || (href === 'index.html' && currentPage === ''))) {
            link.classList.add('active-menu');
        }
    });

    // Menu bar shrink on scroll
    function shrinkMenuBar() {
        if (window.scrollY > 60) {
            header.classList.add('shrink');
        } else {
            header.classList.remove('shrink');
        }
    }
    window.addEventListener('scroll', shrinkMenuBar, { passive: true });
    shrinkMenuBar(); // Run on load
});
