<?php

namespace Tests\Feature;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneCExchangeReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SAFE = '/^audit\.onec_exchange\.(source=[0-9]+:[a-z0-9_.-]+:auth=([0-9]{12}|never)|run=[0-9]+(:[a-z0-9_.-]+){4}:[0-9]{1,12}:[0-9]{1,12}:files=[0-9]+:bytes=[0-9]+:items=[0-9]+:orders=[0-9]+:error=[a-z0-9_]+|file=[a-z0-9_.-]+:[a-z0-9_.-]{1,8}:[a-z0-9_.-]+:[0-9]+:[0-9]{12}:received=[01]|log=[0-9]{12}:[a-z0-9_.-]+:[a-z0-9_.-]+)$/';

    public function test_the_report_prints_runs_and_session_files_as_lines_the_workflow_lets_through(): void
    {
        Storage::fake('local');
        config(['onec.exchange.storage_disk' => 'local', 'onec.exchange.storage_path' => 'onec-exchange']);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C', 'driver' => 'commerceml', 'last_authenticated_at' => now()]);
        IntegrationExchangeRun::query()->create(['integration_source_id' => $source->id, 'direction' => 'inbound', 'operation' => 'catalog',
            'status' => 'failed', 'started_at' => now()->subHour(), 'files_count' => 2, 'error_message' => 'Сеанс прерван: 1С начала новый обмен до завершения предыдущего.']);
        IntegrationExchangeRun::query()->create(['integration_source_id' => $source->id, 'direction' => 'inbound', 'operation' => 'catalog',
            'status' => 'succeeded', 'started_at' => now()->subMinutes(30), 'finished_at' => now()]);
        Storage::disk('local')->put('onec-exchange/onec/'.str_repeat('a', 64).'/import0_1.xml', '<xml/>');
        Storage::disk('local')->put('onec-exchange/onec/'.str_repeat('a', 64).'/import0_1.xml.received', 'x');
        Storage::disk('local')->put('onec-exchange/onec/'.str_repeat('a', 64).'/offers0_1.xml', '<xml/>');

        Artisan::call('onec:exchange-report');
        $lines = array_values(array_filter(preg_split('/\R/', trim(Artisan::output()))));

        $this->assertGreaterThanOrEqual(5, count($lines));
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression(self::SAFE, $line);
        }
        $output = implode("\n", $lines);
        $this->assertStringContainsString(':failed:', $output);
        $this->assertStringContainsString('error=superseded_by_new_exchange', $output);
        $this->assertStringContainsString(':import0_1.xml:6:', $output);
        $this->assertStringContainsString(':offers0_1.xml:6:', $output);
        $this->assertStringContainsString('received=1', $output);
        $this->assertStringContainsString('received=0', $output);
    }

    public function test_the_report_finds_exchange_requests_killed_by_the_time_limit_in_the_log(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'log');
        $now = now();
        file_put_contents($log, implode('
', [
            '['.$now->copy()->subDays(9)->format('Y-m-d H:i:s').'] production.ERROR: Maximum execution time of 120 seconds exceeded {"exception":"[object] (Symfony\Component\ErrorHandler\Error\FatalError(code: 0): Maximum execution time at /var/www/app/Services/Integrations/CommerceMlCatalogImporter.php:300)"}',
            '['.$now->format('Y-m-d H:i:s').'] production.ERROR: Maximum execution time of 120 seconds exceeded {"exception":"[object] (Symfony\Component\ErrorHandler\Error\FatalError(code: 0): Maximum execution time of 120 seconds exceeded at /var/www/vendor/laravel/framework/src/Illuminate/Database/Connection.php:400)"}',
            '#0 /var/www/app/Services/Integrations/CommerceMlCatalogImporter.php(310): foo()',
            '['.$now->format('Y-m-d H:i:s').'] production.ERROR: Secret customer text {"exception":"[object] (RuntimeException(code: 0): secret at /var/www/app/Http/Controllers/CartController.php:12)"}',
            '['.$now->format('Y-m-d H:i:s').'] production.ERROR: CommerceML import failed {"exception":"[object] (Illuminate\Database\QueryException(code: 23000): SQLSTATE secret at /var/www/vendor/x.php:1)"}',
            '#0 /var/www/app/Services/Integrations/CommerceMlCatalogImporter.php(77): bar()',
            '',
        ]));

        Artisan::call('onec:exchange-report', ['--log' => $log]);
        $lines = array_values(array_filter(preg_split('/\R/', trim(Artisan::output())), fn (string $line): bool => str_contains($line, '.log=')));
        @unlink($log);

        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression(self::SAFE, $line);
        }
        $this->assertStringContainsString(':time_limit:services_integrations_commercemlcatalogimporter.php_310', $lines[0]);
        $this->assertStringContainsString(':illuminate.database.queryexception:services_integrations_commercemlcatalogimporter.php_77', $lines[1]);
        $this->assertStringNotContainsString('secret', implode('
', $lines));
    }
}
