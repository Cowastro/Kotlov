<?php

return [
    'chimney_bundle_discount' => [
        'enabled' => true,
        'percent' => 10,
        'self_selection_percent' => 5,
        'preferred_brand_slug' => 'teplov-i-suhov',
        'chimney_category_slugs' => [
            'dymohody', 'dymoxody-dlya-pechej', 'dymoxody-dlya-bani',
            'dymoxody-dlya-kaminov', 'prochie-dymohod', 'dymohody-nerzhaveyushchie',
            'koaxial-dymoxod', 'shibery-dymohod', 'kondensatootvody',
            'krepleniya-dymohod', 'zonty-deflektory', 'teplosyomniki',
            'perehody-adaptery-dymohod', 'zaglushki-dymohod', 'truby-mono',
            'troyniki-mono', 'kolena-mono', 'truby-sendvich',
            'troyniki-sendvich', 'kolena-sendvich',
        ],
        'equipment_category_slugs' => [
            'kotly', 'kotly-otopleniya', 'gazovye', 'tverdotoplivnye',
            'kaminy', 'topki', 'pechki', 'pechi', 'pechi-dlya-bani',
            'pechi-kaminy', 'burzhuiki-pechi', 'dlya-dachi',
            'peci-drovianye-otopitelnye', 'drovyanye-pechi-dlya-bani',
            'drovianye-peci-bannye',
        ],
        'excluded_equipment_category_slugs' => [
            'elektricheskie', 'elektrokamenki', 'elektrokamini',
            'bio-kaminy', 'biokaminy', 'teplovyie-nasosyi', 'teplovye-nasosy',
        ],
    ],

    // Публичная выгода за самостоятельное оформление через корзину.
    'online_order_discount' => [
        'code' => 'ONLINE3',
        'percent' => 3,
        'excluded_category_slugs' => [
            'teplovyie-nasosyi',
        ],
        'excluded_payment_methods' => [
            'installment_6',
        ],
    ],

    // Операционные пороги для очереди менеджера.
    'order_management' => [
        'minimum_margin_percent' => 10,
    ],

    // Фиксированные B2B-скидки от актуальной розничной цены товара.
    // Предложение поставщика по-прежнему определяет доступность и остаток,
    // но цена для партнёра рассчитывается от витринной розницы.
    'b2b_brand_retail_discounts' => [
        'ecokamin' => 15,
        'экокамин' => 15,
    ],

    // Автоматическая передача заявок поставщику всегда выключена по умолчанию.
    // Администратор может включить её отдельно только после успешной ручной выборки.
    'supplier_auto_transfer' => [
        'minimum_successful_requests' => 3,
        'minimum_control_days' => 7,
        'sample_size' => 5,
        'batch_size' => 100,
    ],

    // Пороги read-only рыночных сигналов. Они никогда не меняют цену автоматически.
    'market_intelligence' => [
        'suspicious_low_percent' => 5,
    ],

    // ─────────────────────────────────────────────────────────────────
    // Способы доставки
    // price: стоимость в BYN (0 = бесплатно, null = рассчитывается отдельно)
    // free_from: порог бесплатной доставки (null = никогда не бесплатна)
    // ─────────────────────────────────────────────────────────────────
    'delivery_methods' => [
        'pickup' => [
            'name' => 'Самовывоз',
            'price' => 0,
            'free_from' => null,
            'address' => 'г. Минск, ул. Селицкого, д. 23/10',
            'work_time' => 'по предварительному согласованию с консультантом',
        ],
        'courier' => [
            'name' => 'Доставка курьером по г. Минску',
            'price' => 10,
            'free_from' => 400,
        ],
        'transport' => [
            'name' => 'Транспортная компания по Беларуси',
            'price' => 60,
            'free_from' => 1000,
        ],
        'kit' => [
            'name' => 'ТК КИТ — Россия, Казахстан, Армения, Киргизия',
            'price' => null,
            'free_from' => null,
            'external_calculator' => 'https://minsk.tk-kit.ru/clients/rates',
        ],
    ],

    // ─────────────────────────────────────────────────────────────────
    // Общий порог бесплатной доставки (используется в прогресс-баре)
    // ─────────────────────────────────────────────────────────────────
    'free_delivery_threshold' => 400,

    // ─────────────────────────────────────────────────────────────────
    // Особые правила: категории, для которых бесплатная доставка НЕ действует
    // ─────────────────────────────────────────────────────────────────
    'no_free_delivery_slugs' => [
        'kamni-dlya-bani',
        'kamni',
    ],

    // ─────────────────────────────────────────────────────────────────
    // Способы оплаты
    // name — короткое название (используется в админке и странице заказа)
    // desc — подробности (показываются в форме checkout под названием)
    // ─────────────────────────────────────────────────────────────────
    'payment_methods' => [
        'cash' => [
            'name' => 'Наличными',
            'desc' => 'При получении товара',
        ],
        'card' => [
            'name' => 'Банковской платежной карточкой',
            'desc' => 'MasterCard, Visa, Visa Electron, Maestro, Белкарт',
        ],
        'bank_transfer' => [
            'name' => 'Безналичный расчёт',
            'desc' => '100% предоплата на расчётный счёт интернет-магазина',
        ],
        'currency_transfer' => [
            'name' => 'Оплата для РФ и Казахстана',
            'desc' => 'Оплата в российских рублях и/или евро на валютный счёт',
        ],
        'installment_6' => [
            'name' => 'Рассрочка на 6 месяцев',
            'desc' => 'Оставьте заявку — менеджер подтвердит доступность рассрочки и поможет с оформлением',
            'requires_manager_follow_up' => true,
            'video_url' => 'https://www.youtube.com/watch?v=nw2v3z5BZms',
        ],
        'credit_3_years' => [
            'name' => 'Кредит до 3 лет',
            'desc' => 'Подробности уточняйте у менеджеров',
        ],
        'online_card' => [
            'name' => 'Онлайн-оплата картой',
        ],
        'halva' => [
            'name' => 'Карта «Халва»',
            'desc' => 'Рассрочка до 2 месяцев без первого взноса',
            'terminal' => '87015502',
        ],
        'belgazprombank' => [
            'name' => 'Белгазпромбанк',
            'desc' => 'Рассрочка на 3 месяца',
        ],
        'belarusbank_magnit' => [
            'name' => 'Беларусбанк «Магнит Green»',
            'desc' => 'Рассрочка на 2 месяца',
        ],
        'cherepaha' => [
            'name' => 'Карта «Черепаха» от ВТБ',
            'desc' => 'Рассрочка до 8 месяцев',
        ],
        'webpay' => [
            'name' => 'WEBPAY',
            'desc' => 'VISA, MasterCard, БЕЛКАРТ',
        ],
        'invoice' => [
            'name' => 'По счёту (для организаций)',
            'desc' => 'Реквизиты по УНП; счёт после проверки наличия менеджером',
        ],
    ],

    // Номер терминала для карт рассрочки
    'terminal_number' => '87015502',

    // Способы оплаты доступные в форме checkout (подмножество payment_methods)
    'checkout_payment_methods' => [
        'cash',
        'card',
        'bank_transfer',
        'installment_6',
        'invoice',
    ],

    // Типы доставки доступные в форме checkout
    'checkout_delivery_methods' => [
        'pickup',
        'courier',
        'transport',
        'kit',
    ],
];
