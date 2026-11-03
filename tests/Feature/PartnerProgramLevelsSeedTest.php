<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\PartnerProgramLevel;
use App\Models\User;
use App\Services\PartnerLevelEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProgramLevelsSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_levels_are_seeded_and_a_new_partner_starts_at_partner(): void
    {
        $levels = PartnerProgramLevel::query()->orderBy('rank')->get();
        $this->assertSame(['Партнёр', 'Профессионал', 'Дилер'], $levels->pluck('name')->all());
        $this->assertSame([], $levels[0]->rules);
        $this->assertSame([['metric' => 'revenue', 'months' => 6, 'target' => 15000]], $levels[1]->rules);
        $this->assertSame([['metric' => 'revenue', 'months' => 12, 'target' => 60000]], $levels[2]->rules);

        $client = B2bClient::create([
            'user_id' => User::factory()->create()->id, 'status' => 'approved',
            'partner_type' => 'installer', 'company_name' => 'Монтаж',
        ]);
        $evaluator = app(PartnerLevelEvaluator::class);
        $evaluator->evaluate($client);
        $this->assertEquals($levels[0]->id, $client->refresh()->partner_program_level_id);

        $this->view('partials.b2b.partner-level', ['client' => $client, 'can' => fn () => true])
            ->assertSeeText('Ваш уровень — Партнёр')
            ->assertSeeText('Следующий уровень — Профессионал')
            ->assertSeeText('Осталось 15 000,00')
            ->assertSeeText('Заявки клиентов на монтаж в вашем регионе.');
    }
}
