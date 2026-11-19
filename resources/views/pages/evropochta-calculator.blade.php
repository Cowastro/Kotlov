@extends('layouts.amerce')

@section('title', 'Калькулятор доставки Европочтой | KOTLOV')
@section('description', 'Рассчитайте доставку Европочтой из Минска до отделения по весу и размерам упакованной посылки.')

@section('content')
<main class="flat-spacing">
    <div class="container">
        <div class="mx-auto" style="max-width: 720px;">
            <a href="{{ url('/dostavka') }}" class="link text-caption-01">Доставка, оплата и возврат</a>
            <h1 class="h3 mt-12 mb-12">Калькулятор доставки Европочтой</h1>
            <p class="cl-text-2 mb-24">Из Минска до отделения в регионе. Укажите вес вместе с коробкой и её наружные размеры.</p>

            @if ($estimate)
                <section id="evropochta-estimate-result" class="alert alert-success mb-24" aria-live="polite">
                    <p class="fw-semibold mb-4">Доставка — {{ number_format($estimate['customer_price'], 2, ',', ' ') }} BYN</p>
                    <p>{{ $estimate['address'] }} · {{ $estimate['oversize'] ? 'Громоздкая посылка' : 'Стандартная посылка' }}</p>
                    <p class="text-caption-01 mt-4">Ориентир для указанных параметров. Стоимость коробки отдельно.</p>
                </section>
            @endif

            @if ($branches === [])
                <p class="alert alert-warning">Справочник отделений временно недоступен. Попробуйте рассчитать доставку позже.</p>
            @else
                <form id="evropochta-estimate" class="tf-grid-layout gap-16" action="{{ route('evropochta.calculator.estimate') }}" method="POST">
                    @csrf
                    <fieldset class="tf-field">
                        @include('partials.evropochta-branch-picker', [
                            'branches' => $branches,
                            'branchId' => old('evropochta_branch', $values['evropochta_branch'] ?? ''),
                        ])
                    </fieldset>

                    <fieldset class="tf-field">
                        <label class="tf-lable fw-medium" for="evropochta-box">Типовая коробка</label>
                        <div class="tf-select">
                            <select id="evropochta-box">
                                <option value="">Свои размеры</option>
                                @foreach ($boxes as $box)
                                    <option value="{{ implode(',', $box['outer']) }}">{{ implode(' × ', $box['outer']) }} см</option>
                                @endforeach
                            </select>
                        </div>
                    </fieldset>

                    <div class="tf-grid-layout sm-col-2">
                        @foreach (['weight' => 'Вес с упаковкой, кг', 'length' => 'Длина, см', 'width' => 'Ширина, см', 'height' => 'Высота, см'] as $field => $label)
                            @php($value = old($field, $values[$field] ?? ''))
                            <fieldset class="tf-field">
                                <label class="tf-lable fw-medium" for="evropochta-{{ $field }}">{{ $label }}</label>
                                <input type="number" inputmode="decimal" name="{{ $field }}" id="evropochta-{{ $field }}"
                                    value="{{ is_scalar($value) ? $value : '' }}" min="0.01" max="{{ $field === 'weight' ? 50 : 250 }}" step="0.01" required>
                            </fieldset>
                        @endforeach
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-warning" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p class="text-caption-01 cl-text-2">Расчёт справочный. Для заказа с непроверенными габаритами цену доставки подтвердит менеджер.</p>
                    <button type="submit" class="tf-btn animate-btn">Рассчитать доставку</button>
                </form>
            @endif
        </div>
    </div>
</main>
@endsection

@if ($branches !== [])
    @push('scripts')
        <script>
            (function () {
                var form = document.getElementById('evropochta-estimate');
                window.mountEvropochtaBranchPicker(form.querySelector('[data-evropochta-picker]'));
                var box = document.getElementById('evropochta-box');
                box.addEventListener('change', function () {
                    if (!box.value) return;
                    var dimensions = box.value.split(',');
                    ['length', 'width', 'height'].forEach(function (field, index) {
                        document.getElementById('evropochta-' + field).value = dimensions[index];
                    });
                });
                var result = document.getElementById('evropochta-estimate-result');
                if (result) {
                    ['input', 'change'].forEach(function (event) {
                        form.addEventListener(event, function () { result.style.display = 'none'; });
                    });
                }
            })();
        </script>
    @endpush
@endif
