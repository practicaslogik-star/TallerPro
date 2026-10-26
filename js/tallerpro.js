/**
 * TallerPro UI JavaScript
 */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('tallerSidebar');
    const collapseBtn = document.getElementById('tallerCollapseBtn');
    const topbarToggle = document.getElementById('tallerTopbarToggle');
    const navItems = document.querySelectorAll('.tallerpro-nav-item');

    // Restore sidebar state from localStorage
    if (localStorage.getItem('tallerpro_sidebar_collapsed') === 'true') {
        sidebar?.classList.add('collapsed');
    }

    // Toggle sidebar collapse
    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('tallerpro_sidebar_collapsed', isCollapsed ? 'true' : 'false');
    }

    collapseBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    topbarToggle?.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    // Only handle active state for placeholder items with hash links (#...)
    navItems.forEach(function (item) {
        item.addEventListener('click', function (e) {
            const href = item.getAttribute('href');
            if (!href || href.startsWith('#')) {
                e.preventDefault();
                navItems.forEach(function (el) { el.classList.remove('active'); });
                item.classList.add('active');
            }
        });
    });

    // Generic Modal logic (works for any view with .tallerpro-modal-backdrop)
    const openModalBtns = document.querySelectorAll('#tallerOpenModalBtn, .tallerpro-btn-primary');
    const modals = document.querySelectorAll('.tallerpro-modal-backdrop');

    openModalBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const modal = document.querySelector('.tallerpro-modal-backdrop');
            if (modal) {
                e.preventDefault();
                modal.classList.add('active');
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }
        });
    });

    modals.forEach(function (modal) {
        const closeBtns = modal.querySelectorAll('.tallerpro-modal-close, #tallerCloseModalBtn, .tallerpro-btn-cancel, #tallerCancelModalBtn');
        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                modal.classList.remove('active');
            });
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.tallerpro-modal-backdrop.active').forEach(function (m) {
                m.classList.remove('active');
            });
        }
    });
});
