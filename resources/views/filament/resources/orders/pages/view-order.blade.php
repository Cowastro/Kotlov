<x-filament-panels::page>
@php
    $order = $this->getRecord();
    $order->loadMissing('items.product', 'user', 'statusHistory.user');

    $paymentNames  = collect(config('shop.payment_methods',  []))->mapWithKeys(fn($m,$k) => [$k => $m['name']])->toArray();
    $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn($m,$k) => [$k => $m['name']])->toArray();

    $statusColors = [
        'new'             => 'info',
        'confirmed'       => 'warning',
        'processing'      => 'warning',
        'waiting_payment' => 'warning',
        'paid'            => 'success',
        'shipped'         => 'primary',
        'delivered'       => 'success',
        'completed'       => 'success',
        'cancelled'       => 'danger',
    ];
    $statusColor = $statusColors[$order->status] ?? 'gray';

    $paymentStatusLabel = match($order->payment_status) {
        'paid'    => ['label' => 'Оплачен',        'color' => 'success'],
        'pending' => ['label' => 'Ожидает оплаты', 'color' => 'warning'],
        default   => ['label' => $order->payment_status, 'color' => 'gray'],
    };

    $byn = fn($v) => number_format((float)$v, 2, '.', ' ') . ' BYN';

    $deliveryPrice = (float)$order->delivery_price;
    $isKitDelivery = $order->delivery_type === 'kit';
    $deliveryFree  = $deliveryPrice === 0.0 && !$isKitDelivery;
    $deliveryClarify = $isKitDelivery && $deliveryPrice === 0.0;

    $hasCompany = $order->company_name || $order->company_unp || $order->company_address || $order->company_email;
@endphp

<div class="space-y-4 max-w-none">

    {{-- ── 1. Основная информация ───────────────────────────────────────────── --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">

            <div class="flex items-center gap-2">
                <x-heroicon-o-document-text class="h-5 w-5 text-gray-400 shrink-0"/>
                <span class="text-xs text-gray-500 dark:text-gray-400">Заказ</span>
                <span class="font-bold text-lg text-gray-950 dark:text-white">{{ $order->number }}</span>
            </div>

            <x-filament::badge :color="$statusColor">
                {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}
            </x-filament::badge>

            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-calendar class="h-4 w-4 shrink-0"/>
                <span>Создан: {{ $order->created_at->format('d.m.Y H:i') }}</span>
            </div>

            @if($order->updated_at != $order->created_at)
            <div class="flex items-center gap-1.5 text-sm text-gray-400 dark:text-gray-500">
                <x-heroicon-o-pencil-square class="h-4 w-4 shrink-0"/>
                <span>Обновлён: {{ $order->updated_at->format('d.m.Y H:i') }}</span>
            </div>
            @endif

            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-shopping-bag class="h-4 w-4 shrink-0"/>
                <span>{{ $order->items->sum('quantity') }} шт.</span>
            </div>

            <div class="ml-auto flex items-center gap-3">
                <x-filament::badge :color="$paymentStatusLabel['color']">
                    {{ $paymentStatusLabel['label'] }}
                </x-filament::badge>
                <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $byn($order->total) }}
                </span>
            </div>

        </div>
    </div>

    {{-- ── 2. Клиент / Доставка / Оплата ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

        {{-- Клиент --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-user class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Клиент</span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Имя</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Телефон</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $order->customer_phone }}</dd>
                </div>
                @if($order->customer_email)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Email</dt>
                    <dd class="text-gray-700 dark:text-gray-300 break-all">{{ $order->customer_email }}</dd>
                </div>
                @endif
                @if($order->user)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Аккаунт</dt>
                    <dd class="text-gray-600 dark:text-gray-400 text-xs">{{ $order->user->email }}</dd>
                </div>
                @endif
            </dl>

            @if($hasCompany)
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Юридическое лицо</p>
                <dl class="space-y-2.5 text-sm">
                    @if($order->company_name)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Организация</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $order->company_name }}</dd>
                    </div>
                    @endif
                    @if($order->company_unp)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">УНП / ИНН</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $order->company_unp }}</dd>
                    </div>
                    @endif
                    @if($order->company_address)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Адрес компании</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $order->company_address }}</dd>
                    </div>
                    @endif
                    @if($order->company_email)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Email компании</dt>
                        <dd class="text-gray-700 dark:text-gray-300 break-all">{{ $order->company_email }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>

        {{-- Доставка --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-truck class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Доставка</span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Тип</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">
                        {{ \App\Models\Order::DELIVERY_TYPES[$order->delivery_type] ?? ($deliveryNames[$order->delivery_type] ?? $order->delivery_type) }}
                    </dd>
                </div>
                @if($order->delivery_region)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Область / регион</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $order->delivery_region }}</dd>
                </div>
                @endif
                @if($order->delivery_city)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Город</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $order->delivery_city }}</dd>
                </div>
                @endif
                @if($order->delivery_address)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Адрес</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $order->delivery_address }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Стоимость</dt>
                    <dd class="font-medium
                        @if($deliveryClarify) text-amber-600 dark:text-amber-400
                        @elseif($deliveryFree) text-green-600 dark:text-green-400
                        @else text-gray-900 dark:text-white @endif">
                        @if($deliveryClarify)
                            Стоимость уточняется
                        @elseif($deliveryFree)
                            Бесплатно
                        @else
                            {{ $byn($order->delivery_price) }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Оплата --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-credit-card class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Оплата</span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Способ</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">
                        {{ \App\Models\Order::PAYMENT_TYPES[$order->payment_type] ?? ($paymentNames[$order->payment_type] ?? $order->payment_type) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Статус</dt>
                    <dd>
                        <x-filament::badge :color="$paymentStatusLabel['color']">
                            {{ $paymentStatusLabel['label'] }}
                        </x-filament::badge>
                    </dd>
                </div>
                @if($order->coupon_code)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Промокод</dt>
                    <dd><x-filament::badge color="success">{{ $order->coupon_code }}</x-filament::badge></dd>
                </div>
                @endif
                @if((float)$order->discount > 0)
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Скидка</dt>
                    <dd class="font-medium text-red-600 dark:text-red-400">−{{ $byn($order->discount) }}</dd>
                </div>
                @endif
            </dl>
        </div>

    </div>

    {{-- ── 3. Товары заказа ─────────────────────────────────────────────────── --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100 dark:border-white/10">
            <x-heroicon-o-shopping-bag class="h-4 w-4 text-primary-500 shrink-0"/>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Товары заказа</span>
            <x-filament::badge color="gray" class="ml-1">{{ $order->items->sum('quantity') }} шт.</x-filament::badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/10 text-xs text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2.5 text-left font-medium w-16">Фото</th>
                        <th class="px-4 py-2.5 text-left font-medium">Товар</th>
                        <th class="px-4 py-2.5 text-left font-medium w-36">Артикул</th>
                        <th class="px-4 py-2.5 text-center font-medium w-20">Кол-во</th>
                        <th class="px-4 py-2.5 text-right font-medium w-32">Цена</th>
                        <th class="px-5 py-2.5 text-right font-medium w-36">Сумма</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @foreach($order->items as $item)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        <td class="px-4 py-3">
                            @if($item->product)
                                @php try { $imgUrl = $item->product->imageUrl(0); } catch (\Throwable $e) { $imgUrl = null; } @endphp
                                @if($imgUrl)
                                    <img src="{{ $imgUrl }}" alt="" class="h-12 w-12 object-contain rounded border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                @else
                                    <div class="h-12 w-12 rounded border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                                        <x-heroicon-o-photo class="h-5 w-5 text-gray-300"/>
                                    </div>
                                @endif
                            @else
                                <div class="h-12 w-12 rounded border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                                    <x-heroicon-o-photo class="h-5 w-5 text-gray-300"/>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ $item->product_name }}
                            @if($item->product?->brand)
                                <div class="text-xs text-gray-400 font-normal mt-0.5">{{ $item->product->brand->name ?? '' }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">
                            {{ $item->product_sku ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $byn($item->price) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-primary-600 dark:text-primary-400">{{ $byn($item->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02]">
                        <td colspan="4" class="px-5 py-3 text-right text-sm text-gray-500 dark:text-gray-400">
                            Товары ({{ $order->items->sum('quantity') }} шт.)
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ $byn($order->subtotal) }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                    <tr class="border-t border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
                        <td colspan="4" class="px-5 py-3 text-right text-sm text-gray-500 dark:text-gray-400">Доставка</td>
                        <td class="px-4 py-3 text-right text-sm font-medium
                            @if($deliveryClarify) text-amber-600 dark:text-amber-400
                            @elseif($deliveryFree) text-green-600 dark:text-green-400
                            @else text-gray-700 dark:text-gray-300 @endif">
                            @if($deliveryClarify) Уточняется
                            @elseif($deliveryFree) Бесплатно
                            @else {{ $byn($order->delivery_price) }}
                            @endif
                        </td>
                        <td class="px-5 py-3"></td>
                    </tr>
                    @if((float)$order->discount > 0)
                    <tr class="border-t border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
                        <td colspan="4" class="px-5 py-3 text-right text-sm text-gray-500 dark:text-gray-400">Скидка</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-red-600 dark:text-red-400">−{{ $byn($order->discount) }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                    @endif
                    <tr class="border-t-2 border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02]">
                        <td colspan="4" class="px-5 py-4 text-right text-base font-semibold text-gray-700 dark:text-gray-200">Итого</td>
                        <td class="px-4 py-4 text-right text-xl font-bold text-primary-600 dark:text-primary-400">{{ $byn($order->total) }}</td>
                        <td class="px-5 py-4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ── 4. Комментарии ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-chat-bubble-left class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Комментарий клиента</span>
            </div>
            @if($order->comment)
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $order->comment }}</p>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">Комментарий отсутствует</p>
            @endif
        </div>

        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-chat-bubble-left-ellipsis class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Комментарий менеджера</span>
            </div>
            @if($order->admin_comment)
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $order->admin_comment }}</p>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">Комментарий отсутствует</p>
            @endif
        </div>

    </div>

    {{-- ── 5. История статусов ──────────────────────────────────────────────── --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
        <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
            <x-heroicon-o-clock class="h-4 w-4 text-primary-500 shrink-0"/>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">История изменений статуса</span>
            @if($order->statusHistory->count())
                <x-filament::badge color="gray" class="ml-1">{{ $order->statusHistory->count() }}</x-filament::badge>
            @endif
        </div>

        @if($order->statusHistory->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/10 text-xs text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-4 text-left font-medium w-40">Дата</th>
                        <th class="py-2 pr-4 text-left font-medium w-36">Кто изменил</th>
                        <th class="py-2 pr-4 text-left font-medium w-36">Было</th>
                        <th class="py-2 pr-4 text-left font-medium w-36">Стало</th>
                        <th class="py-2 text-left font-medium">Комментарий</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @foreach($order->statusHistory->sortByDesc('created_at') as $history)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400 text-xs">
                            {{ $history->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-300 text-xs">
                            {{ $history->user?->name ?? 'Система' }}
                        </td>
                        <td class="py-2.5 pr-4">
                            @if($history->status_from)
                                <x-filament::badge :color="$statusColors[$history->status_from] ?? 'gray'" size="sm">
                                    {{ \App\Models\Order::STATUSES[$history->status_from] ?? $history->status_from }}
                                </x-filament::badge>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 pr-4">
                            <x-filament::badge :color="$statusColors[$history->status_to] ?? 'gray'" size="sm">
                                {{ \App\Models\Order::STATUSES[$history->status_to] ?? $history->status_to }}
                            </x-filament::badge>
                        </td>
                        <td class="py-2.5 text-gray-600 dark:text-gray-400 text-xs">
                            {{ $history->comment ?: '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">История изменений пока отсутствует</p>
        @endif
    </div>

</div>
</x-filament-panels::page>
