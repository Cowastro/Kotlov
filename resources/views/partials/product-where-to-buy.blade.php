{{-- "Где купить": partner branches of the selected city; phones are revealed on demand and logged. --}}
@php
    $wtbCity = $selectedCity ?? null;
    $wtbBranches = $wtbCity?->exists && \Illuminate\Support\Facades\Schema::hasTable('partner_branches')
        ? \App\Models\PartnerBranch::query()->visibleIn($wtbCity)->get()
        : collect();
    $wtbCityIn = $wtbCity?->name_in ?: 'в '.($selectedCityName ?? 'Минске');
@endphp
@once
    @push('styles')
        <style>
            .wtb { margin-top:20px; padding:20px; border:1px solid var(--line,#e8e8e8); border-radius:16px; background:var(--white,#fff); }
            .wtb__head { display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:6px 12px; margin-bottom:14px; }
            .wtb__head h2 { margin:0; font-size:18px; }
            .wtb__city { font-size:13px; font-weight:600; text-decoration:underline; text-underline-offset:3px; }
            .wtb__list { display:grid; gap:10px; margin:0; padding:0; list-style:none; }
            .wtb__item { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:12px; padding:14px; border-radius:12px; background:var(--bg-2,#f6f5f2); }
            .wtb__item strong { display:block; font-size:15px; line-height:1.3; }
            .wtb__item small { display:block; margin-top:3px; color:var(--text-2,#6b7280); font-size:13px; line-height:1.4; }
            .wtb__phone { padding:9px 14px; border:0; border-radius:999px; background:#1d252c; color:#fff; font-size:13px; font-weight:600; white-space:nowrap; cursor:pointer; }
            .wtb__phone:disabled { opacity:.6; cursor:default; }
            .wtb__phones { display:grid; gap:4px; text-align:right; }
            .wtb__phones a { color:var(--text,#111)!important; font-weight:700; white-space:nowrap; }
            .wtb__note { margin:12px 0 0; color:var(--text-2,#6b7280); font-size:12px; }
            @media (max-width:575px) {
                .wtb__item { grid-template-columns:1fr; }
                .wtb__phone { width:100%; }
                .wtb__phones { text-align:left; }
            }
        </style>
    @endpush
@endonce
<section class="wtb" aria-labelledby="wtb-title" data-where-to-buy>
    <div class="wtb__head">
        <h2 id="wtb-title">Где купить {{ $wtbCityIn }}</h2>
        @if($citySelectorEnabled ?? false)
            <a href="#citySelector" data-bs-toggle="offcanvas" class="wtb__city">Другой город</a>
        @endif
    </div>
    @if($wtbBranches->isNotEmpty())
        <ul class="wtb__list">
            @foreach($wtbBranches as $branch)
                <li class="wtb__item">
                    <div>
                        <strong>{{ $branch->name }}</strong>
                        @if($branch->address)<small>{{ $branch->address }}</small>@endif
                        @if($branch->working_hours)<small>{{ $branch->working_hours }}</small>@endif
                    </div>
                    <div data-wtb-slot>
                        <button type="button" class="wtb__phone"
                            data-wtb-phone="{{ route('partner-branches.phone', $branch) }}"
                            data-product-id="{{ $product->id ?? '' }}">Показать телефон</button>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="wtb__note">Официальные партнёры KOTLOV. Цена и гарантия — как на сайте.</p>
    @else
        <ul class="wtb__list">
            <li class="wtb__item">
                <div>
                    <strong>{{ $cityContact['company_name'] ?? 'KOTLOV.BY' }}</strong>
                    <small>Заказ онлайн с доставкой {{ $wtbCityIn }}</small>
                </div>
                <div class="wtb__phones">
                    @foreach(array_slice($cityContact['phone_links'] ?? [], 0, 1) as $phone)
                        <a href="tel:{{ $phone['href'] }}">{{ $phone['display'] }}</a>
                    @endforeach
                </div>
            </li>
        </ul>
    @endif
</section>
@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-wtb-phone]');
                if (!button) return;
                button.disabled = true;
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(button.dataset.wtbPhone, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
                    body: JSON.stringify({ product_id: button.dataset.productId || null })
                }).then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                  .then(function (data) {
                      var box = document.createElement('div');
                      box.className = 'wtb__phones';
                      (data.phones || []).forEach(function (phone) {
                          var link = document.createElement('a');
                          link.href = 'tel:' + phone.href;
                          link.textContent = phone.display;
                          box.appendChild(link);
                      });
                      button.closest('[data-wtb-slot]').replaceChildren(box);
                  })
                  .catch(function () { button.disabled = false; });
            });
        </script>
    @endpush
@endonce
