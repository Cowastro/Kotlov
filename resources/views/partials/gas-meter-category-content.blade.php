<section class="gas-meter-guide" aria-labelledby="gas-meter-guide-title">
    <div class="container">
        <div class="gas-meter-guide__lead">
            <div>
                <p class="text-caption-01 cl-text-3 mb-8">Подбор газового счетчика</p>
                <h2 id="gas-meter-guide-title">Как купить подходящий счетчик газа</h2>
                <p>
                    В каталоге собраны бытовые диафрагменные и электронные газовые счетчики для квартиры,
                    частного дома и небольших коммерческих объектов. Чтобы прибор подошел без переделки
                    газопровода, до заказа сравните не только типоразмер, но и направление потока,
                    монтажную длину и требования проекта.
                </p>
            </div>
            <ul class="gas-meter-guide__checklist">
                <li><strong>Расход газа</strong>Суммируйте максимальный расход всех подключаемых приборов.</li>
                <li><strong>Левый или правый</strong>Проверьте вход газа и стрелку направления потока.</li>
                <li><strong>Монтажная длина</strong>Сверьте межосевое расстояние: 110, 200 или 250 мм.</li>
                <li><strong>Документы</strong>Уточните паспорт, поверку и допустимость модели для вашего объекта.</li>
            </ul>
        </div>

        <div class="gas-meter-guide__types">
            <article class="gas-meter-guide__type">
                <h3>Для квартиры</h3>
                <p>Компактные модели G1,6, G2,5 и G4 для учета расхода газа плитой или несколькими бытовыми приборами. Точный класс выбирают по проектному расходу.</p>
                <a href="#catalog-products">Смотреть бытовые модели</a>
            </article>
            <article class="gas-meter-guide__type">
                <h3>Для дома с котлом</h3>
                <p>Исполнения G4 и G6 для объектов с газовым котлом и плитой. Важны суммарный расход, направление подключения и условия установки.</p>
                <a href="#catalog-products">Сравнить G4 и G6</a>
            </article>
            <article class="gas-meter-guide__type">
                <h3>Электронные счетчики</h3>
                <p>Ультразвуковые модели СКАТ для учета больших расходов газа. Опции связи и точное исполнение проверяйте по паспорту выбранной модификации.</p>
                <a href="/brands/belomo">Счетчики БелОМО</a>
            </article>
        </div>

        <div class="gas-meter-guide__note">
            Газоиспользующее оборудование нельзя подбирать только по цене или внешним размерам.
            Перед покупкой согласуйте модификацию с проектировщиком либо обслуживающей газоснабжающей организацией.
            Монтаж, пломбирование и ввод в эксплуатацию выполняются в установленном порядке.
        </div>

        @if ($faq->isNotEmpty())
            <div class="gas-meter-guide__faq" aria-labelledby="gas-meter-faq-title">
                <h2 id="gas-meter-faq-title">Вопросы о выборе газового счетчика</h2>
                <div class="gas-meter-guide__faq-grid">
                    @foreach ($faq as $item)
                        <details>
                            <summary>{{ $item['question'] }}</summary>
                            <p>{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
