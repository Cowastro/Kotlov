<?php

namespace Tests\Feature;

use App\Filament\Resources\SupplierSyncRuns\SupplierSyncRunResource;
use App\Models\SupplierSyncRun;
use App\Models\User;
use App\Services\SupplierSyncJournalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Tests\TestCase;

class SupplierSyncSourceArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_excel_can_be_attached_and_seen_in_the_unified_journal(): void
    {
        Storage::fake('local');
        $path = storage_path('framework/testing/supplier-source.xlsx');
        file_put_contents($path, 'test xlsx payload');

        $run = SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-svah-group',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->artisan('supplier:archive-sync-source', [
            'sync-command' => 'supplier:sync-svah-group',
            'file' => $path,
            '--name' => '20261014_105619.xlsx',
        ])
            ->expectsOutputToContain('metric.sync_source.attached=1')
            ->assertSuccessful();

        $run->refresh();
        $this->assertSame('20261014_105619.xlsx', $run->source_file_name);
        $this->assertSame('xlsx', $run->source_file_type);
        $this->assertTrue($run->hasArchivedSource());
        Storage::disk('local')->assertExists($run->source_file_path);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)
            ->get(SupplierSyncRunResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Исходный файл')
            ->assertSeeText('20261014_105619.xlsx')
            ->assertSeeText('Скачать XLSX');
    }

    public function test_journal_recorder_automatically_archives_a_file_argument(): void
    {
        Storage::fake('local');
        $path = storage_path('framework/testing/automatic-source.xlsx');
        file_put_contents($path, 'automatic source payload');
        $input = new ArrayInput(
            ['source' => $path],
            new InputDefinition([new InputArgument('source', InputArgument::REQUIRED)]),
        );

        $recorder = app(SupplierSyncJournalRecorder::class);
        $recorder->start('supplier:sync-test-source', $input);
        $recorder->finish('supplier:sync-test-source', 0);

        $run = SupplierSyncRun::query()->where('command', 'supplier:sync-test-source')->firstOrFail();
        $this->assertSame('automatic-source.xlsx', $run->source_file_name);
        $this->assertSame('xlsx', $run->source_file_type);
        $this->assertNotEmpty($run->source_file_sha256);
        $this->assertTrue($run->hasArchivedSource());
    }
}
