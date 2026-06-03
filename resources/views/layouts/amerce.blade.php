<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'KOTLOV — маркетплейс отопления' }}</title>
    <meta name="description" content="{{ $description ?? 'KOTLOV — маркетплейс отопления, котлов, печей, каминов, дымоходов и монтажных услуг в Беларуси.' }}">
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
    <link rel="stylesheet" href="{{ asset('assets/css/kotlov.css') }}">

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
    <script src="{{ asset('assets/js/plugin/photoswipe-lightbox.umd.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugin/photoswipe.umd.min.js') }}"></script>

    {{-- Глобальный JS: корзина, сравнение, избранное --}}
    <script>
    // ===== Корзина: кнопка «В корзину» =====
    (function () {
        var csrf = document.querySelector('meta[name="csrf-token"]').content;

        function updateCartCount(count) {
            document.querySelectorAll('.shop-cart .count, .toolbar-count').forEach(function (el) {
                el.textContent = count;
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-add-to-cart');
            if (!btn) return;

            var productId = btn.dataset.productId;
            if (!productId) return;

            btn.disabled = true;
            var original = btn.textContent.trim();
            btn.textContent = '...';

            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.textContent = '✓ Добавлено';
                updateCartCount(data.count);
                setTimeout(function () {
                    btn.textContent = original;
                    btn.disabled = false;
                }, 1500);
            })
            .catch(function () {
                btn.textContent = original;
                btn.disabled = false;
            });
        });

        // Инициализация счётчика при загрузке
        fetch('/cart/data', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { updateCartCount(data.count); });
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
