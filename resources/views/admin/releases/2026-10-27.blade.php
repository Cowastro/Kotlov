@extends('admin.releases.layout')

@section('title', 'Релиз 27.10.2026')

@php
    $admin = fn (string $path = ''): string => url('/admin'.($path !== '' ? '/'.$path : ''));
    $site = fn (string $path): string => url('/'.ltrim($path, '/'));
@endphp

@section('content')
  <header>
    <div class="eyebrow">Внутренний релиз для команды · 27 октября 2026</div>
    <h1>Что нового в каталоге KOTLOV</h1>
    <p class="lead">Дымоходы переключаются по диаметру прямо в карточке, система ТиС «Феррит 1 мм» на сайте, починены фото и цены, разобрана очередь 1С, найдена и исправлена ошибка цен в прайсе bania.</p>
  </header>

  <div class="summary" aria-label="Коротко в цифрах">
    <div><b>Ø</b><span>выбор диаметра у всех дымоходов</span></div>
    <div><b>17</b><span>элементов системы «Феррит 1 мм»</span></div>
    <div><b>21</b><span>карточка с исправленной ценой bania</span></div>
    <div><b>9</b><span>пар дублей объединено</span></div>
  </div>

  <nav class="toc" aria-label="Содержание">
    <a href="#chimneys">Дымоходы</a>
    <a href="#onec">Очередь 1С</a>
    <a href="#prices">Цены и поставщики</a>
    <a href="#cards">Карточки</a>
  </nav>

  <section class="links" aria-labelledby="links-title">
    <h2 id="links-title">Быстрые ссылки</h2>
    <div class="link-groups">
      <div>
        <h3>Админка <small>нужен вход</small></h3>
        <ul class="link-list">
          <li><a href="{{ $admin('integration-products') }}?tab=unmatched">Непривязанные товары 1С</a></li>
          <li><a href="{{ $admin('products') }}">Товары</a></li>
          <li><a href="{{ $admin('chimney-calculator') }}">Калькулятор дымохода</a></li>
        </ul>
      </div>
      <div>
        <h3>На сайте</h3>
        <ul class="link-list">
          <li><a href="{{ $site('dymohody/tis-ferrit-1-mm') }}">Прайс ТиС «Феррит 1 мм»</a></li>
          <li><a href="{{ $site('truby-mono/truba-teplov-i-suhov-mono-tm-r-l1000-430-08-150') }}">Пример: выбор диаметра у трубы</a></li>
          <li><a href="{{ $site('pechki/pec-otopitelnaia-vezuvii-aogt-01') }}">Везувий АОГТ 01 — цена исправлена</a></li>
          <li><a href="{{ $site('podbor-dymohoda') }}">Подбор дымохода</a></li>
        </ul>
      </div>
    </div>
  </section>

  <section id="chimneys">
    <h2>Дымоходы</h2>

    <div class="item">
      <div class="item-head"><h3>Выбор диаметра в карточке</h3><a class="open" href="{{ $site('truby-mono/truba-teplov-i-suhov-mono-tm-r-l1000-430-08-150') }}">Пример</a></div>
      <p>Как у мембранных баков: в карточке трубы, колена, тройника, перехода, зонта или шибера есть блок «Выберите диаметр» с соседними размерами той же детали — тот же производитель, сталь, толщина, длина и угол. Искать соседний диаметр в каталоге больше не нужно.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Система ТиС «Феррит 1 мм»</h3><a class="open" href="{{ $site('dymohody/tis-ferrit-1-mm') }}">Открыть прайс</a></div>
      <p>Банная система из стали AISI 430 толщиной 1 мм: Моно и Термо с керамоволокном, диаметры 115 и 150. Прайс по элементам и диаметрам — как у «Стандарт 30/50», ссылка есть в разделе «Дымоходы». Элементы попадают и в подбор дымохода. У деталей, которые уже были в каталоге, цены не менялись.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Фото дымоходов</h3></div>
      <p>29 карточек показывали заглушку «Фото скоро появится» — исправлено. Часть дымоходов получила точные фото с 21vek.by и teplov.ru вместо фото соседнего диаметра; у бака-ватерпаса, дефлекторов, Титана и шибера-задвижки заменены неверные фото.</p>
    </div>
  </section>

  <section id="onec">
    <h2>Очередь 1С</h2>

    <div class="item">
      <div class="item-head"><h3>Новые карточки из строк 1С</h3><a class="open" href="{{ $admin('integration-products') }}?tab=unmatched">Открыть очередь</a></div>
      <ul>
        <li>Безымянные нержавеющие детали без входной цены (переходы, хомут Ø240, тройники, крепление 100/200) — цены выставлены по рынку.</li>
        <li>Из sbg.by: каменка навесная Емеля, герметик Soudal, шнур 6 мм, огнеупорное одеяло, фольга 50 мкм.</li>
        <li>Плиты Балезино П2-3 Б и П1-2 Б — по цене 21vek.by.</li>
        <li>Привязаны: печи Царь-Печи (Горыня, Емеля, Емеля мини, Василиса, Потапыч) с ценами sbg.by, топка Omega 700, фольга Alukraft, Comfortprom «сталь 8 мм до 26 м³ со стеклом».</li>
      </ul>
      <p>Остались строки без цены ни в 1С, ни на рынке (каминные листы Bavaria, колосник, корзины для камней и др.) — для них нужна закупочная цена в 1С.</p>
    </div>
  </section>

  <section id="prices">
    <h2>Цены и поставщики</h2>

    <div class="item">
      <div class="item-head"><h3>Ошибка в прайсе bania</h3><a class="open" href="{{ $site('pechki/pec-otopitelnaia-vezuvii-aogt-01') }}">АОГТ 01</a></div>
      <p>Часть строк прайса bania была привязана к чужим карточкам: «Мини-1» давала цену печи АОГТ 01 (228 вместо 1197), «Комфорт 70» — «Комфорт 100», каминные дверки — другим дверкам. 21 привязка исправлена, цены восстановлены. Теперь синхронизация не пишет розницу, если модель в прайсе не совпадает с карточкой.</p>
      <p>Без поставщика остались Везувий В5, Pegas Термо 200 стекло, дверки П104 и П105 — на сайте «Цену уточняйте».</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Цены по sbg.by</h3></div>
      <p>Фольга Alukraft 12/18/30 м² — 30, 46 и 73 BYN; печи Царь-Печи — по sbg.by.</p>
    </div>
  </section>

  <section id="cards">
    <h2>Карточки</h2>

    <div class="item">
      <div class="item-head"><h3>Объединены дубли</h3></div>
      <p>Везувий Комфорт 100/200/300, набор Русский лес, дверка прочистная 237, адаптер ТиС Моно М-М D150 и трубы ТиС — одна карточка вместо двух. Старые адреса ведут на оставшуюся карточку.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Названия</h3></div>
      <p>У Везувия В5С, В8, В10, В11 латинская «B» заменена на русскую «В» — поиск и прайс bania теперь их узнают.</p>
    </div>
  </section>
@endsection
