<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmImportConflicts\CrmImportConflictResource;
use App\Filament\Resources\CrmImportRuns\CrmImportRunResource;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmExternalIdentity;
use App\Models\CrmImportConflict;
use App\Models\CrmImportRun;
use App\Models\Supplier;
use App\Models\TelephonyCall;
use App\Models\User;
use App\Services\Crm\Imports\OneCCrmImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCCrmImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_trade_contact_report_import_is_safe_idempotent_and_matches_old_calls(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'test-supplier',
            'name' => 'Тестовый партнёр',
        ]);
        $path = $this->tradeContactReport([
            1 => 'ООО Тестовый партнёр',
            2 => 'Тестовый партнёр',
            3 => 'Минск',
            4 => 'Иванова Анна',
            5 => '12.10.2026',
            6 => 'A/X',
            7 => 'Юридический адрес',
            8 => 'Почтовый адрес',
            9 => 'Фактический адрес',
            10 => '+375 (29) 123-45-67',
            11 => 'office@example.by',
        ]);

        TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'before-one-c-import',
            'direction' => 'inbound',
            'customer_phone' => '375291234567',
            'status' => 'answered',
            'handling_status' => 'handled',
        ]);

        $importer = app(OneCCrmImporter::class);
        $preview = $importer->import('trade_contacts', $path, true);

        $this->assertSame(1, $preview['rows']);
        $this->assertSame(1, $preview['created']);
        $this->assertSame(1, $preview['suppliers_linked']);
        $this->assertDatabaseCount('crm_counterparties', 0);
        $this->assertDatabaseCount('crm_import_runs', 0);
        $this->assertNull($supplier->fresh()->crm_counterparty_id);

        $summary = $importer->import('trade_contacts', $path, false);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(2, $summary['contact_points']);
        $this->assertSame(1, $summary['calls_matched']);
        $this->assertSame(1, $summary['suppliers_linked']);
        $this->assertDatabaseCount('crm_counterparties', 1);
        $this->assertDatabaseCount('crm_contact_points', 2);
        $this->assertDatabaseCount('crm_external_identities', 1);
        $this->assertDatabaseCount('crm_import_runs', 1);

        $counterparty = CrmCounterparty::query()->firstOrFail();
        $this->assertSame($counterparty->id, $supplier->fresh()->crm_counterparty_id);
        $this->assertSame('supplier', $counterparty->fresh()->classification);
        $this->assertSame('ООО Тестовый партнёр', $counterparty->name);
        $this->assertSame('Минск', $counterparty->business_region);
        $this->assertSame('Иванова Анна', $counterparty->one_c_manager_name);
        $this->assertSame('375291234567', CrmContactPoint::query()->where('type', 'phone')->value('normalized_value'));
        $this->assertSame($counterparty->id, TelephonyCall::query()->value('crm_counterparty_id'));
        $this->assertNull(TelephonyCall::query()->value('crm_contact_id'));

        $second = $importer->import('trade_contacts', $path, false);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['matched']);
        $this->assertDatabaseCount('crm_counterparties', 1);
        $this->assertDatabaseCount('crm_contact_points', 2);
        $this->assertDatabaseCount('crm_external_identities', 1);
        $this->assertDatabaseCount('crm_import_runs', 2);

        $identity = CrmExternalIdentity::query()->firstOrFail();
        $this->assertSame($counterparty->id, $identity->counterparty_id);
        $this->assertSame('completed', CrmImportRun::query()->latest('id')->value('status'));

        @unlink($path);
    }

    public function test_admin_can_open_one_c_import_control_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        CrmImportConflict::query()->create([
            'source' => '1c_trade',
            'import_type' => 'trade_contacts',
            'external_id' => 'test-conflict',
            'external_name' => 'Неоднозначный партнёр',
            'reason' => 'Найдено несколько возможных карточек CRM',
            'candidate_ids' => [10, 20],
            'payload' => ['name' => 'Неоднозначный партнёр'],
        ]);

        $this->actingAs($admin)
            ->get(CrmImportRunResource::getUrl('index', panel: 'admin'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(CrmImportConflictResource::getUrl('index', panel: 'admin'))
            ->assertOk();
    }

    /** @param array<int, string> $columns */
    private function tradeContactReport(array $columns): string
    {
        $externalId = '8f000001-test-partner';
        $body = '';

        for ($column = 1; $column <= 11; $column++) {
            if (! array_key_exists($column, $columns)) {
                $body .= "{20,12,{\"#\",{$externalId}},{1,0},0},{$column},\n";

                continue;
            }

            $value = str_replace('"', '""', $columns[$column]);
            $body .= "{20,12,{\"#\",{$externalId}},{1,1,{\"#\",\"{$value}\"}},0},{$column},\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'crm-mxl-');
        file_put_contents($path, 'MOXCEL123456789'.$body);

        return $path;
    }
}
