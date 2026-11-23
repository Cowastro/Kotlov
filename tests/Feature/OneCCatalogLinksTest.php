<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCItems\Actions\ManageOneCLinksAction;
use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use App\Services\Integrations\OneCBridge\OneCItemLinks;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OneCCatalogLinksTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a0000000-0000-0000-0000-000000000001';

    private const RED = 'b0000000-0000-0000-0000-000000000001';

    private const BLUE = 'b0000000-0000-0000-0000-000000000002';

    private function setupLinks(): array
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user);
        $source = IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1С']);
        $item = OneCItem::create(['ref_key' => self::KEY, 'code' => 'УТ-TEST', 'description' => 'Проходник', 'stock_on_hand' => 12, 'stock_reserved' => 2]);

        return [$user, $source, $item, app(OneCItemLinks::class)];
    }

    private function card(string $suffix): Product
    {
        $category = Category::firstOrCreate(['slug' => 'link-test'], ['name' => 'Test', 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => 'Проходник '.$suffix, 'sku' => 'CARD-'.$suffix, 'slug' => 'link-test-'.$suffix, 'price' => 123, 'is_active' => true, 'is_archived' => false]);
    }

    private function row(IntegrationSource $source, string $id, ?Product $card = null): IntegrationProduct
    {
        return IntegrationProduct::create(['integration_source_id' => $source->id, 'external_id' => $id, 'name' => 'Проходник '.$id,
            'product_id' => $card?->id, 'price' => 75, 'stock_quantity' => 9, 'match_status' => $card ? 'matched' : 'unmatched']);
    }

    public function test_manager_links_existing_card_from_the_table_without_new_products_or_duplicate_exchange_rows(): void
    {
        [, , $item, $links] = $this->setupLinks();
        $card = $this->card('red');
        $count = Product::count();
        $page = Livewire::test(ListOneCItems::class)->mountTableAction('manage_links', $item);
        $data = $links->formData($item);
        $data['links'][0]['product_id'] = $card->id;
        $page->setTableActionData($data)->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertSame($card->id, $item->fresh()->product_id);
        $this->assertSame($card->id, IntegrationProduct::where('external_id', self::KEY)->sole()->product_id);
        $links->save($item, $links->formData($item), auth()->user());
        $this->assertSame(1, IntegrationProduct::where('external_id', self::KEY)->count());
        $this->assertSame($count, Product::count());
        $this->assertEquals(123, $card->fresh()->price);
        $this->assertEquals(12, $item->fresh()->stock_on_hand);
        $this->assertDatabaseHas('onec_item_link_changes', ['external_id' => self::KEY, 'new_product_id' => $card->id, 'user_id' => auth()->id()]);
    }

    public function test_characteristics_keep_full_identity_and_separate_cards_after_refresh(): void
    {
        [$user, $source, $item, $links] = $this->setupLinks();
        $red = $this->card('red');
        $blue = $this->card('blue');
        $first = $this->row($source, self::KEY.'#'.self::RED);
        $second = $this->row($source, self::KEY.'#'.self::BLUE);
        $data = $links->formData($item);
        $data['links'][0]['product_id'] = $red->id;
        $data['links'][1]['product_id'] = $blue->id;
        $links->save($item, $data, $user);
        app(OneCNomenclature::class)->refresh();
        $this->assertSame($red->id, $first->fresh()->product_id);
        $this->assertSame($blue->id, $second->fresh()->product_id);
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame(2, $item->fresh()->site_link_count);
        $this->assertSame(2, $item->fresh()->characteristic_count);
        $this->assertFalse($item->fresh()->link_conflict);
        $this->assertEquals(75, $first->fresh()->price);
        $this->assertEquals(9, $first->fresh()->stock_quantity);
    }

    public function test_editing_exchange_link_updates_nomenclature_and_audits_old_and_new_card_immediately(): void
    {
        [, $source, $item] = $this->setupLinks();
        $first = $this->card('first');
        $second = $this->card('second');
        $row = $this->row($source, self::KEY, $first);
        $row->update(['product_id' => $second->id]);
        $this->assertSame($second->id, $item->fresh()->product_id);
        $this->assertDatabaseHas('onec_item_link_changes', ['integration_product_id' => $row->id, 'old_product_id' => $first->id, 'new_product_id' => $second->id]);
    }

    public function test_duplicate_variant_card_is_rejected_and_entire_save_rolls_back(): void
    {
        [$user, $source, $item, $links] = $this->setupLinks();
        $card = $this->card('red');
        $one = $this->row($source, self::KEY.'#'.self::RED);
        $two = $this->row($source, self::KEY.'#'.self::BLUE);
        $data = $links->formData($item);
        foreach ($data['links'] as &$entry) {
            $entry['product_id'] = $card->id;
        }
        try {
            $links->save($item, $data, $user);
            $this->fail('Duplicate accepted');
        } catch (ValidationException) {
        }
        $this->assertNull($one->fresh()->product_id);
        $this->assertNull($two->fresh()->product_id);
        $this->assertSame(0, DB::table('onec_item_link_changes')->count());
    }

    public function test_stale_form_and_foreign_identity_cannot_overwrite_current_links(): void
    {
        [$user, $source, $item, $links] = $this->setupLinks();
        $card = $this->card('red');
        $row = $this->row($source, self::KEY);
        $old = $links->formData($item);
        $row->update(['product_id' => $card->id, 'match_status' => 'matched']);
        try {
            $links->save($item, $old, $user);
            $this->fail('Stale form accepted');
        } catch (ValidationException) {
        }
        $tampered = $links->formData($item);
        $tampered['links'][0]['external_id'] = self::KEY.'#'.self::RED;
        try {
            $links->save($item, $tampered, $user);
            $this->fail('Foreign identity accepted');
        } catch (ValidationException) {
        }
        $this->assertSame($card->id, $row->fresh()->product_id);
    }

    public function test_unlink_keeps_exchange_row_and_does_not_restore_after_refresh(): void
    {
        [$user, $source, $item, $links] = $this->setupLinks();
        $row = $this->row($source, self::KEY, $this->card('red'));
        $data = $links->formData($item);
        $data['links'][0]['product_id'] = null;
        $links->save($item, $data, $user);
        app(OneCNomenclature::class)->refresh();
        $this->assertNull($row->fresh()->product_id);
        $this->assertSame('ignored', $row->fresh()->match_status);
        $this->assertNull($item->fresh()->product_id);
        $this->assertEquals(75, $row->fresh()->price);
        $this->assertEquals(9, $row->fresh()->stock_quantity);
    }

    public function test_zero_characteristic_aliases_are_one_identity_and_conflicts_are_explicit(): void
    {
        [$user, $source, $item, $links] = $this->setupLinks();
        $first = $this->card('red');
        $second = $this->card('blue');
        $bare = $this->row($source, self::KEY, $first);
        $alias = $this->row($source, self::KEY.'#00000000-0000-0000-0000-000000000000', $second);
        $this->assertTrue($item->fresh()->link_conflict);
        $this->assertNull($item->fresh()->product_id);
        $data = $links->formData($item);
        $this->assertCount(1, $data['links']);
        $data['links'][0]['product_id'] = $second->id;
        $links->save($item, $data, $user);
        $this->assertSame($second->id, $bare->fresh()->product_id);
        $this->assertSame($second->id, $alias->fresh()->product_id);
        $this->assertFalse($item->fresh()->link_conflict);
    }

    public function test_customer_cannot_link_and_groups_are_not_editable(): void
    {
        [, , $item, $links] = $this->setupLinks();
        $customer = User::factory()->create(['role' => 'client']);
        try {
            $links->save($item, $links->formData($item), $customer);
            $this->fail('Customer allowed');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $item->update(['is_folder' => true]);
        try {
            $links->save($item, $links->formData($item), auth()->user());
            $this->fail('Group linked');
        } catch (ValidationException) {
        }
        $this->assertSame(0, IntegrationProduct::where('external_id', self::KEY)->count());
    }

    public function test_creator_does_not_take_a_characteristic_for_the_whole_item(): void
    {
        [, $source] = $this->setupLinks();
        $red = $this->card('red');
        $this->row($source, self::KEY.'#'.self::RED, $red);
        $message = app(OneCItemCreator::class)->link($this->card('blue'), self::KEY, 'CODE', 'Master Flash', false);
        $this->assertStringContainsString('характеристики', $message);
        $this->assertSame(0, IntegrationProduct::where('external_id', self::KEY)->count());
    }

    public function test_refresh_command_repairs_display_without_changing_existing_links(): void
    {
        [, $source, $item] = $this->setupLinks();
        $red = $this->card('red');
        $row = $this->row($source, self::KEY.'#'.self::RED, $red);
        DB::table('onec_items')->where('id', $item->id)->update(['product_id' => $red->id, 'characteristic_count' => 0]);
        $this->artisan('onec:refresh-links')->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame(1, $item->fresh()->characteristic_count);
        $this->assertSame($red->id, $row->fresh()->product_id);
    }

    public function test_duplicate_assignment_from_another_screen_is_marked_as_conflict(): void
    {
        [, $source, $item] = $this->setupLinks();
        $card = $this->card('red');
        $this->row($source, self::KEY.'#'.self::RED, $card);
        $this->row($source, self::KEY.'#'.self::BLUE, $card);
        $this->assertTrue($item->fresh()->link_conflict);
        $this->assertNull($item->fresh()->product_id);
    }

    public function test_search_finds_existing_card_by_our_article(): void
    {
        $this->setupLinks();
        $card = $this->card('uniquear ticle');
        $this->assertArrayHasKey($card->id, ManageOneCLinksAction::search($card->sku));
    }
}
