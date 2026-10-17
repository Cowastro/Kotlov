<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Product;
use App\Models\PublicAiSelectionRun;
use App\Services\AiContentEnricher;
use App\Services\ContactRequestNotifier;
use App\Services\PublicAiProductSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAiProductSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_uses_deepseek_analysis_but_only_server_defined_questions(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn(json_encode([
            'category' => 'gas_meter',
            'summary' => 'Нужен левый счётчик G4 длиной 110 мм.',
            'known' => ['flow' => 'G4', 'direction' => 'Левый', 'length' => '110', 'injected' => '<script>alert(1)</script>'],
            'questions' => [['key' => 'dangerous', 'label' => 'Вопрос модели']],
        ], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->start('Счётчик газа G4 левый, длина 110 мм');

        $this->assertSame('gas_meter', $result['category']);
        $this->assertSame(['flow' => 'G4', 'direction' => 'Левый', 'length' => '110'], $result['known']);
        $this->assertSame(['budget'], array_column($result['questions'], 'key'));
        $this->assertTrue($result['ai_used']);
    }

    public function test_recommendations_only_contain_real_active_catalog_products_and_ignore_hallucinated_ids(): void
    {
        $category = $this->category('nasosy', 'Насосы');
        $active = $this->product($category, 'Насос TEST 40', 'pump-test-40', 700);
        $second = $this->product($category, 'Насос TEST 60', 'pump-test-60', 950);
        $archived = $this->product($category, 'Архивный насос', 'archived-pump', 500, ['is_archived' => true]);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->withArgs(function (string $prompt, int $tokens) use ($active, $second, $archived): bool {
            $this->assertStringContainsString('"id":'.$active->id, $prompt);
            $this->assertStringContainsString('"id":'.$second->id, $prompt);
            $this->assertStringNotContainsString('"id":'.$archived->id, $prompt);

            return $tokens === 1400;
        })->andReturn(json_encode(['matches' => [
            ['product_id' => 999999, 'role' => 'optimal', 'reason' => 'Выдуман'],
            ['product_id' => $active->id, 'role' => 'optimal', 'reason' => 'Есть нужный напор', 'confirmed' => ['Напор 40 м'], 'verify' => ['Рабочую точку']],
            ['product_id' => $archived->id, 'role' => 'reserve', 'reason' => 'Архив'],
            ['product_id' => $second->id, 'role' => 'reserve', 'reason' => 'Дешевле варианта ID 123 и есть запас по напору'],
        ]], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->recommend('Насос для дома с напором 40 метров', 'pump', ['budget' => '1000']);

        $this->assertSame([$active->id, $second->id], array_column($result['products'], 'id'));
        $this->assertTrue($result['ai_used']);
        $this->assertStringNotContainsString('ID 123', $result['products'][1]['reason']);
        $this->assertArrayNotHasKey('supplier', $result['products'][0]);
        $this->assertArrayNotHasKey('purchase_byn', $result['products'][0]);
    }

    public function test_it_falls_back_to_catalog_when_ai_is_unavailable(): void
    {
        $category = $this->category('schetchiki-gaza', 'Счётчики газа');
        $product = $this->product($category, 'Счётчик газа G4 левый L=110', 'meter-g4-left', 180);
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(false);

        $result = app(PublicAiProductSelection::class)->recommend('Нужен счётчик газа G4 левый 110 мм', 'gas_meter', []);

        $this->assertFalse($result['ai_used']);
        $this->assertSame($product->id, $result['products'][0]['id']);
        $this->assertStringContainsString('DeepSeek временно не ответил', $result['summary']);
    }

    public function test_explicit_meter_size_direction_and_length_outrank_a_close_ai_alternative(): void
    {
        $category = $this->category('schetchiki-gaza', 'Счётчики газа');
        Product::query()->insert(collect(range(1, 85))->map(fn (int $index): array => [
            'category_id' => $category->id,
            'name' => 'Счётчик газа общий '.$index,
            'slug' => 'generic-meter-'.$index,
            'sku' => 'GEN-'.$index,
            'price' => 150,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
        $exact = $this->product($category, 'Счётчик газа СГД G4 (левый) L=110', 'meter-g4-left-110', 200);
        $close = $this->product($category, 'Счётчик газа СГД G2.5 (левый) L=110', 'meter-g25-left-110', 180);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn(json_encode(['matches' => [
            ['product_id' => $close->id, 'role' => 'optimal', 'reason' => 'Дешевле'],
            ['product_id' => $exact->id, 'role' => 'reserve', 'reason' => 'Точный типоразмер'],
        ]], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->recommend(
            'Нужен счётчик газа G4 левый 110 мм',
            'gas_meter',
            ['flow' => 'G4', 'direction' => 'Левый', 'length' => '110']
        );

        $this->assertSame($exact->id, $result['products'][0]['id']);
        $this->assertSame('optimal', $result['products'][0]['role']);
        $this->assertSame($close->id, $result['products'][1]['id']);
    }

    public function test_selected_boiler_fuel_is_a_hard_catalog_constraint(): void
    {
        $boilers = $this->category('kotly', 'Котлы');
        $gas = Category::query()->create(['parent_id' => $boilers->id, 'slug' => 'gazovye', 'name' => 'Газовые котлы', 'is_active' => true]);
        $electric = Category::query()->create(['parent_id' => $boilers->id, 'slug' => 'elektricheskie', 'name' => 'Электрические котлы', 'is_active' => true]);
        $gasBoiler = $this->product($gas, 'Газовый котёл Atem 12', 'atem-gas-12', 1752);
        $electricBoiler = $this->product($electric, 'Электрический котёл Kospel 12', 'kospel-electric-12', 2100);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->withArgs(function (string $prompt, int $tokens) use ($gasBoiler, $electricBoiler): bool {
            $this->assertStringNotContainsString('"id":'.$gasBoiler->id, $prompt);
            $this->assertStringContainsString('"id":'.$electricBoiler->id, $prompt);

            return $tokens === 1400;
        })->andReturn(json_encode(['matches' => [
            ['product_id' => $gasBoiler->id, 'role' => 'optimal', 'reason' => 'Неверный тип топлива'],
            ['product_id' => $electricBoiler->id, 'role' => 'optimal', 'reason' => 'Электрический'],
        ]], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->recommend(
            'Нужен котёл для дома 120 м²',
            'boiler',
            ['fuel' => 'Электричество']
        );

        $this->assertSame([$electricBoiler->id], array_column($result['products'], 'id'));
    }

    public function test_explicit_electric_boiler_intent_cannot_be_overridden_by_ai_category(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn(json_encode([
            'category' => 'gas_boiler',
            'summary' => 'Ошибочно определен газовый котёл',
            'known' => [],
        ], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->start('Нужен электрокотёл 12 кВт');

        $this->assertSame('boiler', $result['category']);
        $this->assertSame('Электричество', $result['known']['fuel']);
        $this->assertNotContains('fuel', array_column($result['questions'], 'key'));
        $this->assertStringNotContainsString('газов', mb_strtolower($result['summary']));
    }

    public function test_every_boiler_fuel_choice_stays_inside_its_catalog_branch(): void
    {
        $boilers = $this->category('kotly', 'Котлы');
        $gas = Category::query()->updateOrCreate(['slug' => 'gazovye'], ['parent_id' => $boilers->id, 'name' => 'Газовые', 'is_active' => true]);
        $electric = Category::query()->updateOrCreate(['slug' => 'elektricheskie'], ['parent_id' => $boilers->id, 'name' => 'Электрические', 'is_active' => true]);
        $solid = Category::query()->updateOrCreate(['slug' => 'tverdotoplivnye'], ['parent_id' => $boilers->id, 'name' => 'Твердотопливные', 'is_active' => true]);
        $pellet = Category::query()->updateOrCreate(['slug' => 'kotly-na-pelletah'], ['parent_id' => $boilers->id, 'name' => 'Пеллетные', 'is_active' => true]);

        $expected = [
            'Газ' => $this->product($gas, 'Газовый котёл', 'gas-boiler', 1000)->id,
            'Электричество' => $this->product($electric, 'Электрический котёл', 'electric-boiler', 1100)->id,
            'Дрова / уголь' => $this->product($solid, 'Дровяной котёл', 'wood-boiler', 1200)->id,
            'Пеллеты' => $this->product($pellet, 'Пеллетный котёл', 'pellet-boiler', 1300)->id,
        ];

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->times(4)->andReturn(false);

        foreach ($expected as $fuel => $productId) {
            $result = app(PublicAiProductSelection::class)->recommend(
                'Нужен котёл для дома',
                'boiler',
                ['fuel' => $fuel]
            );

            $resultIds = array_column($result['products'], 'id');
            $this->assertContains($productId, $resultIds, $fuel);
            foreach ($expected as $otherFuel => $otherProductId) {
                if ($otherFuel !== $fuel) {
                    $this->assertNotContains($otherProductId, $resultIds, $fuel.' не должен смешиваться с '.$otherFuel);
                }
            }
        }
    }

    public function test_category_endpoint_returns_the_real_non_empty_catalog_tree(): void
    {
        $root = $this->category('otoplenie-test', 'Отопление');
        $radiators = Category::query()->create([
            'parent_id' => $root->id,
            'slug' => 'radiatory-test',
            'name' => 'Радиаторы',
            'is_active' => true,
        ]);
        $empty = $this->category('pustaya-kategoriya', 'Пустая категория');
        $this->product($radiators, 'Радиатор тестовый', 'test-radiator', 500);

        $response = $this->getJson(route('ai-selection.categories'));

        $response->assertOk();
        $items = collect($response->json('categories'));
        $this->assertSame(1, $items->firstWhere('id', $root->id)['product_count']);
        $this->assertSame('Отопление › Радиаторы', $items->firstWhere('id', $radiators->id)['path']);
        $this->assertNull($items->firstWhere('id', $empty->id));
    }

    public function test_selected_catalog_category_is_a_hard_boundary_for_recommendations(): void
    {
        $radiators = $this->category('radiatory-selection-test', 'Радиаторы');
        $boilers = $this->category('gazovye', 'Газовые котлы');
        $radiator = $this->product($radiators, 'Радиатор стальной TEST', 'radiator-steel-test', 600);
        $this->product($boilers, 'Газовый котёл TEST', 'gas-boiler-selection-test', 1700);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(false);

        $result = app(PublicAiProductSelection::class)->recommend(
            'Нужен газовый котёл, но ищу в выбранном разделе',
            'gas_boiler',
            [],
            $radiators->id
        );

        $this->assertSame('general', $result['category']);
        $this->assertSame($radiators->id, $result['catalog_category_id']);
        $this->assertSame([$radiator->id], array_column($result['products'], 'id'));
    }

    public function test_selected_electric_boiler_category_sets_fuel_and_skips_the_fuel_question(): void
    {
        $boilers = $this->category('kotly-selection-test', 'Котлы');
        $electric = Category::query()->create([
            'parent_id' => $boilers->id,
            'slug' => 'elektricheskie',
            'name' => 'Электрические котлы',
            'is_active' => true,
        ]);
        $this->product($electric, 'Электрокотёл TEST', 'electric-selection-test', 1200);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(false);

        $result = app(PublicAiProductSelection::class)->start(
            'Нужно подобрать отопление для дома',
            null,
            $electric->id
        );

        $this->assertSame('boiler', $result['category']);
        $this->assertSame($electric->id, $result['catalog_category_id']);
        $this->assertSame('Электричество', $result['known']['fuel']);
        $this->assertNotContains('fuel', array_column($result['questions'], 'key'));
    }

    public function test_public_endpoints_validate_input_and_store_anonymous_result_without_raw_session_id(): void
    {
        $this->postJson(route('ai-selection.start'), ['intent' => 'x'])->assertUnprocessable();

        $service = $this->mock(PublicAiProductSelection::class);
        $service->shouldReceive('recommend')->once()->andReturn([
            'category' => 'pump',
            'summary' => 'Готово',
            'products' => [['id' => 45, 'name' => 'Насос']],
            'ai_used' => true,
        ]);

        $response = $this->postJson(route('ai-selection.recommend'), [
            'intent' => 'Нужен насос для дома',
            'category' => 'pump',
            'answers' => ['budget' => '1000'],
        ]);

        $response->assertOk()->assertJsonPath('products.0.id', 45);
        $run = PublicAiSelectionRun::query()->firstOrFail();
        $this->assertSame('pump', $run->category);
        $this->assertSame([45], $run->result_product_ids);
        $this->assertSame(64, strlen($run->session_hash));
        $this->assertNotSame(session()->getId(), $run->session_hash);
    }

    public function test_full_selection_funnel_updates_one_analytics_run_and_records_product_open(): void
    {
        $category = $this->category('analytics-pumps', 'Насосы для аналитики');
        $product = $this->product($category, 'Насос аналитический', 'analytics-pump', 800);
        $service = $this->mock(PublicAiProductSelection::class);
        $service->shouldReceive('start')->once()->andReturn([
            'category' => 'pump',
            'catalog_category_id' => $category->id,
            'catalog_category_path' => $category->name,
            'known' => ['area' => '120'],
            'questions' => [],
        ]);
        $service->shouldReceive('recommend')->once()->andReturn([
            'category' => 'pump',
            'summary' => 'Подобран насос',
            'products' => [['id' => $product->id, 'name' => $product->name]],
            'ai_used' => true,
        ]);

        $start = $this->postJson(route('ai-selection.start'), [
            'intent' => 'Нужен насос для дома 120 м²',
            'catalog_category_id' => $category->id,
            'page_url' => 'https://kotlov.by/catalog/nasosy',
        ])->assertOk();

        $runId = $start->json('run_id');
        $runToken = $start->json('run_token');
        $this->assertNotNull($runId);
        $this->assertNotNull($runToken);

        $this->postJson(route('ai-selection.recommend'), [
            'run_id' => $runId,
            'run_token' => $runToken,
            'intent' => 'Нужен насос для дома 120 м²',
            'category' => 'pump',
            'catalog_category_id' => $category->id,
            'answers' => ['area' => '120', 'budget' => '1000'],
            'page_url' => 'https://kotlov.by/catalog/nasosy',
        ])->assertOk()->assertJsonPath('run_id', $runId);

        $this->postJson(route('ai-selection.event'), [
            'run_id' => $runId,
            'run_token' => $runToken,
            'event' => 'product_opened',
            'product_id' => $product->id,
            'step' => 'results',
        ])->assertOk();

        $this->assertSame(1, PublicAiSelectionRun::count());
        $run = PublicAiSelectionRun::findOrFail($runId);
        $this->assertSame('product_opened', $run->status);
        $this->assertSame($product->id, $run->selected_product_id);
        $this->assertSame(1, $run->result_count);
        $this->assertSame(['area' => '120', 'budget' => '1000'], $run->answers);
        $this->assertNotNull($run->recommended_at);
        $this->assertNotNull($run->product_opened_at);

        $this->postJson(route('ai-selection.event'), [
            'run_id' => $runId,
            'run_token' => $runToken,
            'event' => 'abandoned',
            'step' => 'results',
        ])->assertOk();
        $this->assertNull($run->fresh()->abandoned_at);
    }

    public function test_lost_visitor_can_leave_phone_and_manager_receives_full_search_context(): void
    {
        $runToken = 'test-run-token-that-is-long-and-random-enough';
        $run = PublicAiSelectionRun::query()->create([
            'session_hash' => hash('sha256', $runToken),
            'intent' => 'Нужен редкий электрокотёл на 30 кВт',
            'category' => 'boiler',
            'answers' => ['fuel' => 'Электричество', 'power' => '30'],
            'result_product_ids' => [],
            'result_count' => 0,
            'status' => 'no_results',
            'current_step' => 'results',
            'page_url' => 'https://kotlov.by/catalog/kotly',
        ]);
        $notifier = $this->mock(ContactRequestNotifier::class);
        $notifier->shouldReceive('notify')->once()->withArgs(fn (ContactRequest $request): bool => $request->source === 'ai_selection' && str_contains($request->message, 'редкий электрокотёл')
        );

        $this->postJson(route('ai-selection.event'), [
            'run_id' => $run->id,
            'run_token' => $runToken,
            'event' => 'lead_prompted',
            'step' => 'results',
        ])->assertOk();

        $this->postJson(route('ai-selection.contact'), [
            'run_id' => $run->id,
            'run_token' => $runToken,
            'name' => 'Иван',
            'phone' => '+375 29 123-45-67',
        ])->assertOk()->assertJsonPath('success', true);

        $run->refresh();
        $this->assertSame('lead_captured', $run->status);
        $this->assertNotNull($run->lead_prompted_at);
        $this->assertNotNull($run->contact_submitted_at);
        $this->assertSame('+375 29 123-45-67', $run->contactRequest->phone);
        $this->assertStringContainsString('fuel: Электричество', $run->contactRequest->message);
        $this->assertStringContainsString('power: 30', $run->contactRequest->message);
    }

    public function test_storefront_layout_contains_global_ai_selector_and_delayed_teaser_script(): void
    {
        $html = view('partials.ai-product-selector')->render();
        $script = file_get_contents(public_path('assets/js/ai-product-selector.js'));
        $styles = file_get_contents(public_path('assets/css/kotlov.css'));

        $this->assertStringContainsString('AI-помощник', $html);
        $this->assertStringContainsString('Нужна помощь с выбором?', $html);
        $this->assertStringContainsString('Умный подбор', $html);
        $this->assertStringContainsString('Написать консультанту', $html);
        $this->assertStringContainsString('icon-Question', $html);
        $this->assertStringContainsString('data-ai-selector-launcher', $html);
        $this->assertMatchesRegularExpression('/data-ai-selector-launcher[^>]*hidden/', $html);
        $this->assertStringContainsString('data-assistance-toggle', $html);
        $this->assertStringContainsString('data-assistance-menu', $html);
        $this->assertStringContainsString('data-consultant-open', $html);
        $this->assertStringNotContainsString('t.me/kotlovby', $html);
        $this->assertStringContainsString('data-ai-selector-open', $html);
        $this->assertStringContainsString('data-categories-url', $html);
        $this->assertStringContainsString('data-ai-category-search', $html);
        $this->assertStringContainsString('data-ai-lead-open', $html);
        $this->assertStringContainsString('data-contact-url', $html);
        $this->assertStringContainsString('product_opened', $script);
        $this->assertStringContainsString('lead_prompted', $script);
        $this->assertStringContainsString('catalog_category_id', $script);
        $this->assertStringContainsString('kotlov_ai_selector_intro_seen', $script);
        $this->assertStringContainsString('introSeen ? 10000 : 5000', $script);
        $this->assertStringContainsString('showLauncher(!introSeen', $script);
        $this->assertStringContainsString("launcher.classList.add('is-explaining')", $script);
        $this->assertStringContainsString("shadow.querySelector('.widget__preview-main')", $script);
        $this->assertStringContainsString('sevenDays', $script);
        $this->assertStringContainsString('cart|checkout|account|wishlist', $script);
        $this->assertMatchesRegularExpression('/\.ai-selector__launcher\s*\{[^}]*right:\s*24px;/s', $styles);
        $this->assertMatchesRegularExpression('/\.ai-selector__teaser,\s*\.ai-selector__assistance-menu\s*\{[^}]*right:\s*24px;/s', $styles);
        $this->assertMatchesRegularExpression('/@media \(max-width: 767px\)[^{]*\{.*?\.ai-selector__launcher\s*\{[^}]*right:\s*12px;/s', $styles);
    }

    private function category(string $slug, string $name): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true]
        );
    }

    private function product(Category $category, string $name, string $slug, float $price, array $extra = []): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => 'AI-'.strtoupper($slug),
            'price' => $price,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'manual_stock_management' => true,
            'stock_qty' => 3,
            'specs' => ['Максимальный напор' => '40 м'],
            ...$extra,
        ]);
    }
}
