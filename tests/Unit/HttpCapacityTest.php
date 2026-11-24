<?php

namespace Tests\Unit;

use App\Support\HttpCapacity;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/app/Support/HttpCapacity.php';

class HttpCapacityTest extends TestCase
{
    private string $directory;

    private array $leases = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/kotlov-capacity-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach ($this->leases as $lease) {
            HttpCapacity::release($lease);
        }
        foreach (glob($this->directory.'/*.lock') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function test_public_saturation_leaves_separate_admin_checkout_and_integration_capacity(): void
    {
        $guard = new HttpCapacity($this->directory);
        for ($i = 0; $i < 12; $i++) {
            $lease = $guard->acquire(['REQUEST_URI' => '/catalog']);
            $this->assertIsResource($lease);
            $this->leases[] = $lease;
        }
        $this->assertNull($guard->acquire(['REQUEST_URI' => '/search?q=pump']));
        // a full public pool never turns the 1C agent away
        foreach (['/admin/suppliers', '/admin/crm-mail-threads/1228', '/cart', '/b2b/catalog', '/1c/exchange', '/webpay/notify', '/1c/agent/tasks/next'] as $path) {
            $lease = $guard->acquire(['REQUEST_URI' => $path]);
            $this->assertIsResource($lease);
            HttpCapacity::release($lease);
        }
        HttpCapacity::release(array_pop($this->leases));
        $lease = $guard->acquire(['REQUEST_URI' => '/catalog']);
        $this->assertIsResource($lease);
        $this->leases[] = $lease;
    }

    public function test_all_pools_have_a_combined_ceiling_of_23_and_locks_release(): void
    {
        $guard = new HttpCapacity($this->directory);
        foreach (['/catalog' => 12, '/admin' => 4, '/livewire-f410ccd1/update' => 4, '/1c/exchange' => 2, '/1c/agent/tasks/7/part' => 1] as $path => $limit) {
            for ($i = 0; $i < $limit; $i++) {
                $lease = $guard->acquire(['REQUEST_URI' => $path]);
                $this->assertIsResource($lease);
                $this->leases[] = $lease;
            }
            $this->assertNull($guard->acquire(['REQUEST_URI' => $path]));
        }
        $this->assertCount(23, $this->leases);
    }

    public function test_ajax_rejection_is_json_and_does_not_suggest_automatic_post_retry(): void
    {
        $guard = new HttpCapacity($this->directory);
        $response = $guard->busyResponse(['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame('application/json; charset=utf-8', $response['type']);
        $this->assertArrayHasKey('message', json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('refresh', $guard->busyResponse([])['body']);
        $this->assertSame('admin', $guard->pool(['HTTP_HOST' => 'admin.kotlov.by:443', 'REQUEST_URI' => '/livewire/update']));
        $this->assertSame('public', $guard->pool(['REQUEST_URI' => '/cartoon']));
        $this->assertSame('agent', $guard->pool(['REQUEST_URI' => '/1c/agent/tasks/171/part?name=orders_1&code=200']));
        $this->assertSame('integration', $guard->pool(['REQUEST_URI' => '/1c/exchange?type=catalog']));
    }

    public function test_separate_php_process_observes_the_same_capacity_limit(): void
    {
        $guard = new HttpCapacity($this->directory);
        for ($i = 0; $i < 12; $i++) {
            $this->leases[] = $guard->acquire(['REQUEST_URI' => '/catalog']);
        }
        $code = 'require '.var_export(dirname(__DIR__, 2).'/app/Support/HttpCapacity.php', true).';'
            .'$g=new \\App\\Support\\HttpCapacity('.var_export($this->directory, true).');'
            .'echo $g->acquire(["REQUEST_URI"=>"/catalog"])===null?"full":"admitted";';
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        $this->assertSame('full', $output);
    }
}
