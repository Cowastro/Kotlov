@extends('admin.releases.layout')

@section('title', 'Релиз 06–09.11.2026')

@php
    $admin = fn (string $path = ''): string => url('/admin'.($path !== '' ? '/'.$path : ''));
    $site = fn (string $path): string => url('/'.ltrim($path, '/'));
@endphp

@section('content')
  <header>
    <div class="eyebrow">Внутренний релиз для команды · 6–9 ноября 2026</div>
    <h1>Что нового в каталоге KOTLOV</h1>
    <p class="lead">Подключён новый поставщик «Престиж Тепло» (Vaillant, Protherm, Kospel, Drazice, Reflex, Galmet) — почти 400 новых карточек. Прайс S-TANK проверен и разложен по карточкам, дубли объединены, у баков появились фото серий. В описание любого товара теперь можно вставить видео, магазин может временно работать «только онлайн».</p>
  </header>

  <div class="summary" aria-label="Коротко в цифрах">
    <div><b>395</b><span>новых карточек «Престиж Тепло»</span></div>
    <div><b>130</b><span>новых карточек S-TANK (баки, ТЭНы, аноды)</span></div>
    <div><b>73</b><span>карточки S-TANK получили фото серии</span></div>
    <div><b>~375</b><span>описаний дописано (S-TANK и Чистый берег)</span></div>
  </div>

  <nav class="toc" aria-label="Содержание">
    <a href="#suppliers">Поставщики</a>
    <a href="#cards">Карточки и статьи</a>
    <a href="#stores">Магазины</a>
  </nav>

  <section class="links" aria-labelledby="links-title">
    <h2 id="links-title">Быстрые ссылки</h2>
    <div class="link-groups">
      <div>
        <h3>Админка <small>нужен вход</small></h3>
        <ul class="link-list">
          <li><a href="{{ $admin('suppliers') }}">Поставщики</a></li>
          <li><a href="{{ $admin('partner-branches') }}">Филиалы партнёров</a></li>
          <li><a href="{{ $admin('products') }}">Товары</a></li>
        </ul>
      </div>
      <div>
        <h3>На сайте</h3>
        <ul class="link-list">
          <li><a href="{{ $site('blog/ekokamin-shaman-na-sklade-gotov-k-zakazu') }}">Статья: ЭкоКамин ШАМАН на складе</a></li>
          <li><a href="{{ $site('blog/montazh-dymohodov-iz-nerzhaveyki-lida') }}">Статья: дымоходы в Лиде</a></li>
          <li><a href="{{ $site('pechi-kaminy/ecokamin-shaman-chugunnyy-kamin') }}">ЭкоКамин ШАМАН с видео</a></li>
        </ul>
      </div>
    </div>
  </section>

  <section id="suppliers">
    <h2>Поставщики</h2>

    <div class="item">
      <div class="item-head"><h3>Новый поставщик «Престиж Тепло»</h3><a class="open" href="{{ $admin('suppliers') }}">Поставщики</a></div>
      <ul>
        <li>Дистрибьютор Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes, Comisa и Conex Banninger. Прайс приходит рассылкой (xlsm по ссылке Dropbox) — загружается кнопкой «Загрузить прайс» на странице поставщика.</li>
        <li>Цены в прайсе в евро — пересчитываются в рубли по курсу, указанному в самом прайсе. Наличие — «под заказ».</li>
        <li>141 строка привязана к нашим карточкам, для оборудования, которого у нас не было, создано 395 карточек.</li>
        <li>Карточки без фото скрыты и ждут фото: поиск берёт 60 карточек в день, уже опубликовано около 70.</li>
        <li>Фитинги Comisa и Conex карточками не заводились — они доступны в КП через «Найти у поставщиков».</li>
      </ul>
    </div>

    <div class="item">
      <div class="item-head"><h3>S-TANK: прайс октября, дубли и новые карточки</h3></div>
      <ul>
        <li>Цены всех 167 моделей из прайса привязаны к карточкам; новые цены прайса подхватываются автоматически.</li>
        <li>9 пар дублей (AT, Prestige) объединены, старые адреса перенаправляются.</li>
        <li>Созданы карточки недостающих моделей — 105 баков, а также 25 ТЭНов, анодов, термостатов и заглушек.</li>
        <li>Скрытые баки AT/SS ETRO, CT, SS CT, HFWT DUO, SS, ТЭНы ITEX и магниевые аноды получили фото своей серии с сайта производителя — 73 карточки опубликованы. Без фото пока 22 позиции (FLAT, титановые аноды, термостаты, заглушки) — нужны фото от поставщика.</li>
      </ul>
    </div>

    <div class="item">
      <div class="item-head"><h3>Чистый берег</h3></div>
      <ul>
        <li>Из нового прайса заведены насосы Wilo WJ-202 (в поверхностные) и IPL 40/160 (в промышленные); такие модели теперь сами попадают в правильный раздел.</li>
        <li>Фото карточек при обновлении прайса не сбрасываются.</li>
      </ul>
    </div>

    <div class="item">
      <div class="item-head"><h3>ЭкоКамин: Минск и Москва не путаются</h3></div>
      <p>Наличие с сайта ecokamin.ru в карточке для менеджера подписано «Сайт производителя ecokamin.ru», а не как склад в Минске. Склад в Москве — по файлу остатков.</p>
    </div>
  </section>

  <section id="cards">
    <h2>Карточки и статьи</h2>

    <div class="item">
      <div class="item-head"><h3>Видео в описании товара</h3><a class="open" href="{{ $site('pechi-kaminy/ecokamin-shaman-chugunnyy-kamin') }}">Пример</a></div>
      <p>В карточке товара в админке есть поле «Видео в описании»: вставьте ссылку на YouTube или скопированный код iframe — ролик появится в описании на сайте. Первым видео получил ЭкоКамин ШАМАН.</p>
    </div>

    <div class="item">
      <div class="item-head"><h3>Новые статьи</h3></div>
      <ul>
        <li><a href="{{ $site('blog/ekokamin-shaman-na-sklade-gotov-k-zakazu') }}">«ЭкоКамин ШАМАН на складе»</a> — переписана под поиск: ключевые слова, таблица характеристик, монтаж, доставка, вопросы и ответы.</li>
        <li><a href="{{ $site('blog/montazh-dymohodov-iz-nerzhaveyki-lida') }}">Монтаж дымоходов из нержавейки в Лиде</a> — с фото работ и ссылкой на калькулятор дымохода.</li>
        <li>Под каждой статьёй — компактная строка «Поделиться» в соцсетях.</li>
      </ul>
    </div>

    <div class="item">
      <div class="item-head"><h3>Описания для новых карточек</h3></div>
      <p>После пополнения баланса AI дописаны описания, которые оставались пустыми: 130 карточек S-TANK и около 245 карточек Чистого берега.</p>
    </div>
  </section>

  <section id="stores">
    <h2>Магазины</h2>

    <div class="item">
      <div class="item-head"><h3>Режим «Временно только онлайн»</h3><a class="open" href="{{ $admin('partner-branches') }}">Филиалы</a></div>
      <ul>
        <li>В карточке филиала можно включить «Временно только онлайн» — до нужной даты и времени или по выбранным дням недели.</li>
        <li>В это время телефоны и звонки точки на сайте скрыты, вместо них — «Менеджер подтвердит заказ в рабочее время». Заказы на сайте принимаются как обычно.</li>
        <li>В списке филиалов видно, в каком режиме сейчас точка.</li>
      </ul>
    </div>

    <div class="item">
      <div class="item-head"><h3>Смена города из новостей</h3></div>
      <p>Переключение города со страницы городской новости снова работает: сайт запоминает выбранный город.</p>
    </div>
  </section>
@endsection
