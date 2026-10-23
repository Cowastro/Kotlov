<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectAzbukaTeplaManagerContactMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_the_price_list_identifier_with_the_current_manager_phone(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'azbuka-tepla',
            'name' => 'Азбука Тепла',
            'contact' => 'https://docs.google.com/spreadsheets/d/example/edit?gid=2045946143',
            'communication_channels' => [
                ['type' => 'phone', 'name' => 'Старый контакт', 'value' => '2045946143'],
                ['type' => 'email', 'value' => 'info@example.by'],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_23_090000_correct_azbuka_tepla_manager_contact.php');
        $migration->up();

        $supplier->refresh();
        $this->assertSame('Петр: +375 44 557-19-35', $supplier->contact);
        $this->assertSame(['+375 44 557-19-35'], $supplier->contactPhoneNumbers());
        $this->assertSame('info@example.by', $supplier->communication_channels[1]['value']);
    }
}
