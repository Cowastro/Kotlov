<?php

return [
    'suppliers' => [
        'elicon_gas_meters' => [
            'name' => 'Эликон',
            'code' => 'elicon',
            'title' => 'Эликон: счетчики газа',
            'description' => 'Обновляет цены, наличие, описания, характеристики и фотографии бытовых счетчиков газа.',
            'command' => 'supplier:sync-elicon-gas-meters',
            'source_url' => 'https://elicon.by/product-category/bitovie_schetchiki_gaza/',
            'is_active' => true,
            'image_disk_path' => 'img/products/elicon',
        ],

        // Future suppliers:
        // 'teplov' => [...],
        // 'berezka' => [...],
        // 'darco' => [...],
    ],
];
