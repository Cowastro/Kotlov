# Контекст проекта kotlov.by для Claude

## Проект
Laravel-магазин котлов и печей **kotlov.by** (Беларусь).  
Сервер: `h209767@vh154`, путь: `~/www/new.kotlov.by`  
PHP: `/opt/alt/php83/usr/bin/php`  
Git remote: `https://github.com/Cowastro/Kotlov.git` (main branch)

---

## Стандартный деплой

```bash
cd ~/www/new.kotlov.by
git pull origin main
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan optimize:clear
```

---

## Архитектура синхронизации поставщиков

### Таблицы
- `suppliers` — поставщики (code, name, currency, currency_rate)
- `supplier_syncs` — регистрация команд (key, command, source_url)
- `supplier_products` — связь товар↔поставщик (supplier_id, product_id, source_wp_id, supplier_article, price)
- `products` — товары (sku KOTLOV-XXXXXX, brand_id, category_id, content, images JSON, specs JSON)
- `brands`, `categories`, `attributes`, `product_attribute_values`

### Паттерн новой команды синхронизации

Все команды живут в `app/Console/Commands/Sync*Command.php`.  
Шаблон — копируй `SyncTekhnoLitCommand.php` или `SyncPegasCommand.php` (самые чистые).

**Обязательные константы:**
```php
private const SUPPLIER_CODE   = 'mой_код';       // уникальный slug поставщика
private const SYNC_KEY        = 'мой_ключ';       // уникальный ключ в supplier_syncs
private const SOURCE_URL      = 'https://...';    // URL каталога
private const BASE_URL        = 'https://...';    // базовый URL сайта
private const IMAGE_DISK_PATH = 'img/products/мой_код';
private const CATEGORY_ID     = 69;               // ID категории в БД
private const BRAND_SLUG      = 'мой_бренд';
```

**Обязательные флаги команды:**
```
{--apply}      // записывает в БД
{--dry-run}    // только показывает что будет
{--limit=}     // для тестов
{--no-images}  // пропустить скачивание фото
{--enrich}     // AI-генерация описаний (требует ANTHROPIC_API_KEY или AI_API_KEY)
{--sleep=300}  // задержка между запросами (мс)
```

**Структура handle():**
```php
public function handle(): int
{
    $apply         = (bool) $this->option('apply');
    $enrichContent = (bool) $this->option('enrich');
    
    $enricher = new AiContentEnricher();
    if ($enrichContent && !$enricher->isAvailable()) {
        $this->warn('--enrich: no AI provider configured');
        $enrichContent = false;
    }
    
    $items = $this->scrapeCatalog($sleepMs);   // массив товаров
    
    if (!$apply) return $this->dryRun($items);
    
    $brandId    = $this->ensureBrand($now);
    $supplierId = $this->ensureSupplier($now);
    $syncId     = $this->ensureSupplierSync($now);
    
    foreach ($items as $item) {
        $detail  = $this->scrapeProduct($item['url']);
        $merged  = array_merge($item, $detail);
        $product = $this->findProduct($merged, $supplierId, $brandId);
        $isNew   = !$product;
        
        if ($isNew && $enrichContent) {
            $aiText = $enricher->enrich($item['name'], 'БрендИмя', $merged['content'], $merged['attributes']);
            if ($aiText) $merged['content'] = $aiText;
        }
        
        $productId = $this->upsertProduct($merged, $product, $images, $brandId, $now);
        $this->upsertSupplierProduct($merged, $productId, $sku, $supplierId, $syncId, $now);
        $this->syncAttributes($productId, $merged['attributes'], $now);
    }
}
```

### findProduct() — ВАЖНО
Всегда включать `content` и `images` в SELECT при поиске по нормализованному имени:
```php
->get(['id', 'sku', 'name', 'price', 'content', 'images'])
```
Иначе: `Undefined property: stdClass::$content`

### upsertProduct() — правила
- `meta_title` писать как `$item['name'] . ' купить в %city%'` (НЕ "в Минске"!)
- Не перезаписывать непустые content/images если новые пустые
- `ensureSupplier()` — НИКОГДА не перезаписывать `currency` и `currency_rate`

### SKU
```php
private function nextKotlovSku(): string
{
    $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')
        ->pluck('sku')
        ->map(fn($s) => preg_match('/^KOTLOV-(\d+)$/', $s, $m) ? (int)$m[1] : 0)
        ->max() ?? 0;
    do { $sku = sprintf('KOTLOV-%06d', ++$max); }
    while (DB::table('products')->where('sku', $sku)->exists());
    return $sku;
}
```

---

## AI-генерация описаний

Сервис: `app/Services/AiContentEnricher.php`

**Поддерживаемые провайдеры** (приоритет по порядку):
1. `ANTHROPIC_API_KEY` → Claude Haiku
2. `AI_API_KEY` + `AI_API_URL` + `AI_MODEL` → любой OpenAI-совместимый

**Настроено на сервере:** DeepSeek
```env
AI_API_KEY=sk-...
AI_API_URL=https://api.deepseek.com/chat/completions
AI_MODEL=deepseek-chat
```

**Промпт генерирует:**
- 2 абзаца `<p>` + список `<ul><li>` с 4-5 преимуществами
- Плейсхолдер `%city%` вместо названия города (заменяется динамически)
- 200–320 слов, только теги `<p><ul><li><strong>`

**Массовое обогащение существующих товаров:**
```bash
/opt/alt/php83/usr/bin/php artisan product:enrich-content --brand=SLUG
/opt/alt/php83/usr/bin/php artisan product:enrich-content --brand=SLUG --force   # перезаписать
/opt/alt/php83/usr/bin/php artisan product:enrich-content --category=ID
/opt/alt/php83/usr/bin/php artisan product:enrich-content --sku=KOTLOV-000001
```

---

## Города (%city% подстановка)

- 114 городов, поддомены вида `minsk.kotlov.by`, `buda-koshelevo.kotlov.by`
- Middleware устанавливает `view()->share('cityIn', 'в Минске')`
- `ProductController` заменяет `%city%` в meta_title, meta_description, meta_keywords, **и content**
- Все sync команды и AI промпт используют `%city%` — НЕ "в Минске"

---

## Существующие команды синхронизации

| Команда | Поставщик | Источник | Категория | Валюта |
|---------|-----------|----------|-----------|--------|
| `supplier:sync-ecokamin-fireboxes` | ЭкоКамин | ecokamin.ru | 90 (Топки) | RUB |
| `supplier:sync-belkomin-tis-boilers` | TIS | belkomin.com | 54 (Котлы) | BYN |
| `supplier:sync-metabel` | Мета-Бел | metabel.by + Excel | разные | BYN |
| `supplier:sync-tekhnolit` | ТехноЛит | teplodvor.by/tekhnolit | 69 (Банные печи) | BYN |
| `supplier:sync-pegas` | Пегас | teplodvor.by/pegas | 69 (Банные печи) | BYN |
| `supplier:sync-elicon-gas-meters` | Эликон | — | — | BYN |

---

## teplodvor.by — структура HTML (важно для новых разделов)

Сайт teplodvor.by — один и тот же паттерн для ВСЕХ разделов:

**Листинг (каталог):**
```html
<div class="js_shop col-... product">
  <input name="good_id" value="12345">
  <a href="https://teplodvor.by/shop/..." class="shop-item-link">Название</a>
  <span class="js_shop_price">1234.56</span>
  <img src="/userfls/shop/small/10/12345_name.jpg">
</div>
```

**Пагинация:** `/shop/РАЗДЕЛ/page2/`, `/page3/` и т.д.  
**Признак следующей страницы:** `class="next_page"` в HTML

**Детальная страница:**
```html
<h1>Название товара</h1>
<section id="description">...</section>   <!-- описание -->
<table class="param">
  <tr>
    <td class="parametr"><span>Название характеристики</span></td>
    <td>Значение</td>
  </tr>
</table>
<!-- Фото: /userfls/shop/large/10/12345_name.jpg -->
```

**URL разделов teplodvor.by:**
- `/shop/tekhnolit/` — ТехноЛит (уже реализован)
- `/shop/pech-dlya-bani/pegas/` — Пегас (уже реализован)
- `/shop/pech-dlya-bani/gefest/` — Гефест
- `/shop/pech-dlya-bani/harvia/` — Harvia
- `/shop/pech-dlya-bani/helo/` — Helo
- `/shop/kaminy/` — Камины
- И другие...

---

## Создание новой команды для teplodvor.by

1. Скопировать `SyncPegasCommand.php` → `SyncXXXCommand.php`
2. Заменить все константы (SUPPLIER_CODE, SYNC_KEY, SOURCE_URL, BRAND_SLUG, CATEGORY_ID)
3. Обновить `normalizeName()` — убрать слова бренда из нормализации
4. Обновить `ensureBrand()` — имя, slug, страна
5. Обновить `ensureSupplier()` — название
6. Создать миграцию `database/migrations/ДАТА_register_XXX_supplier_sync.php`
7. Коммит + пуш + на сервере: `git pull && php artisan migrate && php artisan optimize:clear`

---

## Миграция для регистрации поставщика (шаблон)

```php
// database/migrations/2026_06_12_XXXXXX_register_XXX_supplier_sync.php
public function up(): void
{
    $now = now();
    if (!DB::table('suppliers')->where('code', 'МОЙ_КОД')->exists()) {
        DB::table('suppliers')->insert([
            'code' => 'МОЙ_КОД', 'name' => 'Название',
            'currency' => 'BYN', 'currency_rate' => 1,
            'contact' => 'https://...', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    if (!Schema::hasTable('supplier_syncs')) return;
    DB::table('supplier_syncs')->updateOrInsert(
        ['key' => 'МОЙ_КЛЮЧ'],
        ['name' => '...', 'code' => 'МОЙ_КОД', 'command' => 'supplier:sync-xxx',
         'source_url' => '...', 'image_disk_path' => 'img/products/xxx',
         'is_active' => true, 'created_at' => $now, 'updated_at' => $now,]
    );
}
```

---

## Известные проблемы / особенности

1. **ТехноЛит 9 товаров** — не создались из-за `findProduct` без `content` в SELECT. Фикс запушен (commit `2d4afad`), нужно ещё раз запустить `supplier:sync-tekhnolit --apply` на сервере после `git pull`.

2. **Мета-Бел бренд slug** — `meta-bel` (не `metabel`). Команда enrich: `--brand=meta-bel`.

3. **EcoKamin валюта RUB** — перед запуском `--apply` задать реальный курс RUB→BYN в админке `/admin/suppliers`. Защита: если курс = 1.0 при RUB, команда откажется применять.

4. **Изображения** — не коммитить скачанные файлы, не делать `git checkout` на них.

5. **Encoding в PHP строках** — Unicode символы («»""''–—) в строках PHP должны быть через `\u{AB}` и т.п., иначе parse error на некоторых серверах.
