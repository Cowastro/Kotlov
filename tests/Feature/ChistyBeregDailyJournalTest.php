<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierSyncRun;
use App\Services\Suppliers\ChistyBeregPriceList;
use App\Services\SupplierSyncJournalRecorder;
use App\Services\SupplierSyncRunTextExporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\TestCase;

class ChistyBeregDailyJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_new_file_is_a_skipped_check_without_global_baseline_scan(): void
    {
        Storage::fake('local');
        Supplier::firstOrCreate(['code' => 'chisty_bereg'], ['name' => 'Чистый берег']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $journal = app(SupplierSyncJournalRecorder::class);
        $journal->start('supplier:sync-chisty-bereg', new ArrayInput([]));
        $this->artisan('supplier:sync-chisty-bereg', ['--if-new' => true, '--apply' => true])->assertSuccessful();
        $journal->finish('supplier:sync-chisty-bereg', 0);
        $run = SupplierSyncRun::where('command', 'supplier:sync-chisty-bereg')->latest('id')->firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('Прайс ещё не загружен', $run->context['skip_reason']);
        $this->assertSame('Чистый берег', $run->supplierLabel());
        $this->assertSame('Загруженный прайс (XLS)', $run->sourceLabel());
        $this->assertSame(0, $run->changes_count);
        $this->assertFalse(collect($queries)->contains(fn ($sql) => preg_match('/(?:from|into|update)\s+["`]?supplier_sync_states(?:["`]|\s|$)/i', $sql) === 1));
        $text = app(SupplierSyncRunTextExporter::class)->render($run);
        $this->assertStringContainsString('Импорт не выполнялся', $text);
        $this->assertStringNotContainsString('API / сайт', $text);
    }

    public function test_actual_import_archives_default_uploaded_price_and_records_supplier_even_without_changes(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(ChistyBeregPriceList::FILE, 'test uploaded source');
        Supplier::firstOrCreate(['code' => 'chisty_bereg'], ['name' => 'Чистый берег']);
        $journal = app(SupplierSyncJournalRecorder::class);
        $journal->start('supplier:sync-chisty-bereg', new ArrayInput([]));
        $journal->finish('supplier:sync-chisty-bereg', 0);
        $run = SupplierSyncRun::where('command', 'supplier:sync-chisty-bereg')->latest('id')->firstOrFail();
        $this->assertSame('success', $run->status);
        $this->assertSame('Чистый берег', $run->supplier_names);
        $this->assertSame('latest.xls', $run->source_file_name);
        $this->assertTrue($run->hasArchivedSource());
    }

    public function test_legacy_rows_have_truthful_supplier_and_source_labels_without_rewriting_history(): void
    {
        $run = new SupplierSyncRun(['command' => 'supplier:sync-chisty-bereg', 'status' => 'success']);
        $this->assertSame('Чистый берег', $run->supplierLabel());
        $this->assertSame('Загруженный прайс (XLS)', $run->sourceLabel());
        $this->assertSame('success', $run->status);
        $this->assertSame('БЛК7', (new SupplierSyncRun(['command' => 'supplier:sync-blk7']))->supplierLabel());
    }

    public function test_chisty_bereg_price_and_enrichment_are_daily_in_minsk_timezone(): void
    {
        $this->app->make(Kernel::class)->bootstrap();
        $events = collect($this->app->make(Schedule::class)->events());
        foreach ([
            'supplier:sync-chisty-bereg' => '0 9 * * *',
            'supplier:chisty-bereg-create --limit=120' => '20 9 * * *',
            'supplier:chisty-bereg-create --refill-specs' => '40 9 * * *',
            'supplier:chisty-bereg-photos --limit=12' => '0 10 * * *',
            'supplier:chisty-bereg-market-prices --limit=10' => '20 10 * * *',
        ] as $command => $expression) {
            $matching = $events->filter(fn ($event) => str_contains((string) $event->command, $command));
            $this->assertCount(1, $matching, $command);
            $event = $matching->first();
            $this->assertSame($expression, $event->expression);
            $this->assertSame('Europe/Minsk', $event->timezone);
        }
    }
}
