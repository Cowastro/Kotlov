<?php

namespace Tests\Unit;

use App\Models\IntegrationIssue;
use App\Models\Order;
use App\Services\AiContentEnricher;
use App\Services\Integrations\IntegrationIssueAiAdvisor;
use App\Services\Integrations\IntegrationIssueAdvisor;
use Mockery;
use PHPUnit\Framework\TestCase;

class IntegrationIssueAiAdvisorTest extends TestCase
{
    public function test_it_falls_back_to_safe_rules_when_ai_is_unavailable(): void
    {
        $ai = Mockery::mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturnFalse();
        $advisor = new IntegrationIssueAiAdvisor($ai, new IntegrationIssueAdvisor);

        $result = $advisor->advise(new IntegrationIssue([
            'type' => 'integration_stale',
            'title' => 'Нет свежего обмена',
        ]));

        $this->assertSame('rules', $result['source']);
        $this->assertSame('Восстановить автоматический обмен', $result['title']);
        $this->assertNotEmpty($result['steps']);
    }

    public function test_it_sends_only_technical_order_context_and_accepts_valid_json(): void
    {
        $issue = new IntegrationIssue([
            'type' => 'order_not_exported',
            'severity' => 'danger',
            'title' => 'Заказ не передан в 1С',
            'context' => [],
        ]);
        $issue->setRelation('order', new Order([
            'number' => 'ORD-2026-9000',
            'status' => 'new',
            'payment_status' => 'pending',
            'customer_name' => 'Секретный Клиент',
            'customer_phone' => '+375291112233',
            'customer_email' => 'secret@example.test',
            'delivery_address' => 'Секретный адрес',
        ]));
        $issue->setRelation('source', null);
        $issue->setRelation('integrationProduct', null);

        $ai = Mockery::mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturnTrue();
        $ai->shouldReceive('providerName')->once()->andReturn('test-model');
        $ai->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $prompt, int $maxTokens): bool {
                $this->assertStringContainsString('ORD-2026-9000', $prompt);
                $this->assertStringContainsString('payment_status', $prompt);
                $this->assertStringNotContainsString('Секретный Клиент', $prompt);
                $this->assertStringNotContainsString('+375291112233', $prompt);
                $this->assertStringNotContainsString('secret@example.test', $prompt);
                $this->assertStringNotContainsString('Секретный адрес', $prompt);

                return $maxTokens === 650;
            })
            ->andReturn('```json {"title":"Проверить получение заказа","steps":["Запустить штатный обмен заказами","Проверить ответ success"],"note":"Не отмечать вручную"} ```');
        $advisor = new IntegrationIssueAiAdvisor($ai, new IntegrationIssueAdvisor);

        $result = $advisor->advise($issue);

        $this->assertSame('ai', $result['source']);
        $this->assertSame('test-model', $result['provider']);
        $this->assertSame('Проверить получение заказа', $result['title']);
        $this->assertSame(['Запустить штатный обмен заказами', 'Проверить ответ success'], $result['steps']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
