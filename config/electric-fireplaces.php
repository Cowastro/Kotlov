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

    // Exact models named in portal specifications or in the manufacturers' published kits and compatibility tables.
    'compatibility' => [
        'portal-smolcom-deli-v42' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-deli-v42#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-optima-v42' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-optima-v42#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-govard-v42' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-govard-v42#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-line-v60' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-line-v60#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-valerie-v60' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-valerie-v60#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-albion-v60' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-albion-v60#specifications'],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-albion-v23' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-albion-v23#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-jazz2-v23' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-jazz2-v23#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-lauritz-v23' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-lauritz-v23#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-marta-v23' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-marta-v23#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'luton-2-r' => [
            'sources' => ['https://kotlov.by/oblicovki/luton-2-r#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
                'interflame-panoramic-33w-led-fx',
            ],
        ],
        'portal-praga' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-praga#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
                'interflame-panoramic-33w-led-fx',
            ],
        ],
        'portal-smolcom-lindos-fs33' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-lindos-fs33#specifications'],
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
            ],
        ],
        'portal-smolcom-govard-fs33w' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-smolcom-govard-fs33w#specifications'],
            'inserts' => [
                'interflame-panoramic-33w-led-fx',
            ],
        ],
        'portal-soho' => [
            'sources' => ['https://kotlov.by/oblicovki/portal-soho#specifications'],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],

        'athena' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/533/',
            ],
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
            ],
        ],
        'brighton' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4059/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4021/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'brighton-uglovoj' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/454/',
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/442/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'bronx' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/728/Katalog_2018_Dimplex.pdf',
            ],
            'inserts' => [
                'yelektrokamin-symphony-26',
            ],
        ],
        'lucca' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4895/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        ...array_fill_keys([
            'modena',
            'portal-cabinet',
        ], [
            'sources' => [
                'https://dimplex.ru/upload/iblock/728/Katalog_2018_Dimplex.pdf',
            ],
            'inserts' => [
                'yelektrokamin-albany',
            ],
        ]),
        ...array_fill_keys([
            'neapol',
            'portal-lumsden',
            'portal-provence',
        ], [
            'sources' => [
                'https://dimplex.ru/upload/iblock/728/Katalog_2018_Dimplex.pdf',
            ],
            'inserts' => [
                'elektrokamin-dimplex-brookline-black',
                'elektrokamin-dimplex-chesford',
                'yelektrokamin-cavendish',
            ],
        ]),
        'olympia' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/8d2/h1dcgzhaapwbhsmdo9hyj5twowwkjzjg/Catalog-Dimplex-2024.pdf',
            ],
            'inserts' => [
                'yelektrokamin-albany',
            ],
        ],
        'portal-abu-dabi-c600' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/8d2/h1dcgzhaapwbhsmdo9hyj5twowwkjzjg/Catalog-Dimplex-2024.pdf',
            ],
            'inserts' => [
                'yelektrokamin-cassete-600',
            ],
        ],
        'portal-adel' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/770/dimplex_2020.pdf',
            ],
            'inserts' => [
                'yelektrokamin-juneau',
            ],
        ],
        'portal-amalfi-c400' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/728/Katalog_2018_Dimplex.pdf',
                'https://dimplex.ru/map/',
            ],
            'inserts' => [
                'yelektrokamin-cassete-400',
                'elektrokamin-dimplex-cassette-400-lnh-int',
            ],
        ],
        'portal-ampir-c400' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/8d2/h1dcgzhaapwbhsmdo9hyj5twowwkjzjg/Catalog-Dimplex-2024.pdf',
                'https://dimplex.ru/catalog/kaminokomplekt-ampir-slonovaya-kost-s-ochagom-cassette-400-lnh-int-ps-bez-drov-504/',
            ],
            'inserts' => [
                'yelektrokamin-cassete-400',
                'elektrokamin-dimplex-cassette-400-lnh-int',
            ],
        ],
        'portal-boston' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3872/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3895/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3957/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3865/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
                'elektrokamin-dimplex-xhd28l-int',
                'elektrokamin-royal-flame-dioramic-28-led-fx',
            ],
        ],
        ...array_fill_keys([
            'portal-derby',
            'portal-venice',
        ], [
            'sources' => [
                'https://dimplex.ru/map/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-dimplex-brookline-black',
                'elektrokamin-dimplex-chesford',
                'elektrokamin-dimplex-danville-black-fb2',
                'elektrokamin-dimplex-flagstaff',
            ],
        ]),
        'portal-doublef' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/6276/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
            ],
        ],
        'portal-greece-c400' => [
            'sources' => [
                'https://dimplex.ru/upload/iblock/8d2/h1dcgzhaapwbhsmdo9hyj5twowwkjzjg/Catalog-Dimplex-2024.pdf',
                'https://dimplex.ru/catalog/kaminokomplekt-greece-pod-cassette-400-s-ochagom-cassette-400-lnh-int-ps-bez-drov-506/',
            ],
            'inserts' => [
                'yelektrokamin-cassete-400',
                'elektrokamin-dimplex-cassette-400-lnh-int',
            ],
        ],
        'portal-lagos' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4767/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'portal-manor-sym-26' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/507/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
            ],
        ],
        'portal-smolcom-albion-corner-v23' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/6476/',
            ],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
            ],
        ],
        'portal-smolcom-albion-mbjupsym26-bianko-belyi' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/425/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/426/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-albion-v23-10772' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/3425/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/3417/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'portal-smolcom-alioth-new-mbjupsym26-bianko-belyi' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/431/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/469/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-alioth-v42' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6905/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6909/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        ...array_fill_keys([
            'portal-smolcom-boston-mbjupsym26-belyi-vanil',
            'portal-smolcom-boston-mbjupsym26-dub-antik',
            'portal-smolcom-boston-mbjupsym26-maxagon-koricnevyi-antik',
        ], [
            'sources' => [
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/747/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3872/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/3895/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ]),
        'portal-smolcom-boston-v60' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6405/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/3365/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        ...array_fill_keys([
            'portal-smolcom-brighton-corner-std-asp-belyi-dub-patina-zoloto',
            'portal-smolcom-brighton-corner-std-asp-dub-antik',
            'portal-smolcom-brighton-corner-std-asp-maxagon-koricnevyi-antik',
            'portal-smolcom-brighton-corner-std-asp-temnyi-orex',
        ], [
            'sources' => [
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/751/',
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/454/',
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/442/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ]),
        ...array_fill_keys([
            'portal-smolcom-brighton-std-asp-belyi-dub-patina-zoloto',
            'portal-smolcom-brighton-std-asp-belyi-vanil',
            'portal-smolcom-brighton-std-asp-dub-antik',
            'portal-smolcom-brighton-std-asp-maxagon-koricnevyi-antik',
            'portal-smolcom-brighton-std-asp-temnyi-orex',
        ], [
            'sources' => [
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/752/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4059/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4021/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ]),
        'portal-smolcom-brittany-fs25' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6751/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'portal-smolcom-deli-v60-bianko-belyi' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6827/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6831/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-galaxy-cg60' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6509/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6513/',
            ],
            'inserts' => [
                'elektrokamin-royal-flame-crystal-60-rf',
                'elektrokamin-royal-flame-galaxy-60-rf',
            ],
        ],
        'portal-smolcom-govard-v42-cernyi' => [
            'sources' => [
                'https://smolcom.by/catalog/portali/lineynye-portaly/6707/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6708/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6712/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-island-c400' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4693/',
            ],
            'inserts' => [
                'yelektrokamin-cassete-400',
            ],
        ],
        'portal-smolcom-jazz-tv-stand-mb_jup_sym26' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4697/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4701/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-koln-mb_jup_sym26' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4717/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4727/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-lindos-mb_jup_sym26' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4875/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4879/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-line-v42' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6537/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6541/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-line-v42-cernyi' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6537/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6541/',
                'https://smolcom.by/catalog/portali/lineynye-portaly/6534/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-line-v60-cernyi' => [
            'sources' => [
                'https://smolcom.by/catalog/portali/lineynye-portaly/815/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/3349/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6412/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-60-led',
                'elektrokamin-real-flame-vision-60-log-led',
            ],
        ],
        'portal-smolcom-london-fs25' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6547/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'portal-smolcom-lumsden-std-asp' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4979/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4927/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        ...array_fill_keys([
            'portal-smolcom-lumsden-std-asp-belyi-vanil',
            'portal-smolcom-lumsden-std-asp-dub-antik',
            'portal-smolcom-lumsden-std-asp-maxagon-koricnevyi-antik',
            'portal-smolcom-lumsden-std-asp-temnyi-orex',
        ], [
            'sources' => [
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/822/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4979/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4927/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ]),
        'portal-smolcom-monferrato-fs25' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6774/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'portal-smolcom-neoclassic-sym26_mb_jup' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5424/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5431/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5452/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
                'elektrokamin-dimplex-xhd28l-int',
            ],
        ],
        'portal-smolcom-optima-v42-seryi-grafit' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6957/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
            ],
        ],
        'portal-smolcom-sanremo-vis42-razbornyi-bianko-belyi' => [
            'sources' => [
                'https://royalflame.ru/about/news/kaminy-tumby-v-assortimente-royal-flame/',
                'https://royalflame.ru/catalog/ochag-vision-42-log-led/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-soho-mbjupsym26-dub-antik' => [
            'sources' => [
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/876/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5541/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5554/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-stamford-mbjupsym26-bianko-belyi' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6450/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6462/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'portal-smolcom-stamford-mbjupsym26-seryi-grafit' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6450/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6462/',
                'https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/6344/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        ...array_fill_keys([
            'portal-smolcom-stone-montebello-std-asp-dub-antik',
            'portal-smolcom-stone-montebello-std-asp-maxagon-koricnevyi-antik',
            'portal-smolcom-stone-montebello-std-asp-temnyi-orex',
        ], [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/3521/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/542/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ]),
        'portal-smolcom-toronto-v23' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6936/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6932/',
            ],
            'inserts' => [
                'elektrokamin-royal-flame-vision-23-ef-led-fx-12172',
                'elektrokamin-royal-flame-vision-23-ef-led-3d-fx-12171',
            ],
        ],
        'portal-smolcom-valerie-v42' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6660/',
                'https://smolcom.by/catalog/kaminokomplecty/multimedia-kaminokomplekti/6664/',
            ],
            'inserts' => [
                'elektrokamin-real-flame-vision-42-led',
                'elektrokamin-real-flame-vision-42-log-led',
            ],
        ],
        'portal-smolcom-verona-corner-std-asp' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/3254/',
                'https://smolcom.by/catalog/kaminokomplecty/uglovie-kaminokomplekti/3235/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'portal-smolcom-verona-std-asp' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5749/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5717/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'portal-sydney' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/550/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'san-marino' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/537/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/5498/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
        'stone' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/3521/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplekti-iz-kamniy/542/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-atherton',
                'elektrokamin-real-flame-aspen-black',
            ],
        ],
        'theodor' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/4352/',
            ],
            'inserts' => [
                'interflame-panoramic-25-led-fx',
            ],
        ],
        'tokio' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/553/',
            ],
            'inserts' => [
                'elektrokamin-royal-flame-dioramic-33-led-fx',
            ],
        ],
        'turin' => [
            'sources' => [
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6631/',
                'https://smolcom.by/catalog/kaminokomplecty/kaminokomplecti-iz-dereva-mdf/6617/',
            ],
            'inserts' => [
                'elektrokamin-dimplex-symphony-26-df2608-int',
                'elektrokamin-royal-flame-jupiter-fx-n-black',
            ],
        ],
    ],
];
