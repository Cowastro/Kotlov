/* Keep the scrollable category list and its flyouts inside the desktop viewport. */
(() => {
    const menu = document.getElementById('desktop-catalog');
    if (!menu) return;

    const wrap = menu.closest('.nav-category-wrap');
    const button = wrap.querySelector('.btn-nav-drop');
    let pending = false;

    function update() {
        pending = false;
        button.setAttribute('aria-expanded', String(menu.classList.contains('active')));
        if (window.innerWidth < 1200) return;

        const rect = menu.getBoundingClientRect();
        wrap.style.setProperty('--catalog-menu-top', `${rect.top}px`);
        wrap.style.setProperty('--catalog-menu-height', `${Math.max(0, window.innerHeight - rect.top - 16)}px`);
        wrap.style.setProperty('--catalog-panel-left', `${rect.right - 1}px`);
        wrap.style.setProperty('--catalog-panel-width', `${Math.max(0, Math.min(960, window.innerWidth - rect.right - 15))}px`);
    }

    function schedule() {
        if (pending) return;
        pending = true;
        requestAnimationFrame(update);
    }

    new MutationObserver(schedule).observe(menu, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('scroll', schedule, { passive: true });
    wrap.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.classList.contains('active')) {
            event.preventDefault();
            menu.classList.remove('active');
            button.classList.remove('active');
            button.focus();
        }
        if (event.key === 'ArrowRight' && event.target.matches('.nav-category_link')) {
            const firstLink = event.target.parentElement.querySelector('.sub-nav-category a');
            if (firstLink) {
                event.preventDefault();
                firstLink.focus();
            }
        }
    });
    update();
})();
