<div id="region" class="popup">
    <div class="popup-cont">
        <p>Пожалуйста, выберите свою область и город, либо то что максимально рядом с вами!</p>

        <div class="region-tabs">
            <div class="region-tabs-header">Область - Минская</div>

            <ul class="region-tabs_cont">
                <li data-id="1" class="active">Минская</li>
                <li data-id="2">Гомельская</li>
                <li data-id="3">Брестская</li>
                <li data-id="4">Гродненская</li>
                <li data-id="5">Могилёвская</li>
                <li data-id="6">Витебская</li>
            </ul>
        </div>

        <ul class="obl active obl-num-1">
            @forelse($obl1 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @empty
                <li><a href="https://kotlov.by" class="active">Минск</a></li>
                <li><a href="https://borisov.kotlov.by">Борисов</a></li>
                <li><a href="https://molodechno.kotlov.by">Молодечно</a></li>
            @endforelse
        </ul>

        <ul class="obl obl-num-2">
            @foreach($obl2 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>

        <ul class="obl obl-num-3">
            @foreach($obl3 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>

        <ul class="obl obl-num-4">
            @foreach($obl4 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>

        <ul class="obl obl-num-5">
            @foreach($obl5 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>

        <ul class="obl obl-num-6">
            @foreach($obl6 ?? [] as $city)
                <li>
                    <a href="{{ $city['link'] ?? '#' }}" class="{{ $city['active'] ?? '' }}">
                        {{ $city['name'] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="close"></div>
    </div>

    <div class="mask"></div>
</div>