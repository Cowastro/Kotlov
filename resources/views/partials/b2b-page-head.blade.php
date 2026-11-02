{{-- B2B header for the partner's cart and checkout: the retail site chrome is hidden (minimalLayout). --}}
<style>
.b2b-page-head { display:flex; align-items:center; justify-content:space-between; gap:24px; padding:24px 0 18px; border-bottom:1px solid #e5e7eb; }
.b2b-page-head strong { font-size:22px; letter-spacing:-.02em; }
.b2b-page-head small { font-size:12px; color:#d94045; }
.b2b-page-head nav { display:flex; gap:10px; }
.b2b-page-head nav a { padding:10px 16px; border:1px solid #d8dde4; border-radius:10px; font-weight:600; }
.b2b-page-head nav a.is-current { border-color:#162231; background:#162231; color:#fff; }
@media (max-width: 767px) { .b2b-page-head { padding-inline:15px; } .b2b-page-head nav a:first-child { display:none; } }
</style>
<div class="container">
    <header class="b2b-page-head">
        <a href="{{ route('b2b.catalog') }}"><strong>KOTLOV.BY <small>B2B</small></strong></a>
        <nav>
            <a href="{{ route('b2b.catalog') }}">Каталог</a>
            <a href="{{ route('cart') }}" @class(['is-current' => request()->routeIs('cart')])>Корзина</a>
            <a href="{{ route('b2b.cabinet', ['section' => 'orders']) }}">Мои заказы</a>
        </nav>
    </header>
</div>
