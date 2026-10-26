{{-- Top of every «Дымоходы» section: the calculator assembles the whole kit, the list below sells single parts.
     A diameter chosen in the quick filters is passed on, so the calculator opens on the same size. --}}
@php
    $chimneyCtaParams = array_filter([
        'chimney_diameter' => request('chimney_diameter') ?: request('mono_diameter'),
    ]);
    $chimneyCtaDiscount = (int) config('shop.chimney_bundle_discount.percent', 10);
@endphp
<section class="chimney-selector-cta" aria-labelledby="chimney-selector-cta-title">
    <div class="container">
        <div class="chimney-selector-cta__inner">
            <div>
                <span class="chimney-selector-cta__eyebrow">Калькулятор дымохода</span>
                <h2 id="chimney-selector-cta-title">Не знаете, какие элементы нужны?</h2>
                <p>Укажите диаметр выхода котла, печи или камина, схему и высоту — калькулятор соберёт весь комплект: трубы, колена, тройники, крепления и проходы, и посчитает цену. Схему можно сразу передать менеджеру на проверку.</p>
                <div class="chimney-selector-cta__facts" aria-hidden="true">
                    <span>Моно или сэндвич</span>
                    <span>Диаметр и высота</span>
                    @if ($chimneyCtaDiscount > 0)
                        <span>−{{ $chimneyCtaDiscount }}% вместе с котлом или печью</span>
                    @endif
                </div>
            </div>
            <div class="chimney-selector-cta__actions">
                <a class="chimney-selector-cta__button" href="{{ route('chimney-selector', $chimneyCtaParams) }}">Собрать дымоход в калькуляторе</a>
                <a class="chimney-selector-cta__all" href="#catalog-products">Или выбрать элементы по отдельности</a>
            </div>
        </div>
    </div>
</section>
<style>
    .chimney-selector-cta{margin-top:24px}
    .chimney-selector-cta__inner{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:28px;align-items:center;padding:24px 30px;border-radius:22px;background:linear-gradient(135deg,#151515 0%,#2b2b2b 100%);color:#fff;box-shadow:0 18px 45px rgba(0,0,0,.12)}
    .chimney-selector-cta__eyebrow{display:block;margin-bottom:6px;color:#ff696d;font-size:12px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}
    .chimney-selector-cta h2{margin:0 0 8px;color:#fff;font-size:clamp(22px,2.6vw,30px);line-height:1.15}
    .chimney-selector-cta p{max-width:780px;margin:0;color:rgba(255,255,255,.74);font-size:14px;line-height:1.55}
    .chimney-selector-cta__facts{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
    .chimney-selector-cta__facts span{padding:6px 10px;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.9);font-size:12px}
    .chimney-selector-cta__actions{display:flex;min-width:280px;flex-direction:column;gap:9px}
    .chimney-selector-cta__button{display:flex;align-items:center;justify-content:center;min-height:52px;padding:12px 22px;border-radius:999px;background:#df4145;color:#fff;font-weight:750;line-height:1.2;text-align:center}
    .chimney-selector-cta__button:hover{background:#c93438;color:#fff}
    .chimney-selector-cta__all{color:rgba(255,255,255,.78);font-size:13px;text-align:center;text-decoration:underline;text-underline-offset:4px}
    .chimney-selector-cta__all:hover{color:#fff}
    @media(max-width:767px){
        .chimney-selector-cta{margin-top:16px}
        .chimney-selector-cta__inner{grid-template-columns:1fr;gap:16px;padding:20px 18px}
        .chimney-selector-cta p{font-size:13px}
        .chimney-selector-cta__facts{display:none}
        .chimney-selector-cta__actions{min-width:0}
        .chimney-selector-cta__button{width:100%}
    }
</style>
