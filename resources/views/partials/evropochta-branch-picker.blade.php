<div data-evropochta-picker
    data-branches="{{ json_encode(array_map(fn (array $branch) => $branch['label'], $branches), JSON_UNESCAPED_UNICODE) }}">
    <label class="tf-lable fw-medium" for="evropochta-search">Отделение получения</label>
    <input type="search" id="evropochta-search" placeholder="Город, улица или номер отделения"
        autocomplete="off" aria-controls="evropochta-results" aria-describedby="evropochta-search-status" @disabled($pickerDisabled ?? false)>
    <input type="hidden" id="evropochta-branch" name="evropochta_branch" value="{{ is_scalar($branchId) ? $branchId : '' }}" @disabled($pickerDisabled ?? false)>
    <p id="evropochta-search-status" class="text-caption-01 cl-text-2" role="status"></p>
    <div id="evropochta-results" hidden></div>
</div>

@once
    @push('styles')
        <style>
            #evropochta-results:not([hidden]) { max-height: 16rem; overflow-y: auto; border: 1px solid #e7e2dc; border-radius: 8px; }
            #evropochta-results button { display: block; width: 100%; padding: 10px 12px; text-align: left; }
            #evropochta-results button + button { border-top: 1px solid #e7e2dc; }
            #evropochta-results button:hover, #evropochta-results button:focus-visible { background: #f5f3f2; }
        </style>
    @endpush

    @push('scripts')
        <script src="{{ asset('assets/js/evropochta-branch-picker.js') }}?v={{ filemtime(public_path('assets/js/evropochta-branch-picker.js')) }}"></script>
    @endpush
@endonce
