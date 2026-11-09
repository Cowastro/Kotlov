@if($citySelectorEnabled ?? false)
    <div class="offcanvas offcanvas-bottom city-selector-offcanvas" id="citySelector" tabindex="-1" style="height:auto;max-height:80vh;">
        <div class="canvas-header" style="border-bottom:1px solid var(--line,#e8e8e8);">
            <h6 class="fw-semibold mb-0">Выберите ваш город</h6>
            <span class="icon-close-popup" data-bs-dismiss="offcanvas" style="cursor:pointer;">
                <i class="icon icon-X2"></i>
            </span>
        </div>
        <div class="canvas-body" style="overflow-y:auto;">
            <form method="POST" action="{{ route('city.select.submit') }}" class="container-full py-24">
                @csrf
                <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
                <div class="d-flex flex-wrap gap-8 mb-20">
                    @foreach(($citySelectorRegions ?? []) as $region => $cities)
                        <button type="button"
                            class="city-region-tab tf-btn animate-btn {{ $loop->first ? 'btn-fill' : 'btn-line' }}"
                            data-region="{{ $loop->index }}" style="font-size:13px;padding:6px 16px;">
                            {{ $region }}
                        </button>
                    @endforeach
                </div>

                @foreach(($citySelectorRegions ?? []) as $region => $cities)
                    <div class="city-region-panel {{ $loop->first ? '' : 'd-none' }}" data-panel="{{ $loop->index }}">
                        <div class="d-flex flex-wrap gap-8">
                            @foreach($cities as $city)
                                <button type="submit" name="slug" value="{{ $city['slug'] }}"
                                   class="city-link {{ ($selectedCityName ?? $cityName ?? 'Минск') === $city['name'] ? 'active' : '' }}"
                                   style="padding:6px 14px;border:1px solid var(--line,#e0e0e0);border-radius:20px;background:#fff;font:inherit;font-size:14px;color:var(--text,#222);cursor:pointer;transition:all .2s;white-space:nowrap;">
                                    {{ $city['name'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </form>
        </div>
    </div>
    <style>
    .city-link:hover,.city-link.active{background:#222;color:#fff!important;border-color:#222!important;}
    .city-region-tab.btn-fill{background:#222;color:#fff;border-color:#222;}
    .city-region-tab.btn-line{background:#fff;color:#222;border:1px solid #e0e0e0;}
    .city-region-tab.btn-line:hover{background:#f5f5f5;}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.city-region-tab').forEach(function(btn){
            btn.addEventListener('click',function(){
                var r=this.dataset.region;
                document.querySelectorAll('.city-region-tab').forEach(function(b){
                    b.classList.remove('btn-fill');b.classList.add('btn-line');
                });
                this.classList.remove('btn-line');this.classList.add('btn-fill');
                document.querySelectorAll('.city-region-panel').forEach(function(p){
                    p.classList.add('d-none');
                });
                document.querySelector('[data-panel="'+r+'"]').classList.remove('d-none');
            });
        });
    });
    </script>
@endif
