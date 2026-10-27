@php
    $ownTransport = config('shop.delivery_methods.own_transport', []);
    $minskTransport = config('shop.delivery_methods.courier', []);
    $routes = collect($ownTransport['routes'] ?? [])->filter(fn ($route) => !empty($route['days']) && !empty($route['destinations']));
    $collapsible = $collapsible ?? false;
@endphp

@if ($routes->isNotEmpty())
    @if ($collapsible)
        <details class="own-transport-schedule" id="own-transport-schedule">
            <summary>
                <span class="own-transport-schedule__summary-icon" aria-hidden="true"><i class="icon icon-Truck"></i></span>
                <span>
                    <strong>График доставки своим транспортом</strong>
                    <small>Минск, Минский район и города по маршрутам</small>
                </span>
            </summary>
            <div class="own-transport-schedule__body">
    @else
        <div class="own-transport-schedule own-transport-schedule--expanded">
            <div class="own-transport-schedule__heading">
                <span class="own-transport-schedule__summary-icon" aria-hidden="true"><i class="icon icon-Truck"></i></span>
                <div>
                    <h6 class="fw-semibold mb-4">Доставка своим транспортом по графику</h6>
                    <p class="cl-text-2 mb-0">Регулярные маршруты по Минску, Минскому району и городам Беларуси.</p>
                </div>
            </div>
            <div class="own-transport-schedule__body">
    @endif

                <div class="own-transport-schedule__routes">
                    @foreach ($routes as $route)
                        <div class="own-transport-route">
                            <strong>{{ $route['days'] }}</strong>
                            <span>{{ implode(', ', $route['destinations']) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="own-transport-schedule__benefit">
                    <p class="mb-4">
                        <strong>Минск и Минский район:</strong>
                        {{ number_format($minskTransport['price'] ?? 10, 0, '.', ' ') }} BYN,
                        от {{ number_format($minskTransport['free_from'] ?? 400, 0, '.', ' ') }} BYN — <strong>бесплатно</strong>.
                    </p>
                    <p class="mb-0">
                        <strong>Города по графику:</strong>
                        менее 500 BYN — 30 BYN; от 500 до 999 BYN — 20 BYN;
                        от 1 000 BYN — <strong>бесплатно</strong>.
                    </p>
                </div>
                @if (!empty($ownTransport['schedule_note']))
                    <p class="own-transport-schedule__note mb-0">{{ $ownTransport['schedule_note'] }}</p>
                @endif

    @if ($collapsible)
            </div>
        </details>
    @else
            </div>
        </div>
    @endif
@endif
