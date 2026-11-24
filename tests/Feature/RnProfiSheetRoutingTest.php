<?php

namespace Tests\Feature;

use App\Console\Commands\SyncRnProfiChunksCommand;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class RnProfiSheetRoutingTest extends TestCase
{
    #[DataProvider('tabs')]
    public function test_tab_identity_uses_the_verified_gid_even_for_old_excel_names(string $requested, string $canonical, string $gid): void
    {
        Artisan::shouldReceive('call')->once()->with('supplier:sync-rn-profi', Mockery::on(fn ($args) =>
            $args['--google-csv-gid'] === $gid && $args['--google-csv-sheet'] === $canonical
            && $args['--only-linked'] === true && $args['--dry-run'] === true))->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('');
        $command = new SyncRnProfiChunksCommand;
        $command->setLaravel($this->app);
        $this->assertSame(0, $command->run(new ArrayInput(['--sheet' => [$requested], '--only-linked' => true, '--dry-run' => true, '--sleep' => 0], $command->getDefinition()), new BufferedOutput));
    }

    public static function tabs(): array
    {
        return [
            ['3. Дизайн-радиаторы SHIFT, INSI', '3. Дизайн-радиаторы SHIFT, INSIGNIA', '1846942646'],
            ['15. Радиаторы ИТАЛИЯ NOVA FLORI', '15. Радиаторы ИТАЛИЯ NOVA FLORIDA', '662323938'],
            ['9. VARMEGA Радиаторная арматура', '9. VARMEGA Радиаторная арматура', '561682745'],
        ];
    }

    public function test_unknown_tab_cannot_silently_download_the_contents_sheet(): void
    {
        Artisan::shouldReceive('call')->never();
        $command = new SyncRnProfiChunksCommand;
        $command->setLaravel($this->app);
        $this->assertSame(1, $command->run(new ArrayInput(['--sheet' => ['Misspelled Varmega'], '--dry-run' => true], $command->getDefinition()), new BufferedOutput));
    }
}
