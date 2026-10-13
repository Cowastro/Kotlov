<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCrmProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_and_last_name_keep_legacy_name_in_sync(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Наталья',
            'last_name' => 'Жнерович',
            'is_crm_user' => true,
            'department' => 'Отдел продаж',
            'job_title' => 'Менеджер',
        ]);

        $this->assertSame('Наталья Жнерович', $user->fresh()->name);
        $this->assertTrue($user->fresh()->is_crm_user);
        $this->assertSame('Отдел продаж', $user->fresh()->department);
    }
}
