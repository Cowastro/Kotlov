<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\PartnerProgramLevel;
use App\Models\User;
use App\Services\PartnerLevelEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerLevelEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_paid_non_cancelled_orders_in_period_count_and_level_does_not_drop(): void
    {
        $user = User::factory()->create();
        $client = B2bClient::create([
            'user_id' => $user->id, 'status' => 'approved',
            'partner_type' => 'wholesale', 'company_name' => 'Тест',
        ]);
        $base = PartnerProgramLevel::create([
            'name' => 'Базовый', 'rank' => 1, 'rules' => [],
            'benefits' => ['wholesale_prices' => 'Каталог', 'site_leads' => 'Заявки по региону'],
        ]);
        $next = PartnerProgramLevel::create([
            'name' => 'Следующий', 'rank' => 2, 'benefits' => ['volume_bonus' => 'Условия'],
            'rules' => [['metric' => 'revenue', 'months' => 1, 'target' => 100]],
        ]);

        foreach ([
            ['eligible', 'paid', 'completed', now(), 99.99],
            ['unpaid', 'pending', 'completed', now(), 200],
            ['cancelled', 'paid', 'cancelled', now(), 200],
            ['old', 'paid', 'completed', now()->subMonths(2), 200],
        ] as [$number, $payment, $status, $date, $total]) {
            DB::table('b2b_orders')->insert([
                'b2b_client_id' => $client->id, 'number' => $number, 'payment_status' => $payment,
                'status' => $status, 'created_at' => $date, 'total' => $total,
                'contact_name' => 'Тест', 'contact_phone' => '+375291234567', 'company_name' => 'Тест',
            ]);
        }

        $evaluator = app(PartnerLevelEvaluator::class);
        $evaluator->evaluate($client);
        $this->assertEquals($base->id, $client->refresh()->partner_program_level_id);
        $this->assertEquals(0.01, $evaluator->progress($client)['rules']->first()['remaining']);
        $this->view('partials.b2b.partner-level', ['client' => $client, 'can' => fn () => true])
            ->assertSeeText('Ваш уровень — Базовый')->assertSeeText('Осталось 0,01')
            ->assertSee('aria-valuenow="99"', false)
            ->assertSeeText('Оптовые цены')->assertSeeText('Бонус за объём')->assertDontSeeText('Заявки по региону');
        $client->partner_type = 'installer';
        $this->view('partials.b2b.partner-level', ['client' => $client, 'can' => fn () => true])
            ->assertSeeText('Заявки по региону');

        DB::table('b2b_orders')->where('number', 'eligible')->update(['total' => 100]);
        $evaluator->evaluate($client);
        $this->assertEquals($next->id, $client->refresh()->partner_program_level_id);

        DB::table('b2b_orders')->where('number', 'eligible')->update(['status' => 'cancelled']);
        $evaluator->evaluate($client);
        $this->assertEquals($next->id, $client->refresh()->partner_program_level_id);
    }
}
