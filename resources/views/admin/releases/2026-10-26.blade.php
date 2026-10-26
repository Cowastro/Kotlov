@extends('admin.releases.layout')

@section('title', 'Релиз 26.10.2026')

@php
    $admin = fn (string $path = ''): string => url('/admin'.($path !== '' ? '/'.$path : ''));
    $brand = fn (string $slug): string => url('/brands/'.$slug);
    $adminProducts = fn (string $search): string => url('/admin/products').'?search='.urlencode($search);
@endphp

@section('content')
  <header>
    <div class="eyebrow">Внутренний релиз для команды · 26 октября 2026</div>
    <h1>Что нового в админке и каталоге KOTLOV</h1>
    <p class="lead">Быстрый ручной заказ, счёт или КП из заказа, новые электрокотлы и ТЭНы от «Майтек Групп» с фото и описаниями, исправленные цены и изменения на сайте для покупателей.</p>
  </header>

  <div class="summary" aria-label="Коротко в цифрах">
    <div><b>1 форма</b><span>быстрый заказ вместо полной карточки</span></div>
    <div><b>+202</b><span>новые карточки от «Майтек Групп»</span></div>
    <div><b>140</b><span>из них уже на сайте с фото</span></div>
    <div><b>145</b><span>карточек получили описания</span></div>
  </div>

  <nav class="toc" aria-label="Содержание">
    <a href="#orders">Заказы и документы</a>
    <a href="#maitek">Майтек: новые бренды</a>
    <a href="#prices">Цены</a>
    <a href="#site">Сайт для покупателей</a>
  </nav>

  <section class="links" aria-labelledby="links-title">
    <h2 id="links-title">Быстрые ссылки</h2>
    <div class="link-groups">
      <div>
        <h3>Админка <small>нужен вход</small></h3>
        <ul class="link-list">
          <li><a href="{{ $admin('orders') }}">Заказы → «Быстрый заказ»</a></li>
          <li><a href="{{ $admin('commercial-documents') }}">Счета и КП</a></li>
          <li><a href="{{ url('/admin/products') }}?filters[awaiting_photos][isActive]=1">Товары, скрытые до фото</a></li>
          <li><a href="{{ $admin('crm-mail-threads') }}">Почта CRM</a></li>
        </ul>
      </div>
      <div>
        <h3>На сайте</h3>
        <ul class="link-list">
          <li><a href="{{ $brand('hotex') }}">HOTEX</a> · <a href="{{ $brand('skat') }}">SKAT</a> · <a href="{{ $brand('sangai') }}">Сангай</a> · <a href="{{ $brand('innowarm') }}">INNOWARM</a></li>
          <li><a href="{{ url('/podbor-dymohoda') }}">Калькулятор дымохода</a></li>
          <li><a href="{{ url('/dostavka') }}">Доставка и оплата (WebPay, ЕРИП)</a></li>
          <li><a href="{{ url('/') }}">Главная</a></li>
        </ul>
      </div>
    </div>
  </section>

  <section id="orders">
    <h2>Заказы и документы</h2>

    <div class="item">
      <div class="item-head"><h3>Быстрый заказ</h3><a class="open" href="{{ $admin('orders') }}">Открыть заказы</a></div>
      <p>Для заказов по телефону, в мессенджере или в магазине: кнопка «Быстрый заказ» вверху списка заказов.</p>
      <ol>
        <li>Телефон и имя клиента (email по желанию).</li>
        <li>Товары: поиск по названию, артикулу или бренду. Цена подставляется из карточки, её можно изменить. Сумма считается сразу.</li>
        <li>Доставка: самовывоз, курьер или транспортная. Для доставки появятся город, адрес и стоимость.</li>
        <li>Оплата: для оплаты по счёту или безналом появятся поля «Организация» и «УНП».</li>
        <li>Статус — по умолчанию «В работе». Нажмите «Создать заказ».</li>
      </ol>
      <p>Заказ получает номер, вы сразу становитесь ответственным, приходит обычное уведомление о новом заказе, и открывается карточка заказа. Письмо клиенту при ручном заказе не отправляется.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Счёт или КП клиенту из заказа</h3><a class="open" href="{{ $admin('orders') }}">Открыть заказы</a></div>
      <p>В карточке заказа есть кнопка «Счёт / КП клиенту». Клиент, товары, цены, скидка и доставка переносятся из заказа. Для розничного клиента включается быстрый режим без закупочных и CRM-проверок.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Удаление черновиков КП</h3><a class="open" href="{{ $admin('commercial-documents') }}">Открыть счета и КП</a></div>
      <p>На странице черновика есть кнопка «Удалить черновик». Удаление окончательное.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Почта в CRM</h3><a class="open" href="{{ $admin('crm-mail-threads') }}">Открыть почту</a></div>
      <p>Письма можно отфильтровать по сотруднику.</p>
    </div>
  </section>

  <section id="maitek">
    <h2>Новый ассортимент от «Майтек Групп»</h2>
    <p>Подключили бренды, которые есть только у Майтека. Цены, розница и остатки обновляются каждый день в 07:40 по их онлайн-прайсу.</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Бренд</th><th>Что это</th><th>Карточек</th><th>На сайте</th><th>Фото и тексты</th></tr></thead>
        <tbody>
          <tr><td><a href="{{ $brand('hotex') }}">HOTEX</a><br><a class="sub" href="{{ $adminProducts('HOTEX') }}">в админке</a></td><td>электрокотлы BE и BES (с баком и насосом), 4–27 кВт, Wi-Fi</td><td class="num">57</td><td class="num">54</td><td>фото и характеристики с Onliner, описания</td></tr>
          <tr><td><a href="{{ $brand('skat') }}">SKAT</a><br><a class="sub" href="{{ $adminProducts('Skat') }}">в админке</a></td><td>электрокотлы Skat и Skat plus, 4–20 кВт</td><td class="num">41</td><td class="num">38</td><td>фото HOTEX: это те же котлы по тем же ценам; описаний пока нет</td></tr>
          <tr><td><a href="{{ $brand('sangai') }}">Сангай (УМТ)</a><br><a class="sub" href="{{ $adminProducts('Сангай') }}">в админке</a></td><td>электрокотлы ЭВПМ, «Сангай-МК» с насосом Wilo, ТЭНы к ним</td><td class="num">21</td><td class="num">16</td><td>фото и характеристики с Onliner, описания</td></tr>
          <tr><td><a href="{{ $brand('innowarm') }}">INNOWARM</a><br><a class="sub" href="{{ $adminProducts('INNOWARM') }}">в админке</a></td><td>ТЭНы для полотенцесушителей, бойлеров, теплоаккумуляторов</td><td class="num">67</td><td class="num">32</td><td>фото с сайта производителя, описания</td></tr>
          <tr><td><a href="{{ $brand('sten') }}">СТЭН</a><br><a class="sub" href="{{ url('/admin/products') }}?filters[awaiting_photos][isActive]=1">в админке</a></td><td>ЭВПМ и «Стандарт» 380 В, ТЭНБ G2, пульт ПУЭ-15</td><td class="num">16</td><td class="num">0</td><td>ждут фото</td></tr>
          <tr><td><a href="{{ $brand('ferroli') }}">Ferroli</a><br><a class="sub" href="{{ $adminProducts('Ferroli') }}">в админке</a></td><td>VITABEL, DIVA D, DIVATECH D, DIVABEL — карточки, у которых не было поставщика</td><td class="num">+10 привязок</td><td class="num">—</td><td>карточки уже были</td></tr>
        </tbody>
      </table>
    </div>
    <div class="note">
      <strong>Карточки без фото скрыты.</strong>
      <span>Они появятся на сайте сами, как только найдётся фото. В админке их видно в списке товаров с фильтром <a href="{{ url('/admin/products') }}?filters[awaiting_photos][isActive]=1">«Скрыты до фото»</a>.</span>
    </div>

    <div class="item">
      <h3>Где Майтек — посредник</h3>
      <p>Если ту же карточку дешевле поставляет другой поставщик (Термокрафт по СТЭН, Каракан и Greolit, Термостудия по Ferroli), Майтек её больше не обновляет. 62 такие привязки сняты, наличие пересчитано по основному поставщику. Где Майтек дешевле, его закупка и остаток учитываются, а розницу ставит основной поставщик.</p>
    </div>
  </section>

  <section id="prices">
    <h2>Цены</h2>
    <div class="item">
      <div class="item-head"><h3>Greolit PROFI</h3><a class="open" href="{{ $brand('greolit') }}">Открыть бренд</a></div>
      <p>Котлы 99 и 250 кВт «с автоматикой» и «без автоматики» получали цены друг друга. Теперь у каждой карточки своя цена:</p>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Котёл Greolit PROFI</th><th>Розница, BYN</th></tr></thead>
          <tbody>
            <tr><td>99 кВт без автоматики</td><td class="num">13 400</td></tr>
            <tr><td>99 кВт с автоматикой</td><td class="num">14 704</td></tr>
            <tr><td>250 кВт без автоматики</td><td class="num">26 860</td></tr>
            <tr><td>250 кВт с автоматикой</td><td class="num">28 164</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <ul>
      <li><b>Термостудия:</b> позиции, которые пропали из её прайса, больше не держат старую цену. Исправлена цена TESY EV 9S 200.</li>
      <li><b>Deal.by (sbg.by):</b> добавлена синхронизация цен по брендам и проверка цен по поставщикам.</li>
    </ul>
  </section>

  <section id="site">
    <h2>Сайт для покупателей</h2>
    <div class="item">
      <div class="item-head"><h3>Калькулятор дымохода</h3><a class="open" href="{{ url('/podbor-dymohoda') }}">Открыть калькулятор</a></div>
      <p>Теперь стоит вверху каждого раздела «Дымоходы». Обе схемы дымохода можно скачать.</p>
    </div>
    <div class="item">
      <div class="item-head"><h3>Оплата: WebPay и ЕРИП</h3><a class="open" href="{{ url('/dostavka') }}">Открыть «Доставка и оплата»</a></div>
      <p>На главной и на странице «Доставка и оплата» есть информация об оплате картой через WebPay с официальными логотипами платёжных систем и инструкция по оплате через ЕРИП.</p>
    </div>
    <ul>
      <li><b>iPhone</b> больше не увеличивает страницу при нажатии на поле формы: на сайте, в портале заказов, портале КП и в админке.</li>
      <li><b>Продавец на витрине</b> — СанБизнесГруп: обновлены реквизиты в подвале и в политике конфиденциальности.</li>
      <li>Кнопка «Правила оформления заказа» на главной выровнена по центру.</li>
    </ul>
  </section>

  <footer>Внутренняя страница для сотрудников KOTLOV. Ссылки в админку открываются после входа. Вопросы и замечания по релизу — в общий чат команды.</footer>
@endsection
