<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                // ── Левая часть: основные поля (2/3 ширины) ────────────────
                Section::make('Карточка товара')
                    ->description('Название, каталог и основная привязка. Остальные данные разнесены по вкладкам ниже.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название товара')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                $set('slug', Str::slug($state));
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('URL (slug)')
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('h1')
                            ->label('Заголовок H1'),

                        TextInput::make('sku')
                            ->label('Артикул (SKU)'),

                        Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('brand_id')
                            ->label('Бренд')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('supplier_id')
                            ->label('Поставщик')
                            // Product::supplier() is a legacy relation to a supplier user.
                            // Catalog imports store the real supplier ID from `suppliers`,
                            // so relationship() rendered a bare numeric ID and rejected it
                            // during save when a matching user did not exist.
                            ->options(fn (): array => Supplier::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),

                        Placeholder::make('supplier_contacts_summary')
                            ->label('Контакты поставщика')
                            ->content(fn (?Product $record): HtmlString => self::supplierContactsHtml($record)),
                    ]),

                // ── Правая часть: статусы + цены (1/3 ширины) ──────────────
                Tabs::make('Разделы товара')
                    ->persistTabInQueryString('product-tab')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Продажи и наличие')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Section::make('Статусы')
                                            ->description('Видимость товара и маркетинговые метки.')
                                            ->columns(2)
                                            ->schema([
                                                Toggle::make('is_active')
                                                    ->label('Активен')
                                                    ->default(true),

                                                Toggle::make('in_stock')
                                                    ->label('В наличии')
                                                    ->default(true),

                                                Select::make('availability_status')
                                                    ->label('Статус наличия')
                                                    ->options(Product::availabilityStatusOptions())
                                                    ->default(Product::AVAILABILITY_IN_STOCK)
                                                    ->required()
                                                    ->columnSpanFull(),
                                                Toggle::make('is_featured')
                                                    ->label('Хит продаж'),

                                                Toggle::make('is_new')
                                                    ->label('Новинка'),

                                                Toggle::make('is_sale')
                                                    ->label('Акция'),

                                                Toggle::make('is_archived')
                                                    ->label('Архивный (снят с продажи)')
                                                    ->helperText('Скрыт из каталога, noindex в поиске')
                                                    ->default(false)
                                                    ->columnSpanFull(),

                                                TextInput::make('sort_order')
                                                    ->label('Сортировка')
                                                    ->numeric()
                                                    ->default(0),
                                            ]),

                                        Section::make('Цены')
                                            ->description('Цена поставщика, розничная цена и фактический остаток.')
                                            ->columns(3)
                                            ->schema([
                                                // Цена поставщика + валюта поставщика — в одной строке (2+1 колонки).
                                                // Изменение пересчитывает BYN цену в реальном времени.
                                                // При сохранении EditProduct.afterSave() сохраняет в supplier_products.
                                                TextInput::make('supplier_price_virtual')
                                                    ->label('Цена поставщика')
                                                    ->helperText(function (?Product $record): string {
                                                        if (! $record?->id) {
                                                            return '';
                                                        }
                                                        $sp = DB::table('supplier_products')
                                                            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
                                                            ->where('supplier_products.product_id', $record->id)
                                                            ->select('supplier_products.currency_rate', 'supplier_products.currency', 'suppliers.name as supplier_name', 'suppliers.updated_at')
                                                            ->orderByRaw("CASE WHEN supplier_products.currency != 'BYN' THEN 0 ELSE 1 END")
                                                            ->first();
                                                        if (! $sp) {
                                                            return '';
                                                        }
                                                        if ($sp->currency === 'BYN') {
                                                            return $sp->supplier_name;
                                                        }
                                                        $rate = number_format((float) $sp->currency_rate, 4, '.', ' ');
                                                        $upd = $sp->updated_at
                                                            ? Carbon::parse($sp->updated_at)->format('d.m.Y H:i')
                                                            : '—';

                                                        return "{$sp->supplier_name} · курс 1 {$sp->currency} = {$rate} BYN (обновлён {$upd})";
                                                    })
                                                    ->numeric()
                                                    ->step('0.01')
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $get, callable $set, ?Product $record): void {
                                                        if ($state === null || $state === '' || ! $record?->id) {
                                                            return;
                                                        }
                                                        $currency = $get('supplier_currency_virtual') ?: 'BYN';
                                                        if ($currency === 'BYN') {
                                                            $set('price', round((float) $state, 2));

                                                            return;
                                                        }
                                                        $sp = DB::table('supplier_products')
                                                            ->where('product_id', $record->id)
                                                            ->where('currency', $currency)
                                                            ->first();
                                                        if ($sp && $sp->currency_rate) {
                                                            $set('price', round((float) $state * (float) $sp->currency_rate, 2));
                                                        }
                                                    })
                                                    ->visible(fn (?Product $record): bool => (bool) DB::table('supplier_products')
                                                        ->where('product_id', $record?->id ?? 0)
                                                        ->exists())
                                                    ->columnSpan(2),

                                                Select::make('supplier_currency_virtual')
                                                    ->label('Валюта поставщика')
                                                    ->options(['EUR' => 'EUR', 'USD' => 'USD', 'RUB' => 'RUB', 'BYN' => 'BYN'])
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $get, callable $set, ?Product $record): void {
                                                        if (! $record?->id) {
                                                            return;
                                                        }
                                                        $price = (float) ($get('supplier_price_virtual') ?? 0);
                                                        if (! $price) {
                                                            return;
                                                        }
                                                        if ($state === 'BYN') {
                                                            $set('price', round($price, 2));

                                                            return;
                                                        }
                                                        $sp = DB::table('supplier_products')
                                                            ->where('product_id', $record->id)
                                                            ->where('currency', $state)
                                                            ->first();
                                                        if ($sp && $sp->currency_rate) {
                                                            $set('price', round($price * (float) $sp->currency_rate, 2));
                                                        }
                                                    })
                                                    ->visible(fn (?Product $record): bool => (bool) DB::table('supplier_products')
                                                        ->where('product_id', $record?->id ?? 0)
                                                        ->exists())
                                                    ->columnSpan(1),

                                                TextInput::make('price')
                                                    ->label('Цена на сайте')
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('BYN')
                                                    ->helperText('Автоматически из цены поставщика × курс НБРБ. Можно скорректировать вручную.')
                                                    ->default(0)
                                                    ->columnSpan(2),

                                                TextInput::make('price_old')
                                                    ->label('Старая цена')
                                                    ->numeric()
                                                    ->prefix('BYN')
                                                    ->columnSpan(1),

                                                Select::make('currency')
                                                    ->label('Валюта сайта')
                                                    ->helperText('Валюта в которой показывается цена на сайте (обычно BYN).')
                                                    ->options(['BYN' => 'BYN', 'USD' => 'USD', 'EUR' => 'EUR', 'RUB' => 'RUB'])
                                                    ->default('BYN')
                                                    ->required(),

                                                TextInput::make('stock_qty')
                                                    ->label('Кол-во на складе')
                                                    ->numeric(),

                                                TextInput::make('unit')
                                                    ->label('Ед. измерения')
                                                    ->default('шт'),

                                                TextInput::make('warranty')
                                                    ->label('Гарантия')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                // ── Описание ─────────────────────────────────────────────────
                            ]),

                        Tab::make('Описание')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Тексты карточки')
                                    ->description('Краткое описание для анонса и полный текст с преимуществами товара.')
                                    ->schema([
                                        RichEditor::make('short_description')
                                            ->label('Краткое описание')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline',
                                                'bulletList', 'orderedList',
                                                'link',
                                                'undo', 'redo',
                                            ]),

                                        RichEditor::make('content')
                                            ->label('Полное описание')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline',
                                                'bulletList', 'orderedList',
                                                'h2', 'h3', 'h4',
                                                'link', 'blockquote',
                                                'attachFiles',
                                                'undo', 'redo',
                                            ])
                                            ->fileAttachmentsDirectory('editor-uploads')
                                            ->fileAttachmentsDisk('public')
                                            ->fileAttachmentsVisibility('public'),
                                    ]),

                                // ── Фотографии ────────────────────────────────────────────────
                            ]),

                        Tab::make('Фотографии')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Медиафайлы')
                                    ->description('Первое фото используется как основное в каталоге. Порядок можно менять перетаскиванием.')
                                    ->schema([
                                        Repeater::make('existing_images')
                                            ->label('Текущие фото (удалите строку — фото удалится)')
                                            ->schema([
                                                Placeholder::make('img_preview')
                                                    ->label('')
                                                    ->content(function ($get, $record): HtmlString {
                                                        $path = (string) ($get('path') ?? '');
                                                        if (! $path) {
                                                            return new HtmlString('<span class="text-gray-400 text-xs">нет пути</span>');
                                                        }
                                                        $url = self::resolveImageUrl($path, $record);

                                                        return new HtmlString(
                                                            '<img src="'.e($url).'" style="max-height:72px;border-radius:4px;object-fit:cover;" '
                                                            .'onerror="this.style.opacity=0.3">'
                                                        );
                                                    }),

                                                TextInput::make('path')
                                                    ->label('Путь')
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->columnSpan(3),
                                            ])
                                            ->columns(4)
                                            ->addable(false)
                                            ->reorderable(true)
                                            ->collapsible()
                                            ->defaultItems(0),

                                        FileUpload::make('images')
                                            ->label('Загрузить новые фото')
                                            ->helperText('Новые файлы добавятся к существующим')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                            ->disk('public')
                                            ->multiple()
                                            ->reorderable()
                                            ->directory('products')
                                            ->maxFiles(20),
                                    ]),

                                // ── Характеристики ────────────────────────────────────────────
                            ]),

                        Tab::make('Характеристики')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make('Технические данные')
                                    ->description('Структурированные параметры для фильтров, сравнения и карточки товара.')
                                    ->schema([
                                        Repeater::make('specs')
                                            ->label('Технические характеристики')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Название')
                                                    ->required(),
                                                TextInput::make('value')
                                                    ->label('Значение')
                                                    ->required(),
                                                TextInput::make('unit')
                                                    ->label('Единица (кВт, м²)'),
                                            ])
                                            ->columns(3)
                                            ->addActionLabel('Добавить характеристику')
                                            ->collapsible()
                                            ->defaultItems(0),
                                    ]),

                                // ── Дополнительно ─────────────────────────────────────────────
                                Section::make('Дополнительно')
                                    ->columnSpanFull()
                                    ->columns(3)
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('weight')
                                            ->label('Вес (кг)')
                                            ->numeric(),

                                        TextInput::make('video_url')
                                            ->label('Ссылка на видео')
                                            ->url(),
                                    ]),

                                // ── SEO ───────────────────────────────────────────────────────
                            ]),

                        Tab::make('Промо и сервис')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Section::make('Промо, сервис и документы')
                                    ->description('Акценты в карточке, сервисная информация и файлы для покупателя.')
                                    ->columns(2)
                                    ->schema([
                                        Repeater::make('promo_flags')
                                            ->label('Промо-флаги')
                                            ->helperText('Показываются рядом с ценой на карточке товара.')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Код')
                                                    ->helperText('Например: chimney_gift, free_delivery, warranty')
                                                    ->required(),
                                                TextInput::make('label')
                                                    ->label('Текст')
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Добавить флаг')
                                            ->defaultItems(0),

                                        KeyValue::make('service_info')
                                            ->label('Вкладка "Сервис"')
                                            ->keyLabel('Поле')
                                            ->valueLabel('Значение')
                                            ->addActionLabel('Добавить строку'),

                                        Repeater::make('documents')
                                            ->label('Документы')
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('Название')
                                                    ->required(),
                                                TextInput::make('url')
                                                    ->label('URL')
                                                    ->url()
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Добавить документ')
                                            ->defaultItems(0)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make('Поисковое представление')
                                    ->description('Заголовок и описание для поисковых систем. Пустые поля можно заполнять из данных товара автоматически.')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Meta Title')
                                            ->columnSpanFull(),

                                        Textarea::make('meta_keywords')
                                            ->label('Meta Keywords')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function supplierContactsHtml(?Product $product): HtmlString
    {
        if (! $product?->exists) {
            return new HtmlString('<span style="color:#9ca3af">Контакты появятся после сохранения товара.</span>');
        }

        $suppliers = $product->supplierProducts->pluck('supplier')
            ->merge($product->integrationProducts->pluck('source.supplier'))
            ->filter(fn ($supplier): bool => $supplier instanceof Supplier)
            ->unique('id')
            ->values();

        if ($suppliers->isEmpty()) {
            return new HtmlString(
                '<div style="padding:12px 14px;border:1px dashed #4b5563;border-radius:10px;color:#9ca3af">'
                .'Поставщик ещё не привязан. Выберите его слева или свяжите товар через обмен 1С.'
                .'</div>',
            );
        }

        $cards = $suppliers->map(function (Supplier $supplier): string {
            $counterparty = self::findSupplierCounterparty($supplier);
            $contacts = self::supplierContactLines($supplier, $counterparty);
            $supplierUrl = e(SupplierResource::getUrl('edit', ['record' => $supplier], panel: 'admin'));
            $crmUrl = $counterparty
                ? e(CrmCounterpartyResource::getUrl('edit', ['record' => $counterparty], panel: 'admin'))
                : null;

            $links = '<a href="'.$supplierUrl.'" style="color:#60a5fa;text-decoration:none">Поставщик</a>';
            if ($crmUrl) {
                $links .= '<span style="color:#6b7280"> · </span><a href="'.$crmUrl.'" style="color:#60a5fa;text-decoration:none">CRM</a>';
            }

            $body = $contacts === []
                ? '<div style="margin-top:7px;color:#f59e0b;font-size:12px">Телефон и e-mail пока не заполнены</div>'
                : '<div style="display:grid;gap:6px;margin-top:8px">'.implode('', $contacts).'</div>';

            return '<div style="padding:12px 14px;border:1px solid #374151;border-radius:10px;background:rgba(31,41,55,.35)">'
                .'<div style="display:flex;align-items:center;justify-content:space-between;gap:12px">'
                .'<strong style="color:#f3f4f6">'.e($supplier->name).'</strong>'
                .'<span style="font-size:11px;white-space:nowrap">'.$links.'</span>'
                .'</div>'.$body.'</div>';
        })->implode('<div style="height:8px"></div>');

        return new HtmlString($cards);
    }

    private static function findSupplierCounterparty(Supplier $supplier): ?CrmCounterparty
    {
        if ($supplier->crmCounterparty) {
            return $supplier->crmCounterparty->loadMissing([
                'contactPoints' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary'),
                'contactPoints.contact',
            ]);
        }

        return CrmCounterparty::query()
            ->with([
                'contactPoints' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary'),
                'contactPoints.contact',
            ])
            ->where(function ($query) use ($supplier): void {
                $query
                    ->where('name', $supplier->name)
                    ->orWhere('public_name', $supplier->name)
                    ->orWhere('legal_name', $supplier->name);
            })
            ->first();
    }

    /** @return array<int, string> */
    private static function supplierContactLines(Supplier $supplier, ?CrmCounterparty $counterparty): array
    {
        $items = collect();

        if ($counterparty) {
            foreach ($counterparty->contactPoints as $point) {
                if (! in_array($point->type, ['phone', 'email'], true) || blank($point->value)) {
                    continue;
                }

                $items->push([
                    'type' => $point->type,
                    'value' => trim((string) $point->value),
                    'label' => collect([
                        $point->contact?->full_name,
                        $point->contact?->position,
                        $point->label,
                    ])->filter()->unique()->join(' · '),
                ]);
            }
        }

        $rawContact = (string) $supplier->contact;
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $rawContact, $emails);
        preg_match_all('/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u', preg_replace('/https?:\/\/\S+/iu', '', $rawContact) ?? '', $phones);

        foreach ($phones[0] ?? [] as $phone) {
            $items->push(['type' => 'phone', 'value' => trim($phone), 'label' => 'Из карточки поставщика']);
        }
        foreach ($emails[0] ?? [] as $email) {
            $items->push(['type' => 'email', 'value' => trim($email), 'label' => 'Из карточки поставщика']);
        }

        return $items
            ->unique(fn (array $item): string => $item['type'].'|'.mb_strtolower($item['value']))
            ->map(function (array $item): string {
                $isPhone = $item['type'] === 'phone';
                $href = $isPhone
                    ? 'tel:'.preg_replace('/[^\d+]/', '', $item['value'])
                    : 'mailto:'.$item['value'];
                $icon = $isPhone ? '☎' : '✉';
                $label = $item['label'] !== ''
                    ? '<span style="color:#9ca3af;font-size:11px">'.e($item['label']).'</span>'
                    : '';

                return '<div style="display:flex;align-items:center;gap:8px;min-width:0">'
                    .'<span aria-hidden="true" style="color:#f59e0b">'.$icon.'</span>'
                    .'<a href="'.e($href).'" style="color:#e5e7eb;font-weight:600;text-decoration:none">'.e($item['value']).'</a>'
                    .$label.'</div>';
            })
            ->values()
            ->all();
    }

    /**
     * Строит URL для предпросмотра изображения в форме.
     * Поддерживает пути из kotlov.by и локально загруженные файлы.
     */
    private static function resolveImageUrl(string $path, ?object $record = null): string
    {
        // Загружено через FileUpload → public storage
        if (str_starts_with($path, 'products/')) {
            return asset('storage/'.$path);
        }

        // img/products/... → public path
        if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
            return '/'.ltrim($path, '/');
        }

        // product/000/000065/file.jpg
        if (str_starts_with($path, 'product/')) {
            return '/proxy-image/'.$path;
        }

        // 000/000065/file.jpg (два слеша)
        if (substr_count($path, '/') >= 2) {
            return '/proxy-image/product/'.$path;
        }

        // Просто имя файла — используем Product::imageUrl(), потому что legacy путь
        // зависит от SKU/ID и не должен собираться здесь наугад.
        if ($record && method_exists($record, 'imageUrl')) {
            $images = $record->images;
            if (is_string($images)) {
                $images = json_decode($images, true) ?: [];
            }

            $index = array_search($path, (array) $images, true);

            return $record->imageUrl($index === false ? 0 : $index);
        }

        return asset('img/products/product-placeholder.jpg');
    }
}
