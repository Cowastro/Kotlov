<?php

return [
    // Model-specific figures from published product sheets. Catalog and detail pages use the same values.
    'models' => [
        'ocag-elektriceskii-electrolux-sphere-plus-efpp-3320rls-n' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/ochag-elektricheskiy-electrolux-sphere-plus-efp-p-3320rls-n/',
            'specs' => ['Диагональ экрана' => '33″', 'Мощность обогрева' => '2 кВт', 'Площадь обогрева' => 'до 25 м²'],
        ],
        'ocag-elektriceskii-electrolux-sphere-plus-efpp-3320rls' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/kaminokomplekt-ochag-electrolux-efp-p-3320rls-portal-loft-30-kamen-belyy-belaya-emal/',
            'specs' => ['Мощность обогрева' => '1–2 кВт', 'Площадь обогрева' => 'до 25 м²'],
        ],
        'vstraivaemyi-ocag-electrolux-classic-efpp-1020ls' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/ochag-elektricheskiy-electrolux-classic-efp-p-1020ls/',
            'specs' => ['Мощность обогрева' => '1–2 кВт', 'Площадь обогрева' => 'до 25 м²'],
        ],
        'elektriceskii-kamin-electrolux-efpp-3620ls' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/kaminokomplekt-ochag-electrolux-efp-p-3620ls-portal-moderno-36-belyy-chernyy-r-v/',
            'specs' => ['Площадь обогрева' => 'до 25 м²'],
        ],
        'elektriceskii-kamin-electrolux-efpp-4220ls' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/ochag-elektricheskiy-electrolux-efp-p-4220ls/',
            'specs' => ['Площадь обогрева' => 'до 25 м²'],
        ],
        'kaminokomplekt-electrolux-efpp-3020ls-n-s-portalom-california-30' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/kaminokomplekt-ochag-electrolux-efp-p-3020ls-n-portal-bianco-30-belyy-r-v/',
            'specs' => ['Площадь обогрева' => 'до 25 м²'],
        ],
        'ocag-parovoi-elektriceskii-royal-thermo-rtfpp1000m-3d-cassette-mystery' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/ochag-elektricheskiy-royal-thermo-rtfp-p1000m-3d-cassette-mystery/',
            'specs' => ['Обогрев' => 'Нет', 'Эффект пламени' => 'Водяной пар'],
        ],
        'interflame-panoramic-25-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-dioramic-25-led-fx/',
            'specs' => ['Мощность обогрева' => '2 кВт'],
        ],
        'firelight-bfpt-2525-cup' => [
            'source' => 'https://b2b.rusklimat.com/catalog/product/biokamin-firelight-bfp-t-2525-cup/',
            'specs' => ['Тип камина' => 'Биокамин'],
        ],
    ],

    // Installation opening dimensions, kept in width × height × depth order.
    'openings' => [
        'ocag-elektriceskii-electrolux-sphere-plus-efpp-3320rls-n' => [
            'source' => 'https://rkcdn.ru/products/de5d2311-b059-11ee-b8d6-00505601218a/src.pdf#page=8',
            'mm' => ['width' => 740, 'height' => 525, 'depth' => 209],
        ],
        'ocag-elektriceskii-electrolux-sphere-plus-efpp-3320rls' => [
            'source' => 'https://rkcdn.ru/products/de5d2311-b059-11ee-b8d6-00505601218a/src.pdf#page=8',
            'mm' => ['width' => 740, 'height' => 525, 'depth' => 209],
        ],
        'vstraivaemyi-ocag-electrolux-efpp-3020ls-diagonal-31-diuim' => [
            'source' => 'https://rkcdn.ru/products/de5d2311-b059-11ee-b8d6-00505601218a/src.pdf#page=8',
            'mm' => ['width' => 740, 'height' => 525, 'depth' => 207],
        ],
        'vstraivaemyi-ocag-electrolux-efpp-2520ls-diagonal-25-diuimov' => [
            'source' => 'https://rkcdn.ru/products/de5d2311-b059-11ee-b8d6-00505601218a/src.pdf#page=8',
            'mm' => ['width' => 582, 'height' => 425, 'depth' => 185],
        ],
        'interflame-panoramic-25-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-dioramic-25-led-fx/',
            'mm' => ['width' => 635, 'height' => 470, 'depth' => 332],
        ],
        'elektrokamin-royal-flame-dioramic-28-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-dioramic-28-led-fx/',
            'mm' => ['width' => 746, 'height' => 597, 'depth' => 288],
        ],
        'elektrokamin-royal-flame-dioramic-33-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-dioramic-33-led-fx/',
            'mm' => ['width' => 866, 'height' => 663, 'depth' => 350],
        ],
        'interflame-panoramic-33w-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-dioramic-33w-led-fx/',
            'mm' => ['width' => 866, 'height' => 500, 'depth' => 350],
        ],
        'elektrokamin-royal-flame-vision-26-ef-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-vision-26-ef-led-fx/',
            'mm' => ['width' => 686, 'height' => 447, 'depth' => 240],
        ],
        'elektrokamin-royal-flame-vision-26-ef-led-3d-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-vision-26-ef-led-3d-fx/',
            'mm' => ['width' => 686, 'height' => 447, 'depth' => 240],
        ],
        'elektrokamin-royal-flame-vision-28-ef-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-vision-28-ef-led-fx/',
            'mm' => ['width' => 740, 'height' => 583, 'depth' => 210],
        ],
        'elektrokamin-royal-flame-vision-23-ef-led-fx-12172' => [
            'source' => 'https://royalflame.ru/catalog/ochag-vision-23-ef-led-fx/',
            'mm' => ['width' => 577, 'height' => 497, 'depth' => 190],
        ],
        'elektrokamin-royal-flame-vision-30-ef-led-fx' => [
            'source' => 'https://royalflame.ru/catalog/ochag-vision-30-ef-led-fx/',
            'mm' => ['width' => 787, 'height' => 610, 'depth' => 242],
        ],
        'elektrokamin-dimplex-xhd26l-int' => [
            'source' => 'https://dimplex.ru/catalog/ochag-xhd26l-int/',
            'mm' => ['width' => 660, 'height' => 479, 'depth' => 180],
        ],
        'elektrokamin-dimplex-xhd28l-int' => [
            'source' => 'https://dimplex.ru/catalog/ochag-xhd28l-int/',
            'mm' => ['width' => 695, 'height' => 596, 'depth' => 180],
        ],
        'elektrokamin-dimplex-symphony-26-df2608-int' => [
            'source' => 'https://dimplex.ru/catalog/ochag-symphony-26-df2608/',
            'mm' => ['width' => 695, 'height' => 596, 'depth' => 222],
        ],
        'elektrokamin-dimplex-symphony-30-df3020-int' => [
            'source' => 'https://dimplex.ru/catalog/ochag-symphony-30-df3020-int/',
            'mm' => ['width' => 775, 'height' => 654, 'depth' => 298],
        ],
    ],

    // Exact models named in the portal's published compatibility specification.
    'compatibility' => [
        'portal-smolcom-deli-v42' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-deli-v42#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-optima-v42' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-optima-v42#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-govard-v42' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-govard-v42#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-line-v60' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-line-v60#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-valerie-v60' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-valerie-v60#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-albion-v60' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-albion-v60#specifications',
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-albion-v23' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-albion-v23#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-jazz2-v23' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-jazz2-v23#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-lauritz-v23' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-lauritz-v23#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-marta-v23' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-marta-v23#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'luton-2-r' => [
            'source' => 'https://kotlov.by/oblicovki/luton-2-r#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
                'elektrokamin-royal-flame-dioramic-33-w-led-fx',
            ],
        ],
        'portal-praga' => [
            'source' => 'https://kotlov.by/oblicovki/portal-praga#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
                'elektrokamin-royal-flame-dioramic-33-w-led-fx',
            ],
        ],
        'portal-smolcom-lindos-fs33' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-lindos-fs33#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
            ],
        ],
        'portal-smolcom-govard-fs33w' => [
            'source' => 'https://kotlov.by/oblicovki/portal-smolcom-govard-fs33w#specifications',
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-w-led-fx',
            ],
        ],
        'portal-boston' => [
            'source' => 'https://kotlov.by/oblicovki/portal-boston#specifications',
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
                'elektrokamin-dimplex-xhd28l-int',
            ],
        ],
        'portal-soho' => [
            'source' => 'https://kotlov.by/oblicovki/portal-soho#specifications',
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
    ],
];
