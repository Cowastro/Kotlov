<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\PartnerProgramLevel;
use App\Models\User;
use App\Services\PartnerLevelEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerLevelEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_price_application_only_raises_the_price_level_rank(): void
    {
        Mail::fake();
        $prices = \App\Models\B2bPriceLevel::query()->orderBy('rank')->get();
        [$low, $target, $high] = $prices->take(3)->all();
        PartnerProgramLevel::create([
            'name' => 'Партнёрский', 'rank' => 1, 'benefits' => [], 'rules' => [],
            'b2b_price_level_id' => $target->id, 'auto_apply_price_level' => true,
        ]);

        foreach ([[$low, $target], [$high, $high]] as [$original, $expected]) {
            $client = B2bClient::create([
                'user_id' => User::factory()->create()->id, 'status' => 'approved',
                'partner_type' => 'wholesale', 'company_name' => 'Тест',
                'b2b_price_level_id' => $original->id,
            ]);
            app(PartnerLevelEvaluator::class)->evaluate($client);
            $this->assertEquals($expected->id, $client->refresh()->b2b_price_level_id);
        }
    }

    public function test_only_paid_non_cancelled_orders_in_period_count_and_level_does_not_drop(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $client = B2bClient::create([
            'user_id' => $user->id, 'status' => 'approved',
            'partner_type' => 'wholesale', 'company_name' => 'Тест', 'responsible_user_id' => $manager->id,
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
            ['eligible', 'paid', 'completed', now()->subMonths(2), now(), 99.99],
            ['unpaid', 'pending', 'completed', now(), null, 200],
            ['cancelled', 'paid', 'cancelled', now(), now(), 200],
            ['old', 'paid', 'completed', now()->subMonths(2), now()->subMonths(2), 200],
        ] as [$number, $payment, $status, $createdAt, $paidAt, $total]) {
            DB::table('b2b_orders')->insert([
                'b2b_client_id' => $client->id, 'number' => $number, 'payment_status' => $payment,
                'status' => $status, 'created_at' => $createdAt, 'paid_at' => $paidAt, 'total' => $total,
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
        $transition = DB::table('partner_level_transitions')->latest('id')->first();
        $this->assertEquals($base->id, json_decode($transition->old_level, true)['id']);
        $this->assertEquals($next->id, json_decode($transition->new_level, true)['id']);
        $this->assertEquals(100, json_decode($transition->metrics, true)[0]['actual']);
        $this->assertSame(2, $manager->notifications()->count());
        Mail::assertQueued(\App\Mail\PartnerLevelReachedMail::class, fn ($mail) => $mail->levelName === $next->name);

        DB::table('b2b_orders')->where('number', 'eligible')->update(['status' => 'cancelled']);
        $evaluator->evaluate($client);
        $this->assertEquals($next->id, $client->refresh()->partner_program_level_id);
        $this->assertDatabaseCount('partner_level_transitions', 2);
    }
}
