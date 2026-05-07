<header id="header" class="kotlov-header">
    <div class="container flex aic sb">

        <a class="logo" href="/">
            <img src="/img/logo.png" alt="Kotlov.by">
        </a>

        <div class="wrap-search flex aic sb">

            <div class="choose-city">
                <span class="geo-city-text">Ваш город {{ $geo ?? 'Минск' }}?</span>
                <span class="choose-city_yes">Да</span>
                <span class="geo-city-name">Выбрать другой город</span>
            </div>

            <div class="change-city">
                <div class="geo-icon"></div>
                <span class="geo-city-name">{{ $geo ?? 'Минск' }}</span>
            </div>

            <ul class="menu">
                <li><a class="menu_item" href="/catalog">Каталог</a></li>
                <li><a class="menu_item" href="/kotly">Котлы</a></li>
                <li><a class="menu_item" href="/pechki">Печи</a></li>
                <li><a class="menu_item" href="/kaminy">Камины</a></li>
                <li><a class="menu_item" href="/dymohody">Дымоходы</a></li>
                <li><a class="menu_item" href="/installers">Монтаж</a></li>
                <li><a class="menu_item" href="/partners">Партнёрам</a></li>
            </ul>

            <div class="mob-menu-btn">
                <span></span>
            </div>

            <form method="get" action="/search" id="search">
                <i class="fal fa-search go-search"></i>
                <input type="text" name="q" placeholder="Котёл, печь, камин, дымоход...">
            </form>

            <div class="res-search">
                <div class="ser-header">Результаты поиска</div>
                <div class="wrap-serb"></div>
            </div>

            <div class="close"></div>
        </div>

        <div class="phone">
            <div>
                <i class="fas fa-caret-down"></i>
                <div class="phone_text">{{ $phone1 ?? '+375 [33] 354-40-41' }}</div>
            </div>

            <div class="more-phone">
                <ul class="menu-phones">
                    <li class="menu-phones_item">
                        <a href="tel:{{ $pCall1 ?? '+375333544041' }}">
                            {{ $phone1 ?? '+375 [33] 354-40-41' }}
                        </a>
                    </li>

                    <li class="menu-phones_item">
                        <a href="tel:{{ $pCall3 ?? '+375257436710' }}">
                            {{ $phone3 ?? '+375 [25] 743-67-10' }}
                        </a>
                    </li>

                    <li class="menu-phones_item">
                        <a href="tel:{{ $pCall4 ?? '+375173885958' }}">
                            {{ $phone4 ?? '+375 [17] 388-59-58' }}
                        </a>
                    </li>
                </ul>

                <p class="morp-days">
                    Пн-Пт: {{ $time1 ?? '9:00–18:00' }}<br>
                    Сб: {{ $time2 ?? '10:00–14:00' }}<br>
                    Вс: {{ $time3 ?? 'выходной' }}
                </p>

                <p class="morp-addr">
                    {{ $address ?? 'Минск, ул. Селицкого, 39Б, каб. 23' }}
                </p>

                <p class="morp-mail">
                    {{ $email ?? 'info@kotlov.by' }}
                </p>

                <div class="close"></div>
            </div>
        </div>

    </div>
</header>