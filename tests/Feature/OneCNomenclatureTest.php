<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OneCNomenclatureTest extends TestCase
{
    use RefreshDatabase;

    private const HEAT = 'f0000000-0000-0000-0000-000000000001';

    private const CONVECTORS = 'f0000000-0000-0000-0000-000000000002';

    private const BOILERS = 'f0000000-0000-0000-0000-000000000003';

    private const HMM = 'a0000000-0000-0000-0000-000000001500';

    private function read(User $admin, string $key, array $items): OneCAgentTask
    {
        $task = app(OneCNomenclature::class)->enqueue($admin);
        $this->withHeaders(['Authorization' => 'Bearer '.$key, 'X-Kotlov-Agent' => '6'])->get('/1c/agent/tasks/next')->assertOk();
        foreach (array_keys($task->payload['queries']) as $i => $name) {
            $this->call('POST', '/1c/agent/tasks/'.$task->id.'/part?name='.$name.'&code=200', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$key,
                'HTTP_X_KOTLOV_AGENT' => '6', 'CONTENT_TYPE' => 'application/octet-stream'], json_encode(['value' => $i === 0 ? $items : []], JSON_UNESCAPED_UNICODE))->assertOk();
        }
        $this->withHeaders(['Authorization' => 'Bearer '.$key, 'X-Kotlov-Agent' => '6'])->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();

        return $task->fresh();
    }

    private function item(string $key, string $name, ?string $parent, bool $folder = false, string $code = ''): array
    {
        return ['Ref_Key' => $key, 'Code' => $code, 'Description' => $name, 'Артикул' => '', 'Parent_Key' => $parent ?? '00000000-0000-0000-0000-000000000000',
            'IsFolder' => $folder, 'DeletionMark' => false, 'ВидНоменклатуры_Key' => '00000000-0000-0000-0000-000000000000', 'ЕдиницаИзмерения_Key' => '00000000-0000-0000-0000-000000000000'];
    }

    public function test_the_whole_1c_nomenclature_shows_with_groups_links_and_duplicates(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        [, $key] = OneCAgentToken::issue('test', $admin->id);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'obogrev'], ['name' => 'Обогрев', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Ballu HMM-1500', 'slug' => 'hmm', 'price' => 150]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => self::HMM.'#00000000-0000-0000-0000-000000000000',
            'name' => 'Конвектор электрический Ballu BEC/HMM-1500', 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched', 'match_method' => 'onec_bridge']);
        (new OneCItem)->forceFill(['ref_key' => 'deadbeef-0000-0000-0000-000000000001', 'description' => 'Удалён из 1С', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $task = $this->read($admin, $key, [
            $this->item(self::HEAT, 'Отопление', null, true),
            $this->item(self::CONVECTORS, 'Конвекторы', self::HEAT, true),
            $this->item(self::BOILERS, 'Котлы', self::HEAT, true),
            $this->item(self::HMM, 'Конвектор электрический Ballu BEC/HMM-1500', self::CONVECTORS, false, 'УТ-00007481'),
            $this->item('a0000000-0000-0000-0000-000000000002', 'Котел Arderia E6', self::BOILERS, false, 'УТ-00008024'),
            $this->item('a0000000-0000-0000-0000-000000000003', 'Котел Arderia E6', self::BOILERS, false, 'УТ-00008025'),
        ]);
        $this->assertStringStartsWith('Номенклатура 1С: 6 (товары и группы), удалено из 1С: 1', $task->summary);
        $this->assertSame(20, count($task->payload['queries']));
        $this->assertStringContainsString('$skip=2500', $task->payload['queries']['items_2']);

        $hmm = OneCItem::query()->where('ref_key', self::HMM)->sole();
        $this->assertSame('Отопление / Конвекторы', $hmm->path);
        $this->assertSame($card->id, $hmm->product_id);
        $this->assertTrue($hmm->created_from_site);
        $this->assertSame(2, OneCItem::query()->where('code', 'УТ-00008024')->value('same_name_count'));

        $this->actingAs($admin);
        $ids = fn (array $filters = []): array => tap(Livewire::test(ListOneCItems::class), function ($page) use ($filters): void {
            foreach ($filters as $name => $value) {
                $page->filterTable($name, $value);
            }
        })->instance()->getTableRecords()->pluck('id')->all();
        Livewire::test(ListOneCItems::class)->assertOk()->assertSee('привязано к сайту');
        $this->assertContains($hmm->id, $ids());
        $this->assertContains($hmm->id, $ids(['group' => self::HEAT]));
        $this->assertNotContains($hmm->id, $ids(['group' => self::BOILERS]));
        $this->assertCount(2, $ids(['duplicates' => true]));
        $this->assertCount(6, $ids(['is_folder' => false, 'deletion_mark' => false]));

        // A new item goes to the group chosen (by default the sample's) and shows at once.
        $this->assertSame(self::CONVECTORS, app(OneCItemCreator::class)->templateGroup(self::HMM));
        $this->assertSame(['f0000000-0000-0000-0000-000000000001', self::CONVECTORS, self::BOILERS], app(OneCNomenclature::class)->folderWithChildren(self::HEAT));
        app(OneCNomenclature::class)->remember('a0000000-0000-0000-0000-000000002000', 'УТ-00009765', 'Конвектор электрический Ballu BEC/EMT-2000',
            $card->id, self::CONVECTORS, 'KOTLOV-000878', true);
        $new = OneCItem::query()->where('code', 'УТ-00009765')->sole();
        $this->assertSame('Отопление / Конвекторы', $new->path);
        $this->assertTrue($new->created_from_site);
        $this->assertSame(self::CONVECTORS, app(OneCItemCreator::class)->body(['Parent_Key' => self::BOILERS],
            ['name' => 'x', 'full_name' => 'x', 'article' => '', 'group' => self::CONVECTORS])['Parent_Key']);
    }
}
