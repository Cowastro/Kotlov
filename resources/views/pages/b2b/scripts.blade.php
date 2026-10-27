<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var filterForm = document.getElementById('b2b-filter-form');
    var searchInput = document.getElementById('b2b-search-input');
    var suggest = document.getElementById('b2b-search-suggest');
    var suggestUrl = searchInput.closest('.b2b-search').dataset.suggestUrl;
    var timer = null;
    var request = null;
    var activeIndex = -1;

    document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () { filterForm.submit(); });
    });

    function closeSuggest() {
        suggest.hidden = true;
        activeIndex = -1;
        searchInput.setAttribute('aria-expanded', 'false');
    }

    function setActive(index) {
        var items = Array.from(suggest.querySelectorAll('.b2b-suggest-item'));
        if (!items.length) return;
        activeIndex = (index + items.length) % items.length;
        items.forEach(function (item, itemIndex) { item.classList.toggle('is-active', itemIndex === activeIndex); });
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function renderSuggest(items) {
        suggest.replaceChildren();
        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'b2b-suggest-empty';
            empty.textContent = 'Совпадений среди доступных товаров нет';
            suggest.appendChild(empty);
        } else {
            items.forEach(function (item) {
                var link = document.createElement('a');
                link.className = 'b2b-suggest-item';
                link.href = item.url;
                link.setAttribute('role', 'option');

                var image = document.createElement('img');
                image.src = item.image;
                image.alt = '';
                image.loading = 'lazy';

                var copy = document.createElement('span');
                var name = document.createElement('strong');
                name.textContent = item.name;
                var meta = document.createElement('small');
                meta.textContent = [item.sku, item.category, item.brand].filter(Boolean).join(' · ');
                copy.append(name, meta);

                var price = document.createElement('span');
                price.className = 'b2b-suggest-item__price';
                var amount = document.createElement('b');
                amount.textContent = Number(item.price).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) + ' BYN';
                var stock = document.createElement('small');
                stock.textContent = item.stock;
                price.append(amount, stock);
                link.append(image, copy, price);
                suggest.appendChild(link);
            });
        }
        suggest.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        if (request) request.abort();
        var query = searchInput.value.trim();
        if (query.length < 2) return closeSuggest();
        timer = setTimeout(function () {
            request = new AbortController();
            fetch(suggestUrl + (suggestUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' }, signal: request.signal,
            }).then(function (response) { return response.json(); })
                .then(function (data) { if (searchInput.value.trim() === query) renderSuggest(data.items || []); })
                .catch(function (error) { if (error.name !== 'AbortError') closeSuggest(); });
        }, 180);
    });

    searchInput.addEventListener('keydown', function (event) {
        var items = suggest.querySelectorAll('.b2b-suggest-item');
        if (!suggest.hidden && items.length && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            setActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            items[activeIndex].click();
        } else if (event.key === 'Escape') {
            closeSuggest();
        }
    });

    document.addEventListener('click', function (event) { if (!event.target.closest('.b2b-search')) closeSuggest(); });
    document.addEventListener('keydown', function (event) {
        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
            event.preventDefault();
            searchInput.focus();
        }
    });

    var categoryPanel = document.getElementById('b2b-category-panel');
    var overlay = document.querySelector('.b2b-catalog-overlay');
    function toggleCatalog(open) {
        categoryPanel.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    document.querySelectorAll('[data-catalog-open]').forEach(function (button) { button.addEventListener('click', function () { toggleCatalog(true); }); });
    document.querySelectorAll('[data-catalog-close]').forEach(function (button) { button.addEventListener('click', function () { toggleCatalog(false); }); });

    var categorySearch = document.getElementById('b2b-category-search');
    var categoryEmpty = document.querySelector('.b2b-category-empty');
    categorySearch.addEventListener('input', function () {
        var needle = categorySearch.value.trim().toLocaleLowerCase('ru');
        var nodes = Array.from(document.querySelectorAll('#b2b-category-tree [data-category-node]'));
        nodes.forEach(function (node) { node.hidden = false; });
        if (!needle) { categoryEmpty.hidden = true; return; }
        nodes.slice().reverse().forEach(function (node) {
            var ownMatch = (node.dataset.label || '').includes(needle);
            var childMatch = node.tagName === 'DETAILS' && Array.from(node.querySelectorAll(':scope > .b2b-category-tree__nested > [data-category-node]')).some(function (child) { return !child.hidden; });
            node.hidden = !ownMatch && !childMatch;
            if (!node.hidden && node.tagName === 'DETAILS') node.open = true;
        });
        categoryEmpty.hidden = nodes.some(function (node) { return !node.hidden; });
    });

    function showToast(message) {
        var toast = document.querySelector('.b2b-toast');
        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(function () { toast.hidden = true; }, 2200);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.b2b-add-to-cart');
        if (!button || button.disabled) return;
        var quantityInput = document.querySelector(button.dataset.qtyInput);
        var qty = Math.max(1, parseInt(quantityInput.value, 10) || 1);
        var original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span>Добавляем…</span>';
        fetch('{{ route('cart.add') }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrf },
            body: JSON.stringify({ product_id:Number(button.dataset.productId), quantity:qty }),
        }).then(function (response) {
            return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Не удалось добавить товар'); return data; });
        }).then(function (data) {
            document.querySelectorAll('.cart-count').forEach(function (node) { node.textContent = data.count; });
            button.classList.add('is-added');
            button.innerHTML = '<span>Добавлено ✓</span>';
            showToast('Товар добавлен в корзину');
            setTimeout(function () { button.classList.remove('is-added'); button.innerHTML = original; button.disabled = false; }, 1300);
        }).catch(function (error) {
            button.innerHTML = original;
            button.disabled = false;
            showToast(error.message);
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.b2b-price-request-form');
        if (!form) return;
        event.preventDefault();
        var button = form.querySelector('button');
        var quantity = form.querySelector('input');
        button.disabled = true;
        button.textContent = 'Отправляем…';
        fetch('{{ route('b2b.price-requests.store') }}', {
            method:'POST',
            headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrf },
            body:JSON.stringify({ product_id:Number(form.dataset.productId), quantity:Math.max(1,Number(quantity.value)||1) }),
        }).then(function (response) {
            return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Не удалось отправить запрос'); return data; });
        }).then(function (data) {
            form.outerHTML = '<div class="b2b-price-request-state"><strong>' + (data.status_label || 'Новая') + '</strong><span>' + data.message + '</span></div>';
            showToast('Запрос цены отправлен');
        }).catch(function (error) {
            button.disabled=false;
            button.textContent='Запросить оптовую цену';
            showToast(error.message);
        });
    });
})();
</script>
