<details class="mb-16 border rounded-3 p-3" data-product-delivery="{{ route('product.delivery-estimate') }}">
    <summary class="fw-semibold">Рассчитать Европочту до отделения <span class="d-block text-caption-01 fw-normal">Оплата при получении</span></summary>
    <p class="text-caption-01 mt-2">Расчёт за один сигнализатор.</p>
    <div data-product-picker hidden>
        @include('partials.evropochta-branch-picker', ['branches' => [], 'branchId' => ''])
    </div>
    <p class="mt-2 fw-medium" data-product-rate role="status"></p>
    <button type="button" class="link text-decoration-underline" data-product-retry hidden>Повторить расчёт</button>
</details>
@push('scripts')
    <script src="{{ asset('assets/js/product-delivery-estimate.js') }}?v={{ filemtime(public_path('assets/js/product-delivery-estimate.js')) }}"></script>
@endpush
