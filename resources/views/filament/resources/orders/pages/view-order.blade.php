<x-filament-panels::page>
@php
    $order        = $this->getRecord();
    $order->loadMissing('items', 'user');

    $paymentNames  = collect(config('shop.payment_methods',  []))->mapWithKeys(fn($m,$k) => [$k => $m['name']])->toArray();
    $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn($m,$k) => [$k => $m['name']])->toArray();

    $statusColors = [
        'new'        => 'fi-color-info',
        'confirmed'  => 'fi-color-warning',
        'processing' => 'fi-color-warning',
        'shipped'    => 'fi-color-primary',
        'delivered'  => 'fi-color-success',
        'cancelled'  => 'fi-color-danger',
    ];
    $statusColor = $statusColors[$order->status] ?? 'fi-color-gray';

    $paymentStatusLabel = match($order->payment_status) {
        'paid'    => ['label' => 'Оплачен',        'color' => 'fi-color-success'],
        'pending' => ['label' => 'Ожидает оплаты', 'color' => 'fi-color-warning'],
        default   => ['label' => $order->payment_status, 'color' => 'fi-color-gray'],
    };

    $byn = fn($v) => number_format((float)$v, 2, '.', ' ') . ' BYN';
    $deliveryFree = (float)$order->delivery_price === 0.0;
@endphp

<div class="space-y-4">

    {{-- ── 1. Шапка заказа ──────────────────────────────────────────────── --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">

            <div class="flex items-center gap-2 min-w-0">
                <x-heroicon-o-document-text class="h-5 w-5 text-gray-400 shrink-0"/>
                <span class="text-xs text-gray-500 dark:text-gray-400">Заказ</span>
                <span class="font-bold text-base text-gray-950 dark:text-white">{{ $order->number }}</span>
            </div>

            <x-filament::badge :color="str_replace('fi-color-','',$statusColor)">
                {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}
            </x-filament::badge>

            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-calendar class="h-4 w-4 shrink-0"/>
                {{ $order->created_at->format('d.m.Y H:i') }}
            </div>

            <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300">
                <x-heroicon-o-user class="h-4 w-4 shrink-0 text-gray-400"/>
                @if($order->user)
                    {{ $order->user->name }}
                @else
                    <span class="text-gray-400">Гость</span>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-2">
                <x-filament::badge :color="str_replace('fi-color-','',$paymentStatusLabel['color'])">
                    {{ $paymentStatusLabel['label'] }}
                </x-filament::badge>
                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $byn($order->total) }}
                </span>
            </div>

        </div>
    </div>

    {{-- ── 2. Клиент / Доставка / Оплата ────────────────────────────────── --}}
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
            </dl>
        </div>

        {{-- Доставка --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-truck class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Доставка</span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Способ</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">
                        {{ $deliveryNames[$order->delivery_type] ?? $order->delivery_type }}
                    </dd>
                </div>
                @php
                    $addressParts = array_filter([$order->delivery_region, $order->delivery_city, $order->delivery_address]);
                @endphp
                @if(count($addressParts))
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Адрес</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ implode(', ', $addressParts) }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Стоимость</dt>
                    <dd class="{{ $deliveryFree ? 'text-green-600 dark:text-green-400 font-medium' : 'font-medium text-gray-900 dark:text-white' }}">
                        {{ $deliveryFree ? 'Бесплатно' : $byn($order->delivery_price) }}
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
                    <dt class="text-xs text-gray-400 mb-0.5">Способ оплаты</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">
                        {{ $paymentNames[$order->payment_type] ?? $order->payment_type }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-0.5">Статус оплаты</dt>
                    <dd>
                        <x-filament::badge :color="str_replace('fi-color-','',$paymentStatusLabel['color'])">
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
                {{-- Реквизиты для invoice --}}
                @if($order->payment_type === 'invoice' && $order->company_name)
                    <hr class="border-gray-100 dark:border-white/10"/>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Организация</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $order->company_name }}</dd>
                    </div>
                    @if($order->company_unp)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">УНП / ИНН</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $order->company_unp }}</dd>
                    </div>
                    @endif
                @endif
            </dl>
        </div>

    </div>

    {{-- ── 3. Товары заказа ──────────────────────────────────────────────── --}}
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
                        <th class="px-5 py-2.5 text-left font-medium">Товар</th>
                        <th class="px-4 py-2.5 text-left font-medium w-32">Артикул</th>
                        <th class="px-4 py-2.5 text-center font-medium w-20">Кол-во</th>
                        <th class="px-4 py-2.5 text-right font-medium w-32">Цена</th>
                        <th class="px-5 py-2.5 text-right font-medium w-36">Сумма</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @foreach($order->items as $item)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $item->product_name }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">
                            {{ $item->product_sku ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $byn($item->price) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-primary-600 dark:text-primary-400">{{ $byn($item->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 4. Суммы + Комментарии ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

        {{-- Комментарии --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Комментарии</span>
            </div>
            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Комментарий клиента</p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        {{ $order->comment ?: '—' }}
                    </p>
                </div>
                <div class="pt-3 border-t border-gray-100 dark:border-white/10">
                    <p class="text-xs text-gray-400 mb-1">Комментарий менеджера</p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        {{ $order->admin_comment ?: '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Суммы --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 mb-4 border-b border-gray-100 dark:border-white/10 pb-3">
                <x-heroicon-o-calculator class="h-4 w-4 text-primary-500 shrink-0"/>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Итоговые суммы</span>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">
                        Товары ({{ $order->items->sum('quantity') }} шт.)
                    </dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $byn($order->subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Доставка</dt>
                    <dd class="{{ $deliveryFree ? 'text-green-600 dark:text-green-400 font-medium' : 'font-medium text-gray-900 dark:text-white' }}">
                        {{ $deliveryFree ? 'Бесплатно' : $byn($order->delivery_price) }}
                    </dd>
                </div>
                @if((float)$order->discount > 0)
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Скидка</dt>
                    <dd class="font-medium text-red-600 dark:text-red-400">−{{ $byn($order->discount) }}</dd>
                </div>
                @endif
                <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-white/10 mt-2">
                    <dt class="font-semibold text-gray-700 dark:text-gray-200 text-base">Итого</dt>
                    <dd class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $byn($order->total) }}</dd>
                </div>
            </dl>
        </div>

    </div>

</div>
</x-filament-panels::page>
