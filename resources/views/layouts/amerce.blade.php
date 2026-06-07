<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? ('KOTLOV — маркетплейс отопления' . (isset($cityIn) && $cityIn ? ' ' . $cityIn : '')) }}</title>
    <meta name="description" content="{{ $description ?? ('KOTLOV — маркетплейс отопления, котлов, печей, каминов, дымоходов и монтажных услуг' . (isset($cityIn) && $cityIn ? ' ' . $cityIn : ' в Беларуси') . '.') }}">
    @if(isset($currentCity) && $currentCity)
    <link rel="canonical" href="https://kotlov.by{{ request()->getPathInfo() }}">
    @endif
    <meta name="keywords" content="{{ $keywords ?? 'котлы, печи, камины, дымоходы, отопление, монтаж, маркетплейс отопления' }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('assets/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/icon/icomoon/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/photoswipe.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kotlov.css') }}?v={{ filemtime(public_path('assets/css/kotlov.css')) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="shortcut icon" href="{{ asset('img/favicon.ico') }}">

    @stack('styles')
</head>

<body>

    @include('partials.amerce-header')

    @yield('content')

    @include('partials.footer')
    @include('partials.modals')

    <script src="{{ asset('assets/js/plugin/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/count-down.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/infinityslide.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/wow.min.js') }}"></script>
    <script src="{{ asset('assets/js/zoom.js') }}"></script>
    <script src="{{ asset('assets/js/carousel.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/drift.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/js/shop.js') }}?v={{ filemtime(public_path('assets/js/shop.js')) }}"></script>
    <script src="{{ asset('assets/js/plugin/photoswipe-lightbox.umd.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/photoswipe.umd.min.js') }}"></script>

    {{-- Глобальный JS: корзина, сравнение, избранное --}}
    <script>
    // ===== Корзина =====
    (function () {
        var csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Обновить счётчик в хедере и тулбаре
        function updateCartCount(count) {
            document.querySelectorAll('.shop-cart .count, .toolbar-count').forEach(function (el) {
                el.textContent = count;
            });
        }

        // Обновить прогресс-бар бесплатной доставки
        function updateThreshold(data) {
            var textEl     = document.getElementById('cart-threshold-text');
            var remainEl   = document.getElementById('cart-threshold-remaining');
            var barEl      = document.getElementById('cart-threshold-bar');
            if (!textEl || !remainEl || !barEl) return;

            if (data.remaining <= 0) {
                textEl.innerHTML = '🎉 У вас <strong>бесплатная доставка</strong>!';
            } else {
                var rem = parseFloat(data.remaining).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                textEl.innerHTML =
                    'Добавьте ещё на <span class="text-primary fw-7" id="cart-threshold-remaining">' +
                    rem + ' BYN</span> для бесплатной доставки';
            }
            var pct = data.progress || 0;
            barEl.style.width = pct + '%';
            barEl.dataset.progress = pct;
        }

        // Рендер товаров в mini-cart (Amerce-структура)
        // Amerce main.js ищет: .wrap-empty_text → .list-empty → children not .box-text_empty
        // Поэтому НЕ убираем класс list-empty с контейнера — Amerce должен сам находить items
        function renderMiniCart(data) {
            var container = document.getElementById('cart-items');
            var footer    = document.querySelector('.tf-mini-cart-bottom');
            if (!container) return;

            // Удаляем старые динамические items
            container.querySelectorAll('.tf-mini-cart-item').forEach(function (el) { el.remove(); });

            if (!data.items || data.items.length === 0) {
                // Пустая корзина: показываем empty-state, прячем footer
                var emptyBlock = container.querySelector('.box-text_empty');
                if (emptyBlock) emptyBlock.style.display = '';
                if (footer) footer.style.display = 'none';
                return;
            }

            // Есть товары: прячем empty-state, показываем footer
            var emptyBlock = container.querySelector('.box-text_empty');
            if (emptyBlock) emptyBlock.style.display = 'none';
            // Показываем footer — убираем и класс и инлайн-стиль (jQuery .hide() ставит style)
            if (footer) {
                footer.classList.remove('box-empty_clear');
                footer.style.display = '';
            }

            // Добавляем items в оригинальной Amerce-структуре
            var placeholder = '/img/products/product-placeholder.jpg';
            data.items.forEach(function (item) {
                var img  = item.image || placeholder;
                var url  = '/' + item.category_slug + '/' + item.slug;
                var total = (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2);
                var html =
                    '<div class="tf-mini-cart-item file-delete" data-product-id="' + item.id + '">' +
                        '<div class="tf-mini-cart-image">' +
                            '<a href="' + url + '">' +
                            '<img loading="lazy" width="100" height="100" src="' + img + '" ' +
                                'onerror="this.src=\'' + placeholder + '\'" alt="' + item.name + '">' +
                            '</a>' +
                        '</div>' +
                        '<div class="tf-mini-cart-info">' +
                            '<a href="' + url + '" class="name fw-medium link text-line-clamp-1">' +
                                item.name +
                            '</a>' +
                            (item.sku ? '<p class="text-caption-01 cl-text-3 mb-4">Арт: ' + item.sku + '</p>' : '') +
                        '</div>' +
                        '<div class="tf-mini-cart-price">' +
                            '<button class="tf-btn-line-3 type-primary remove cs-pointer border-0 bg-transparent p-0 mini-cart-remove" ' +
                                'data-product-id="' + item.id + '">' +
                                '<span class="text-caption-01 fw-semibold">Удалить</span>' +
                            '</button>' +
                            '<div class="fw-semibold d-flex align-items-center justify-content-between gap-4">' +
                                '<span class="number">' + item.quantity + '</span>' +
                                '<span>x</span>' +
                                '<span class="price tf-mini-card-price">' + parseFloat(item.price).toFixed(2) + ' BYN</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                container.insertAdjacentHTML('beforeend', html);
            });

            // Обновляем итог в футере
            var totalEl = document.querySelector('.tf-totals-total-value');
            if (totalEl) totalEl.textContent = parseFloat(data.subtotal).toFixed(2) + ' BYN';
        }

        // Загрузить данные корзины и отрисовать
        // Счётчик запросов: рендерим только если это последний запрос
        var _cartLoadSeq = 0;
        function loadMiniCart() {
            var seq = ++_cartLoadSeq;
            fetch('/cart/data', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (seq !== _cartLoadSeq) return; // пришёл устаревший ответ — игнорируем
                    updateCartCount(data.count);
                    renderMiniCart(data);
                    updateThreshold(data);
                });
        }

        // AJAX-отправка форм мини-корзины (Заметка / Доставка / Промокод)
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('.mini-cart-ajax-form');
            if (!form) return;
            e.preventDefault();

            var url  = form.dataset.url;
            var body = new URLSearchParams(new FormData(form));
            var msg  = form.querySelector('.mini-cart-form-msg');

            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: body,
            })
            .then(function (r) { return r.json(); })
            .then(function () {
                // Показываем подтверждение и закрываем панель через секунду
                if (msg) {
                    msg.style.display = '';
                    msg.textContent   = '✓ Сохранено';
                    setTimeout(function () {
                        msg.style.display = 'none';
                        // Закрываем тул-панель
                        var closeBtn = form.closest('.tf-mini-cart-tool-openable');
                        if (closeBtn) closeBtn.classList.remove('open');
                    }, 900);
                }
            })
            .catch(function () {
                if (msg) {
                    msg.style.display = '';
                    msg.textContent   = 'Ошибка. Попробуйте снова.';
                }
            });
        });

        // Клик «В корзину» — перехватываем, добавляем через AJAX, потом открываем корзину
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-add-to-cart');
            if (!btn) return;

            e.preventDefault();

            var productId = btn.dataset.productId;
            if (!productId) return;

            // Читаем количество: из data-qty-input (селектор) или фиксировано 1
            var qty = 1;
            var qtySelector = btn.dataset.qtyInput;
            if (qtySelector) {
                var qtyEl = document.querySelector(qtySelector);
                if (qtyEl) qty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
            }

            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: qty }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateCartCount(data.count);
                loadMiniCart();
                var cartCanvas = document.getElementById('shoppingCart');
                if (cartCanvas && window.bootstrap) {
                    bootstrap.Offcanvas.getOrCreateInstance(cartCanvas).show();
                }
            });
        });

        // Удалить из мини-корзины
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.mini-cart-remove');
            if (!btn) return;
            e.preventDefault();
            var productId = btn.dataset.productId;
            if (!productId) return;

            fetch('/cart/remove', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateCartCount(data.count);
                loadMiniCart();
            });
        });

        // Загружаем корзину при открытии offcanvas
        document.addEventListener('show.bs.offcanvas', function (e) {
            if (e.target && e.target.id === 'shoppingCart') {
                loadMiniCart();
            }
        });

        // ── Чекбокс согласия в mini-cart ──────────────────
        document.addEventListener('DOMContentLoaded', function () {
            var agreeBox    = document.getElementById('agree-term');
            var agreeError  = document.getElementById('agree-term-error');
            var checkoutBtn = document.getElementById('mini-cart-checkout-btn');

            if (!checkoutBtn) return;

            function syncBtn() {
                var checked = agreeBox && agreeBox.checked;
                checkoutBtn.style.opacity       = checked ? '1'           : '0.45';
                checkoutBtn.style.cursor        = checked ? 'pointer'     : 'not-allowed';
                checkoutBtn.style.pointerEvents = checked ? ''            : 'none';
                if (checked && agreeError) agreeError.style.display = 'none';
            }

            // Клик: pointerEvents:none не пустит, но на случай прямого вызова
            checkoutBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!agreeBox || !agreeBox.checked) {
                    if (agreeError) agreeError.style.display = '';
                    return;
                }
                // Всё ок — идём на checkout
                agreeError && (agreeError.style.display = 'none');
                window.location.href = '/checkout';
            });

            if (agreeBox) {
                agreeBox.addEventListener('change', syncBtn);
            }
            syncBtn(); // начальное состояние при загрузке
        });

        // Инициализация счётчика при загрузке страницы
        loadMiniCart();
    })();

    // ===== Сравнение =====
    function addToCompare(productId, el) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        fetch('/compare/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ product_id: productId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.message === 'added') {
                el.classList.add('active');
                var tip = el.querySelector('.tooltip');
                if (tip) tip.textContent = 'В сравнении';
            } else if (data.message === 'already_added') {
                window.location.href = '/compare';
            } else if (data.message === 'limit_reached') {
                alert('Максимум 4 товара для сравнения.');
            }
        });
    }
    </script>

    @stack('scripts')

    <script>

    // ===== ПОИСК: автодополнение =====
    function initSearchSuggest(inputId, suggestId) {
        var input = document.getElementById(inputId);
        var suggest = document.getElementById(suggestId);
        if (!input || !suggest) return;

        var timer = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) {
                suggest.style.display = 'none';
                suggest.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                $.get('/search/suggest', { q: q }, function (data) {
                    if (!data.length) {
                        suggest.style.display = 'none';
                        return;
                    }
                    var html = data.map(function (item) {
                        if (item.type === 'category') {
                            return '<a href="' + item.url + '" class="suggest-item suggest-category">' +
                                '<i class="icon icon-FolderOpen me-8"></i>' +
                                '<span>' + item.name + '</span>' +
                                '</a>';
                        }
                        return '<a href="' + item.url + '" class="suggest-item suggest-product">' +
                            '<span class="suggest-name">' + item.name + '</span>' +
                            '<span class="suggest-price cl-text-2">' + item.price + '</span>' +
                            '</a>';
                    }).join('');
                    suggest.innerHTML = html;
                    suggest.style.display = 'block';
                });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !suggest.contains(e.target)) {
                suggest.style.display = 'none';
            }
        });

        input.closest('form').addEventListener('submit', function () {
            suggest.style.display = 'none';
        });
    }

    initSearchSuggest('header-search-input',      'search-suggest');
    initSearchSuggest('modal-search-input',       'modal-search-suggest');
    initSearchSuggest('mobilemenu-search-input',  'mobilemenu-search-suggest');
    initSearchSuggest('searchpage-search-input',  'searchpage-search-suggest');

    // Wishlist + Compare AJAX — подключается после main.js
    $(document).ready(function () {

        // WISHLIST: перехватываем после того как main.js уже отработал
        $(document).on('click', '.card-product .wishlist a', function () {
            var productId = $(this).data('product-id');
            if (!productId) return;
            $.post('/wishlist/toggle',
                { _token: $('meta[name="csrf-token"]').attr('content'), product_id: productId }
            );
        });

        // COMPARE: запоминаем product_id перед открытием offcanvas
        var compareJustLoaded = false;

        $(document).on('click', 'a[href="#compare"][data-product-id]', function (e) {
            e.preventDefault();

            var link = $(this);
            var productId = parseInt(link.data('product-id'));
            if (!productId || link.data('loading')) return;

            link.data('loading', true).addClass('active');

            $.ajax({
                url: '/compare/add',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    product_id: productId
                },
                complete: function () {
                    link.data('loading', false);
                },
                success: function () {
                    loadCompareItems(function () {
                        var compareCanvas = document.getElementById('compare');
                        if (compareCanvas && window.bootstrap) {
                            compareJustLoaded = true;
                            bootstrap.Offcanvas.getOrCreateInstance(compareCanvas).show();
                        }
                    });
                },
                error: function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.message === 'limit_reached') {
                        alert('Максимум 4 товара для сравнения.');
                    }
                }
            });
        });

        $('#compare').on('show.bs.offcanvas', function () {
            if (compareJustLoaded) {
                compareJustLoaded = false;
                return;
            }
            loadCompareItems();
        });
    });

    function loadCompareItems(done) {
        $.get('/compare/items', function (data) {
            var c = $('#compare-offcanvas-items');
            if (!data.length) {
                c.html('<p class="box-text_empty cl-text-2">Список сравнения пуст</p>');
                if (typeof done === 'function') done();
                return;
            }
            c.html(data.map(function (p) {
                return '<div class="tf-compare-item file-delete">' +
                    '<a href="' + p.url + '">' +
                    '<div class="remove" onclick="removeCompareItem(' + p.id + ',event)" style="cursor:pointer;position:absolute;top:4px;right:4px;">' +
                    '<i class="icon icon-X2"></i></div>' +
                    '<img class="radius-3" width="100" height="133" src="' + p.image + '" alt="' + p.name + '">' +
                    '</a>' +
                    '<p class="text-caption-01 text-center mt-4">' + p.name.substring(0, 40) + '</p>' +
                    '</div>';
            }).join(''));
            if (typeof done === 'function') done();
        });
    }

    function removeCompareItem(productId, event) {
        event.preventDefault();
        event.stopPropagation();
        $.post('/compare/remove',
            { _token: $('meta[name="csrf-token"]').attr('content'), product_id: productId },
            function () { loadCompareItems(); }
        );
    }

    function clearCompare() {
        $.post('/compare/clear-ajax',
            { _token: $('meta[name="csrf-token"]').attr('content') },
            function () { loadCompareItems(); }
        );
    }
    </script>

</body>
</html>
