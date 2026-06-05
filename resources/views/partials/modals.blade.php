<!-- Mobile Menu -->
<div class="offcanvas offcanvas-start canvas-mb" id="mobileMenu">
    <div class="canvas-header">
        <span class="icon-close-popup" data-bs-dismiss="offcanvas">
            <i class="icon icon-X2"></i>
        </span>
        <form action="/search" method="get" class="form-search-nav" autocomplete="off">
            <fieldset style="position:relative;flex:1;">
                <input type="text" name="q" id="mobilemenu-search-input"
                    placeholder="Поиск по каталогу" required autocomplete="off">
                <div id="mobilemenu-search-suggest" class="search-suggest-dropdown"></div>
            </fieldset>
            <button type="submit" class="btn-action">
                <i class="icon icon-MagnifyingGlass"></i>
            </button>
        </form>
    </div>
    <div class="canvas-body">

        {{-- Каталог категорий --}}
        <div class="mb-categories">
            <div class="mb-cat-header">
                <i class="icon icon-List"></i>
                <span>Каталог товаров</span>
            </div>
            <ul class="mb-cat-list">
                @foreach ($navCategories as $rootCat)
                    @php $children = $rootCat->children->where('is_active', true); @endphp
                    @if ($children->count() > 0)
                        <li class="mb-cat-item has-children">
                            <div class="mb-cat-row">
                                <a href="/{{ $rootCat->slug }}" class="mb-cat-link">{{ $rootCat->name }}</a>
                                <button class="mb-cat-toggle" type="button">
                                    <i class="icon icon-CaretDown"></i>
                                </button>
                            </div>
                            <ul class="mb-cat-sub">
                                @foreach ($children->take(8) as $child)
                                    <li><a href="/{{ $child->slug }}">{{ $child->name }}</a></li>
                                @endforeach
                                @if ($children->count() > 8)
                                    <li><a href="/{{ $rootCat->slug }}" class="cl-text-2">Все разделы →</a></li>
                                @endif
                            </ul>
                        </li>
                    @else
                        <li class="mb-cat-item">
                            <div class="mb-cat-row">
                                <a href="/{{ $rootCat->slug }}" class="mb-cat-link">{{ $rootCat->name }}</a>
                            </div>
                        </li>
                    @endif
                @endforeach
                <li class="mb-cat-item">
                    <div class="mb-cat-row">
                        <a href="/catalog" class="mb-cat-link cl-text-2">Весь каталог →</a>
                    </div>
                </li>
            </ul>
        </div>

        {{-- Общие ссылки --}}
        <div class="mb-content-top">
            <ul class="nav-ul-mb" id="wrapper-menu-navigation">
                <li><a href="/installers">Монтаж</a></li>
                <li><a href="/brands">Бренды</a></li>
                <li><a href="/akcii">Акции</a></li>
                <li><a href="/blog">Статьи</a></li>
                <li><a href="/dostavka">Доставка</a></li>
                <li><a href="/contacts">Контакты</a></li>
                <li><a href="/partners">Партнёрам</a></li>
            </ul>
        </div>
        <div class="need-help-wrap">
            <p class="nd-title h6 fw-medium mb-16">Нужна помощь?</p>
            <p class="lh-26 cl-text-2 mb-4">Минск, ул. Селицкого, 39Б, каб. 23</p>
            <a href="https://www.google.com/maps?q=Минск,+ул.+Селицкого,+39Б" target="_blank"
                class="text-decoration-underline text-primary lh-26 mb-16">Открыть на карте</a>
            <a href="mailto:info@kotlov.by" class="cl-text-2 link mb-8">info@kotlov.by</a>
            <a href="tel:+375293544041" class="cl-text-2 link">+375 (29) 354-40-41</a>
            <p class="lh-26 cl-text-2 mt-8 mb-0">Пн–Пт: 9:00–18:00</p>
        </div>
    </div>
</div>
<!-- /Mobile Menu -->

<!-- Toolbar -->
<div class="tf-toolbar-bottom">
    <div class="toolbar-item">
        <a href="/catalog">
            <span class="toolbar-icon"><i class="icon icon-storefront"></i></span>
            <span class="toolbar-label">Каталог</span>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="#search" data-bs-toggle="modal">
            <span class="toolbar-icon"><i class="icon icon-MagnifyingGlass"></i></span>
            <span class="toolbar-label">Поиск</span>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="/account">
            <span class="toolbar-icon"><i class="icon icon-User"></i></span>
            <span class="toolbar-label">Кабинет</span>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="/wishlist">
            <span class="toolbar-icon"><i class="icon icon-HeartStraight"></i></span>
            <span class="toolbar-label">Избранное</span>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="/cart">
            <span class="toolbar-icon">
                <i class="icon icon-Handbag"></i>
                <span class="toolbar-count">0</span>
            </span>
            <span class="toolbar-label">Корзина</span>
        </a>
    </div>
</div>
<!-- /Toolbar -->

<!-- Forgot Pass -->
<div class="modal modalCentered fade modal-log modal-log_forgot" id="modalForgot">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <h3 class="title-pop mb-8">Восстановление пароля</h3>
                <p class="desc-pop cl-text-2">Введите email — отправим инструкцию по сбросу пароля.</p>
            </div>
            <div class="modal-main">
                <form class="form-log" action="/forgot-password" method="POST">
                    @csrf
                    <div class="form-content">
                        <fieldset class="tf-field">
                            <label for="forgot-user" class="tf-lable fw-medium">Email адрес <span class="text-primary">*</span></label>
                            <input type="email" name="email" id="forgot-user" placeholder="Ваш email*" required>
                        </fieldset>
                    </div>
                    <div class="group-action">
                        <button type="submit" class="tf-btn animate-btn w-100">Отправить инструкцию</button>
                        <p class="orther-log text-center">
                            Вспомнили пароль?
                            <a href="#sign" data-bs-toggle="modal" class="text-primary text-decoration-underline">Войти</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Forgot Pass -->

<!-- Share -->
<div class="modal modalCentered fade modal-share" id="share">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-heading d-flex align-items-center justify-content-between">
                <h4 class="title-pop">Поделиться</h4>
                <span class="cs-pointer d-flex link" data-bs-dismiss="modal"><i class="icon-X2 fs-24"></i></span>
            </div>
            <div class="modal-main">
                <ul class="tf-social-icon-2 hv-dark mb-20">
                    <li>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank">
                            <i class="icon icon-FacebookLogo"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://t.me/share/url?url={{ urlencode(url()->current()) }}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.289c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.48 13.9l-2.95-.924c-.642-.204-.657-.642.136-.953l11.527-4.445c.535-.194 1.003.131.37.67z"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.instagram.com/kotlov.by/" target="_blank">
                            <i class="icon icon-InstagramLogo"></i>
                        </a>
                    </li>
                </ul>
                <div class="wrap-code btn-coppy-text">
                    <p class="coppyText cl-text-2" id="coppyText">{{ url()->current() }}</p>
                    <div class="btn-action-copy tf-btn">Копировать</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Share -->

<!-- Ask -->
<div class="modal modalCentered fade modal-log modal-ask" id="ask">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <h3 class="title-pop mb-8">Задать вопрос</h3>
                <p class="desc-pop cl-text-2">Наш специалист ответит в течение рабочего дня.</p>
            </div>
            <div class="modal-main">
                <form class="form-log mb-20" action="/ask" method="POST">
                    @csrf
                    <div class="form-content">
                        <fieldset class="tf-field">
                            <label for="name-ask" class="tf-lable fw-medium">Ваше имя <span class="text-primary">*</span></label>
                            <input type="text" name="name" id="name-ask" placeholder="Ваше имя*" required>
                        </fieldset>
                        <fieldset class="tf-field">
                            <label for="phone-ask" class="tf-lable fw-medium">Телефон <span class="text-primary">*</span></label>
                            <input type="tel" name="phone" id="phone-ask" placeholder="+375 (XX) XXX-XX-XX" required>
                        </fieldset>
                        <fieldset class="tf-field">
                            <label for="email-ask" class="tf-lable fw-medium">Email</label>
                            <input type="email" name="email" id="email-ask" placeholder="Ваш email">
                        </fieldset>
                        <fieldset class="tf-field">
                            <label for="message-ask" class="tf-lable fw-medium">Вопрос <span class="text-primary">*</span></label>
                            <textarea name="message" id="message-ask" placeholder="Опишите ваш вопрос..." required></textarea>
                        </fieldset>
                    </div>
                    <div class="group-action">
                        <button type="submit" class="tf-btn animate-btn w-100">Отправить вопрос</button>
                    </div>
                </form>
                <ul class="tf-social-icon-2 hv-dark justify-content-center">
                    <li><a href="https://www.instagram.com/kotlov.by/" target="_blank"><i class="icon icon-InstagramLogo"></i></a></li>
                    <li><a href="https://www.facebook.com/kotlov.by/" target="_blank"><i class="icon icon-FacebookLogo"></i></a></li>
                    <li>
                        <a href="https://t.me/kotlovby" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.289c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.48 13.9l-2.95-.924c-.642-.204-.657-.642.136-.953l11.527-4.445c.535-.194 1.003.131.37.67z"/>
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- /Ask -->

<!-- Compare Offcanvas -->
<div class="offcanvas offcanvas-bottom canvas-compare" id="compare">
    <div class="canvas-wrapper">
        <div class="canvas-body">
            <div class="container">
                <div class="tf-compare-list main-list-clear wrap-empty_text">
                    <div class="tf-compare-head">
                        <h4 class="title letter-space-0">Сравнение товаров</h4>
                    </div>
                    <div class="tf-compare-offcanvas list-empty" id="compare-offcanvas-items">
                        <p class="box-text_empty cl-text-2">Список сравнения пуст</p>
                    </div>
                    <div class="tf-compare-buttons justify-content-center">
                        <a href="/compare" class="tf-btn animate-btn">Сравнить</a>
                        <button onclick="clearCompare()" class="tf-btn btn-white btn-stroke clear-list-empty tf-compare-button-clear-all">
                            Очистить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Compare Offcanvas -->

<!-- Quick Add -->
<div class="modal modalCentered fade modal-quickadd" id="quickAdd">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="d-flex align-items-center justify-content-between mb-20">
                <h5>Быстрое добавление</h5>
                <span class="d-flex cs-pointer link" data-bs-dismiss="modal"><i class="icon icon-X2 fs-24"></i></span>
            </div>
            <div class="tf-product-quick_add tf-quick-prd_variant">
                <div class="product-mini-view">
                    <a href="#" class="prd-image" id="quickAdd-link">
                        <img class="img-product aspect-ratio-1" width="120" height="120"
                            src="{{ asset('img/products/product-placeholder.jpg') }}" alt="Товар KOTLOV" id="quickAdd-img">
                    </a>
                    <div class="prd-content">
                        <a href="#" class="prd-name fw-medium link-underline link" id="quickAdd-name">Название товара</a>
                        <div class="price-wrap">
                            <span class="price-new text-primary fw-semibold" id="quickAdd-price">0.00 BYN</span>
                        </div>
                    </div>
                </div>
                <div class="product-total-quantity">
                    <p>Количество:</p>
                    <div class="group-action">
                        <div class="wg-quantity">
                            <button class="btn-quantity btn-decrease"><i class="icon icon-minus"></i></button>
                            <input class="quantity-product" type="text" name="number" value="1">
                            <button class="btn-quantity btn-increase"><i class="icon icon-plus"></i></button>
                        </div>
                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="btn-action-price tf-btn type-xl animate-btn w-100">В корзину</a>
                    </div>
                    <a href="/checkout" class="tf-btn type-xl btn-primary animate-btn w-100">Купить сейчас</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Quick Add -->

<!-- Quick View -->
<div class="offcanvas offcanvas-end canvas-quickview" id="quickView">
    <div class="mini-quick-image">
        <div class="wrap-quick wrapper-scroll-quickview" id="quickView-images">
            <div class="image item-scroll-quickview">
                <img class="aspect-ratio-1" loading="lazy" width="340" height="444"
                    src="{{ asset('img/products/product-placeholder.jpg') }}" alt="Товар KOTLOV" id="quickView-img-main">
            </div>
        </div>
    </div>
    <div class="wrap-canvas">
        <div class="canvas-header ps-md-0">
            <h5 class="title-pop">Быстрый просмотр</h5>
            <span class="icon-close-popup" data-bs-dismiss="offcanvas"><i class="icon icon-X2"></i></span>
        </div>
        <div class="canvas-body ps-md-0">
            <div class="tf-product-quick_view tf-quick-prd_variant">
                <div class="tf-product-info-heading">
                    <p class="product-infor-cate text-caption-01 mb-4" id="quickView-category">Категория</p>
                    <h3 class="product-infor-name mb-12 letter-space-0" id="quickView-name">Название товара</h3>
                    <div class="product-infor-meta mb-20">
                        <div class="meta_rate">
                            <div class="star-wrap normal d-flex align-items-center">
                                <i class="icon icon-Star"></i><i class="icon icon-Star"></i>
                                <i class="icon icon-Star"></i><i class="icon icon-Star"></i>
                                <i class="icon icon-Star"></i>
                            </div>
                            <span class="text-caption-01 cl-text-2" id="quickView-reviews">(0 отзывов)</span>
                        </div>
                    </div>
                    <div class="product-infor-price mb-12">
                        <h4 class="price-on-sale" id="quickView-price">0.00 BYN</h4>
                    </div>
                    <p class="product-infor-desc cl-text-2 mb-12" id="quickView-desc">Описание товара</p>
                </div>
                <div class="br-line"></div>
                <div class="tf-product-variant">
                    <div class="tf-product-total-quantity">
                        <p>Количество:</p>
                        <div class="group-action">
                            <div class="wg-quantity">
                                <button class="btn-quantity btn-decrease"><i class="icon icon-minus"></i></button>
                                <input class="quantity-product" type="text" name="number" value="1">
                                <button class="btn-quantity btn-increase"><i class="icon icon-plus"></i></button>
                            </div>
                            <a href="#shoppingCart" data-bs-toggle="offcanvas" class="btn-action-price tf-btn type-xl animate-btn w-100">В корзину</a>
                        </div>
                        <a href="/checkout" class="tf-btn type-xl btn-primary animate-btn w-100">Купить сейчас</a>
                    </div>
                </div>
                <div class="box-action">
                    <a href="#" class="tf-btn-line-2 style-primary fw-semibold" id="quickView-detail-link">Подробнее о товаре</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Quick View -->

<!-- Shopping Cart -->
<div class="offcanvas offcanvas-end popup-shopping-cart" id="shoppingCart">
    <div class="tf-minicart-recommendations">
        <div class="title d-flex justify-content-between align-items-center">
            <h5>Вам может понравиться</h5>
            <i class="icon icon-X2 link remove fs-24 cs-pointer"></i>
        </div>
        <div class="wrap-recommendations">
            <div class="list-cart" id="cart-recommendations"></div>
        </div>
    </div>
    <div class="canvas-wrapper">
        <div class="popup-header">
            <div class="d-flex align-items-center justify-content-between mb-12">
                <h5 class="title">Корзина</h5>
                <span class="icon-X2 icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
            <div class="cart-threshold">
                <p class="text" id="cart-threshold-text">
                    Добавьте ещё на
                    <span class="text-primary fw-7" id="cart-threshold-remaining">
                        {{ number_format(config('shop.free_delivery_threshold', 400), 2, '.', ' ') }} BYN
                    </span>
                    для бесплатной доставки
                </p>
                <div class="tf-progress-bar tf-progress-ship">
                    <div class="value" id="cart-threshold-bar" style="width: 0%" data-progress="0"></div>
                </div>
            </div>
        </div>
        <div class="wrap">
            <div class="tf-mini-cart-wrap list-file-delete wrap-empty_text">
                <div class="tf-mini-cart-main">
                    <div class="tf-mini-cart-sroll">
                        <div class="tf-mini-cart-items list-empty" id="cart-items">
                            <div class="box-text_empty type-shop_cart">
                                <div class="shop-empty_top">
                                    <span class="icon"><i class="icon-Handbag"></i></span>
                                    <h4 class="text-emp">Корзина пуста</h4>
                                    <p class="cl-text-2">Добавьте товары из каталога отопительного оборудования</p>
                                </div>
                                <div class="shop-empty_bot">
                                    <a href="/catalog" class="tf-btn animate-btn">В каталог</a>
                                    <a href="/" class="tf-btn btn-stroke">На главную</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tf-mini-cart-bottom box-empty_clear">
                    <div class="tf-mini-cart-tool">
                        <div class="tf-mini-cart-tool-btn btn-add-note">
                            <i class="icon icon-NotePencil"></i>
                            <div class="lh-24">Заметка</div>
                        </div>
                        <div class="tf-mini-cart-tool-btn btn-estimate-shipping">
                            <i class="icon icon-Truck"></i>
                            <div class="lh-24">Доставка</div>
                        </div>
                        <div class="tf-mini-cart-tool-btn btn-add-gift">
                            <i class="icon icon-SealPercent"></i>
                            <div class="lh-24">Купон</div>
                        </div>
                    </div>
                    <div class="tf-mini-cart-bottom-wrap">
                        <div class="tf-mini-cart-total">
                            <h5 class="text-total d-flex align-content-center justify-content-between">
                                <span class="subtotal">Итого</span>
                                <span class="total-price tf-totals-total-value">0.00 BYN</span>
                            </h5>
                        </div>
                        <div class="checkbox-wrap">
                            <input class="tf-check style-2" type="checkbox" id="agree-term">
                            <label for="agree-term">Я согласен с
                                <a href="/privacy" class="text-decoration-underline" target="_blank">
                                    условиями обработки данных
                                </a>
                            </label>
                        </div>
                        <p id="agree-term-error"
                            style="display:none;color:#dc2626;font-size:12px;margin:4px 0 8px;">
                            Необходимо принять условия обработки данных
                        </p>
                        <div class="tf-mini-cart-view-checkout">
                            <a href="/cart" class="tf-btn btn-stroke">Корзина</a>
                            <a href="#" id="mini-cart-checkout-btn" class="tf-btn animate-btn">
                                Оформить заказ
                            </a>
                        </div>
                        <a href="/catalog" class="d-flex justify-content-center fw-semibold text-center link">Продолжить покупки</a>
                    </div>
                </div>
                {{-- Заметка --}}
                <div class="tf-mini-cart-tool-openable add-note">
                    <div class="overlay tf-mini-cart-tool-close"></div>
                    <form class="tf-mini-cart-tool-content mini-cart-ajax-form"
                          data-url="{{ route('cart.note') }}">
                        @csrf
                        <label for="cart-note" class="tf-mini-cart-tool-text h6 fw-medium">
                            <i class="icon icon-NotePencil"></i> Заметка к заказу
                        </label>
                        <textarea name="note" id="cart-note"
                            placeholder="Комментарий к заказу...">{{ session('cart_note') }}</textarea>
                        <div class="tf-cart-tool-btns">
                            <button class="subscribe-button tf-btn animate-btn" type="submit">
                                Сохранить
                            </button>
                            <div class="tf-btn btn-stroke tf-mini-cart-tool-close">Отмена</div>
                        </div>
                        <p class="mini-cart-form-msg text-caption-01 cl-text-2 mt-8" style="display:none;"></p>
                    </form>
                </div>

                {{-- Доставка --}}
                <div class="tf-mini-cart-tool-openable estimate-shipping">
                    <div class="overlay tf-mini-cart-tool-close"></div>
                    <form class="tf-mini-cart-tool-content mini-cart-ajax-form"
                          data-url="{{ route('cart.delivery') }}">
                        @csrf
                        <div class="tf-mini-cart-tool-text h6 fw-medium">
                            <i class="icon icon-Truck"></i> Расчёт доставки
                        </div>
                        <div class="form-content gap-10">
                            <div class="tf-select">
                                <select class="w-100" name="delivery_region">
                                    <option value="">— выберите область —</option>
                                    <option value="Минск" {{ session('cart_delivery_region') === 'Минск' ? 'selected' : '' }}>Минск</option>
                                    <option value="Минская область" {{ session('cart_delivery_region') === 'Минская область' ? 'selected' : '' }}>Минская область</option>
                                    <option value="Брестская область" {{ session('cart_delivery_region') === 'Брестская область' ? 'selected' : '' }}>Брестская область</option>
                                    <option value="Гродненская область" {{ session('cart_delivery_region') === 'Гродненская область' ? 'selected' : '' }}>Гродненская область</option>
                                    <option value="Витебская область" {{ session('cart_delivery_region') === 'Витебская область' ? 'selected' : '' }}>Витебская область</option>
                                    <option value="Могилёвская область" {{ session('cart_delivery_region') === 'Могилёвская область' ? 'selected' : '' }}>Могилёвская область</option>
                                    <option value="Гомельская область" {{ session('cart_delivery_region') === 'Гомельская область' ? 'selected' : '' }}>Гомельская область</option>
                                </select>
                            </div>
                            <input type="text" name="delivery_city"
                                value="{{ session('cart_delivery_city') }}"
                                placeholder="Ваш город">
                        </div>
                        <div class="tf-cart-tool-btns">
                            <button class="subscribe-button tf-btn animate-btn" type="submit">
                                Сохранить
                            </button>
                            <div class="tf-btn btn-stroke tf-mini-cart-tool-close">Отмена</div>
                        </div>
                        <p class="mini-cart-form-msg text-caption-01 cl-text-2 mt-8" style="display:none;"></p>
                    </form>
                </div>

                {{-- Промокод --}}
                <div class="tf-mini-cart-tool-openable add-gift">
                    <div class="overlay tf-mini-cart-tool-close"></div>
                    <form class="tf-mini-cart-tool-content mini-cart-ajax-form"
                          data-url="{{ route('cart.coupon') }}">
                        @csrf
                        <div class="tf-mini-cart-tool-text h6 fw-medium">
                            <i class="icon icon-SealPercent"></i> Промокод
                        </div>
                        <div class="wrap">
                            <fieldset class="tf-field">
                                <input type="text" name="coupon"
                                    value="{{ session('cart_coupon') }}"
                                    placeholder="Введите промокод">
                            </fieldset>
                        </div>
                        <div class="tf-cart-tool-btns">
                            <button class="subscribe-button tf-btn animate-btn" type="submit">
                                Применить
                            </button>
                            <div class="tf-btn btn-stroke line tf-mini-cart-tool-close">Отмена</div>
                        </div>
                        <p class="mini-cart-form-msg text-caption-01 cl-text-2 mt-8" style="display:none;"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Shopping Cart -->

<!-- Search -->
<div class="modal modalCentered fade modal-search" id="search">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="d-flex align-items-center justify-content-between gap-10">
                <h3>Поиск</h3>
                <span class="icon-close-popup flex-shrink-0" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            </div>
            <form action="/search" method="GET" class="form-search-nav style-2" autocomplete="off">
                <fieldset style="position:relative;flex:1;">
                    <input type="text" name="q" id="modal-search-input"
                        placeholder="Поиск по каталогу..." required autocomplete="off">
                    <div id="modal-search-suggest" class="search-suggest-dropdown"></div>
                </fieldset>
                <button type="submit" class="btn-action"><i class="icon icon-MagnifyingGlass"></i></button>
            </form>
            <div class="recently-view" id="recently-viewed-block">
                <p class="h5 mb-16">Недавно просмотренные</p>
                <div dir="ltr" class="swiper tf-swiper mb-24" data-preview="4" data-tablet="3"
                    data-mobile-sm="2" data-mobile="2" data-space-lg="30" data-space-md="20" data-space="10">
                    <div class="swiper-wrapper" id="recently-viewed-items"></div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Search -->

<!-- Register -->
<div class="modal modalCentered fade modal-log" id="register">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <h3 class="title-pop mb-8">Регистрация</h3>
                <p class="desc-pop cl-text-2">Создайте аккаунт для удобного оформления заказов.</p>
            </div>
            <div class="modal-main">
                <form action="/register" method="POST" class="form-log">
                    @csrf
                    <div class="form-content">
                        <fieldset class="tf-field">
                            <label for="reg-name" class="tf-lable fw-medium">Ваше имя <span class="text-primary">*</span></label>
                            <input type="text" name="name" id="reg-name" placeholder="Ваше имя*" required>
                        </fieldset>
                        <fieldset class="tf-field">
                            <label for="reg-email" class="tf-lable fw-medium">Email <span class="text-primary">*</span></label>
                            <input type="email" name="email" id="reg-email" placeholder="Ваш email*" required>
                        </fieldset>
                        <fieldset class="tf-field password-wrapper">
                            <label for="register-password" class="tf-lable fw-medium">Пароль <span class="text-primary">*</span></label>
                            <div class="password-wrapper w-100">
                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                <input class="password-field" type="password" name="password" id="register-password" placeholder="Пароль*" required>
                            </div>
                        </fieldset>
                        <fieldset class="tf-field password-wrapper">
                            <label for="register-password-confirm" class="tf-lable fw-medium">Повторите пароль <span class="text-primary">*</span></label>
                            <div class="password-wrapper w-100">
                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                <input class="password-field" type="password" name="password_confirmation" id="register-password-confirm" placeholder="Повторите пароль*" required>
                            </div>
                        </fieldset>
                    </div>
                    <div class="group-action">
                        <button type="submit" class="action-create-account tf-btn animate-btn w-100">Создать аккаунт</button>
                        <a href="#sign" data-bs-toggle="modal" class="tf-btn btn-stroke">Войти</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Register -->

<!-- Sign In -->
<div class="modal modalCentered fade modal-log" id="sign">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <h3 class="title-pop mb-8">Вход в аккаунт</h3>
                <p class="desc-pop cl-text-2">Войдите для доступа к заказам и личному кабинету.</p>
            </div>
            <div class="modal-main">
                <form action="/login" method="POST" class="form-log">
                    @csrf
                    <div class="form-content">
                        <fieldset class="tf-field">
                            <label for="user-name-log" class="tf-lable fw-medium">Email <span class="text-primary">*</span></label>
                            <input type="email" name="email" id="user-name-log" placeholder="Ваш email*" required>
                        </fieldset>
                        <fieldset class="tf-field password-wrapper">
                            <label for="password" class="tf-lable fw-medium">Пароль <span class="text-primary">*</span></label>
                            <div class="password-wrapper w-100">
                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                <input class="password-field" type="password" name="password" id="password" placeholder="Пароль*" required>
                            </div>
                        </fieldset>
                        <fieldset class="field-bottom">
                            <div class="checkbox-wrap">
                                <input class="tf-check style-2" type="checkbox" name="remember" id="remember">
                                <label for="remember">Запомнить меня</label>
                            </div>
                            <a href="#modalForgot" data-bs-toggle="modal" class="link text-decoration-underline">
                                <span class="text-caption-01 fw-semibold">Забыли пароль?</span>
                            </a>
                        </fieldset>
                    </div>
                    <div class="group-action">
                        <button type="submit" class="tf-btn animate-btn w-100">Войти</button>
                        <a href="#register" data-bs-toggle="modal" class="tf-btn btn-stroke">Создать аккаунт</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Sign In -->
<script>
document.querySelectorAll('.mb-cat-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var item = this.closest('.mb-cat-item');
        item.classList.toggle('open');
    });
});
</script>
