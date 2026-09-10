<style>
    .hp-guide { background: #f7f7f5; }
    .hp-guide__panel { background: #fff; border: 1px solid #e9e9e5; border-radius: 20px; padding: 28px; height: 100%; }
    .hp-guide__eyebrow { color: #e4572e; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .hp-guide__link { display: block; color: inherit; }
    .hp-guide__link:hover h4, .hp-guide__link:hover h5 { color: #e4572e; }
    .hp-guide__tag { display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #f1f1ed; font-size: 13px; }
    .hp-guide__compare { border-left: 4px solid #e4572e; }
    .hp-guide__article-image { aspect-ratio: 16 / 10; border-radius: 16px; overflow: hidden; margin-bottom: 18px; }
    .hp-guide__article-image img { width: 100%; height: 100%; object-fit: cover; }
    .hp-guide__cta { border-radius: 24px; padding: 36px; background: #20231f; color: #fff; }
    .hp-guide__cta .cl-text-2 { color: rgba(255,255,255,.72) !important; }
    @media (max-width: 767px) {
        .hp-guide__panel { padding: 22px; }
        .hp-guide__cta { padding: 26px 22px; }
    }
</style>

<section class="hp-guide flat-spacing" aria-labelledby="heat-pump-guide-title">
    <div class="container">
        <div class="row align-items-center g-24 mb-40">
            <div class="col-lg-7">
                <p class="hp-guide__eyebrow mb-8">Подбор по проекту, а не только по площади</p>
                <h2 id="heat-pump-guide-title" class="mb-16">Тепловой насос для дома: от расчёта до запуска</h2>
                <p class="text-body-1 cl-text-2 mb-12">
                    Воздушный тепловой насос переносит тепло из наружного воздуха в водяную систему отопления.
                    Он может работать на отопление, охлаждение и приготовление горячей воды, но результат зависит
                    от теплопотерь здания, температуры подачи и правильно собранной гидравлической схемы.
                </p>
                <p class="text-body-1 cl-text-2">
                    Поэтому мы начинаем подбор с параметров дома: утепления, окон, вентиляции, тёплого пола или
                    радиаторов, электрической мощности и требуемого резерва. После расчёта выбираем модель,
                    бойлер ГВС, буферную ёмкость, насосные группы и автоматику.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="hp-guide__panel hp-guide__compare">
                    <h4 class="mb-12">Почему тёплый пол — сильная связка</h4>
                    <p class="cl-text-2 mb-16">
                        Тёплому полу нужна сравнительно низкая температура воды. Чем ниже температура подачи,
                        тем меньше перепад температур должен создавать насос и тем выше потенциальная эффективность.
                    </p>
                    <a href="/blog/teplovoy-nasos-kotlov-ge-24-kvt-r32-nareyki"
                       class="fw-semibold text-decoration-underline link">
                        Посмотреть объект с тёплым полом и радиаторами
                    </a>
                </div>
            </div>
        </div>

        <div class="sect-heading text-center mb-32">
            <h3 class="s-title">R32 или R290: что подходит вашему объекту</h3>
            <p class="s-desc text-body-1 cl-text-2">Обе технологии требуют расчёта, но решают немного разные задачи.</p>
        </div>
        <div class="row g-20 mb-48">
            <div class="col-md-6">
                <a href="/teplovyie-nasosyi/kotlov-ge-flm30-r32-10-kvt" class="hp-guide__panel hp-guide__link">
                    <span class="hp-guide__tag mb-16">R32 · подача до 60 °C</span>
                    <h4 class="mb-12">Для современных низкотемпературных систем</h4>
                    <p class="cl-text-2 mb-0">
                        Рациональный вариант для нового энергоэффективного дома, тёплого пола, фанкойлов и
                        низкотемпературных радиаторов. В линейке KOTLOV GE представлены модели разной мощности.
                    </p>
                </a>
            </div>
            <div class="col-md-6">
                <a href="/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye" class="hp-guide__panel hp-guide__link">
                    <span class="hp-guide__tag mb-16">R290 · отопление до 75 °C</span>
                    <h4 class="mb-12">Для радиаторов и высокой температуры воды</h4>
                    <p class="cl-text-2 mb-0">
                        Высокотемпературную серию рассматривают при модернизации существующей котельной,
                        работе с радиаторами и повышенных требованиях к горячему водоснабжению.
                    </p>
                </a>
            </div>
        </div>

        <div class="sect-heading text-center mb-32" id="heat-pump-cases">
            <h3 class="s-title">Полезные материалы и реальные объекты</h3>
            <p class="s-desc text-body-1 cl-text-2">Расчёты, технические объяснения и опыт монтажной команды KOTLOV.</p>
        </div>
        <div class="tf-grid-layout sm-col-2 xl-col-4 mb-48">
            @foreach ($articles as $article)
                <article class="hp-guide__panel">
                    <a href="/blog/{{ $article->slug }}" class="hp-guide__article-image d-block">
                        <img loading="lazy" width="640" height="400"
                            src="{{ $article->cover_image_url }}"
                            alt="{{ $article->title }}"
                            onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                    </a>
                    <p class="text-caption-01 cl-text-3 mb-8">{{ $article->published_at->translatedFormat('d F Y') }}</p>
                    <h5 class="mb-12">
                        <a href="/blog/{{ $article->slug }}" class="link">{{ $article->title }}</a>
                    </h5>
                    <p class="text-body-2 cl-text-2 mb-0">{{ Str::limit(strip_tags($article->excerpt ?? ''), 125) }}</p>
                </article>
            @endforeach
        </div>

        <div class="row g-24 align-items-start mb-48">
            <div class="col-lg-5">
                <p class="hp-guide__eyebrow mb-8">Коротко о главном</p>
                <h3 class="mb-16">Частые вопросы перед покупкой</h3>
                <p class="text-body-1 cl-text-2">
                    Каталожного значения мощности недостаточно. До заказа важно согласовать расчётную точку,
                    температуру подачи, ГВС, электропитание, резерв и место установки наружного блока.
                </p>
                <a href="/blog/kak-vybrat-teplovoy-nasos" class="tf-btn btn-white btn-stroke mt-16">
                    Подробный гид по выбору
                </a>
            </div>
            <div class="col-lg-7">
                <div id="accordion-heat-pump-faq">
                    @foreach ([
                        ['Как подобрать мощность теплового насоса?', 'По расчётным теплопотерям здания, а не только по площади. Учитываются утепление, окна, вентиляция, зимняя температура, отопительные приборы, ГВС и доступная электрическая мощность.'],
                        ['Подходит ли тепловой насос для тёплого пола?', 'Да. Низкая температура подачи делает водяной тёплый пол одним из наиболее подходящих потребителей для теплового насоса.'],
                        ['Можно ли подключить его к радиаторам?', 'Можно, но нужно проверить теплоотдачу радиаторов при расчётной температуре воды. Для высокой подачи рассматривают увеличенные радиаторы или высокотемпературные модели R290.'],
                        ['Чем отличаются R32 и R290?', 'R32 хорошо подходит для современных низкотемпературных систем. Серия KOTLOV GE на R290 способна работать с более высокой температурой воды и интересна для радиаторов и ГВС.'],
                        ['Нужен ли резервный котёл?', 'Это зависит от теплопотерь, климата и требований к надёжности. Резерв помогает закрыть пиковую нагрузку и сохранить отопление во время обслуживания.'],
                        ['От чего зависит расход электроэнергии?', 'От температуры воздуха и подачи, теплопотерь дома, режима ГВС, настроек автоматики и качества монтажа. Чем ниже требуемая температура воды, тем выше потенциальная эффективность.'],
                    ] as $index => [$question, $answer])
                        <div class="accordion-item_v2">
                            <div class="accordion-action {{ $index ? 'collapsed' : '' }} lh-24 fw-medium"
                                data-bs-target="#hp-faq-{{ $index }}" data-bs-toggle="collapse"
                                aria-expanded="{{ $index ? 'false' : 'true' }}" aria-controls="hp-faq-{{ $index }}" role="button">
                                <span>{{ $question }}</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="hp-faq-{{ $index }}" class="collapse {{ $index ? '' : 'show' }}"
                                data-bs-parent="#accordion-heat-pump-faq">
                                <p class="faq-content cl-text-2">{{ $answer }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="hp-guide__cta d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-24">
            <div>
                <h3 class="text-white mb-8">Получите подбор под ваш дом</h3>
                <p class="cl-text-2 mb-0">Проверим исходные данные, предложим мощность и объясним состав котельной.</p>
            </div>
            <a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn btn-white flex-shrink-0"
               data-analytics-event="heat_pump_lead_click">Заказать расчёт</a>
        </div>
    </div>
</section>
