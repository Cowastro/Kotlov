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

    /* Hover intent ("menu aim"): while the pointer heads diagonally to the open panel,
       crossing other rows does not switch the panel. */
    const desktop = window.matchMedia('(min-width: 1200px)');
    const items = Array.from(menu.children).filter(li => li.classList.contains('has-sub-nav-category'));
    let openItem = null;
    let timer = null;
    let lastMoves = [];

    menu.classList.add('js-aim');

    function open(li) {
        clearTimeout(timer);
        timer = null;
        if (openItem === li) return;
        if (openItem) openItem.classList.remove('is-open');
        openItem = li;
        if (li) li.classList.add('is-open');
    }

    function headingToPanel(cur) {
        if (!openItem || !lastMoves.length) return false;
        const panel = openItem.querySelector(':scope > .sub-nav-category');
        if (!panel) return false;
        const r = panel.getBoundingClientRect();
        if (!r.width) return false;
        // Oldest point of the current stroke, or where the pointer last rested.
        const prev = lastMoves.find(move => cur.t - move.t < 300) || lastMoves[lastMoves.length - 1];
        if (cur.x <= prev.x) return false;
        // Slopes from the pointer to the panel's top-left and bottom-left corners.
        const slope = (a, b) => (b.y - a.y) / (b.x - a.x);
        const top = { x: r.left, y: r.top - 40 };
        const bottom = { x: r.left, y: r.bottom + 40 };
        return slope(cur, top) < slope(prev, top) && slope(cur, bottom) > slope(prev, bottom);
    }

    const point = event => ({ x: event.clientX, y: event.clientY, t: Date.now() });

    menu.addEventListener('mousemove', event => {
        lastMoves.push(point(event));
        if (lastMoves.length > 6) lastMoves.shift();
    }, { passive: true });

    items.forEach(li => {
        li.addEventListener('mouseenter', event => {
            if (!desktop.matches) return;
            clearTimeout(timer);
            if (!openItem || !headingToPanel(point(event))) {
                open(li);
                return;
            }
            // Switch only once the pointer stops or turns away from the open panel.
            const retry = () => {
                if (!li.matches(':hover')) return;
                const cur = lastMoves[lastMoves.length - 1];
                const moving = Date.now() - cur.t < 150;
                if (moving && headingToPanel(cur)) {
                    timer = setTimeout(retry, 100);
                } else {
                    open(li);
                }
            };
            timer = setTimeout(retry, 200);
        });
        li.addEventListener('focusin', () => open(li));
    });

    menu.addEventListener('mouseleave', () => {
        clearTimeout(timer);
        timer = null;
    });

    new MutationObserver(() => {
        if (!menu.classList.contains('active')) open(null);
    }).observe(menu, { attributes: true, attributeFilter: ['class'] });
})();
