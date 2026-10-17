<?php

namespace App\Services;

use App\Models\SupportChatSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportChatAutoResponder
{
    private const MAX_AI_REPLIES = 3;

    public static function configured(): bool
    {
        return filled(config('services.ai.api_key')) && filled(config('services.ai.api_url'));
    }

    public function reply(int $conversationId, int $visitorMessageId): void
    {
        if (! SupportChatSetting::aiEnabled() || ! self::configured()) {
            return;
        }

        $conversation = SupportConversation::query()->with('messages')->find($conversationId);
        if (! $conversation || ! $this->mayReply($conversation, $visitorMessageId)) {
            return;
        }

        $aiReplyCount = $conversation->messages->where('sender_type', 'assistant')->count();

        if ($aiReplyCount >= self::MAX_AI_REPLIES - 1) {
            $this->storeReply($conversation, $this->handoffMessage(), true);

            return;
        }

        try {
            $response = $this->client()->post((string) config('services.ai.api_url'), [
                'model' => (string) config('services.ai.model', 'deepseek-chat'),
                'messages' => $this->messagesForApi($conversation),
                'temperature' => 0.1,
                'max_tokens' => 280,
                'stream' => false,
            ])->throw();

            $body = trim((string) data_get($response->json(), 'choices.0.message.content'));
            if ($body === '') {
                throw new \RuntimeException('DeepSeek returned an empty support-chat response.');
            }

            $this->storeReply($conversation, mb_substr($body, 0, 1800));
        } catch (Throwable $exception) {
            Log::error('Support chat AI reply failed.', [
                'conversation_id' => $conversationId,
                'visitor_message_id' => $visitorMessageId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function mayReply(SupportConversation $conversation, int $visitorMessageId): bool
    {
        if ($conversation->status === 'closed'
            || $conversation->assigned_user_id
            || $conversation->last_manager_message_at
            || $conversation->ai_handoff_at) {
            return false;
        }

        $latest = $conversation->messages->last();

        return $latest
            && $latest->id === $visitorMessageId
            && $latest->sender_type === 'visitor';
    }

    private function messagesForApi(SupportConversation $conversation): array
    {
        $history = $conversation->messages
            ->take(-10)
            ->map(fn (SupportMessage $message): array => [
                'role' => $message->sender_type === 'visitor' ? 'user' : 'assistant',
                'content' => $message->body,
            ])
            ->values()
            ->all();

        array_unshift($history, [
            'role' => 'system',
            'content' => $this->systemPrompt(),
        ]);

        return $history;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Ты — AI-помощник интернет-магазина отопительного оборудования KOTLOV. Имя и телефон клиента уже безопасно сохранены в системе KOTLOV и не переданы тебе, поэтому никогда не проси их повторно.

Твоя единственная задача — кратко выяснить, что именно нужно клиенту, и подготовить запрос для живого менеджера. Пиши по-русски, вежливо, максимум 3 коротких предложения. Задавай только один конкретный уточняющий вопрос за сообщение. Для подбора можно уточнять: тип оборудования, площадь и назначение объекта, топливо или мощность, способ установки, город, сроки и бюджет.

Жёсткие ограничения:
- честно называй себя AI-помощником в первой реплике;
- не придумывай и не подтверждай цену, наличие, срок доставки, характеристики, совместимость, гарантию, скидку или точную модель;
- не давай опасных инструкций по газу, электричеству, дымоходам и монтажу;
- не обещай действия и сроки от имени сотрудника;
- если вопрос требует проверки, скажи, что его проверит менеджер;
- не обсуждай темы, не связанные с товарами, заказом, доставкой или монтажом KOTLOV;
- не используй Markdown, списки и ссылки.

Из подтверждённых общих фактов можно сообщить только: заказы на сайте принимаются круглосуточно; живой консультант работает ежедневно до 20:00. Когда данных достаточно, кратко резюмируй потребность и закончи: «Ваши имя и телефон уже сохранены — передаю запрос менеджеру KOTLOV».
PROMPT;
    }

    private function handoffMessage(): string
    {
        return 'Спасибо, основные данные уточнены. Ваши имя и телефон уже сохранены — передаю запрос менеджеру KOTLOV. Он проверит детали и свяжется с вами в рабочее время.';
    }

    private function storeReply(SupportConversation $conversation, string $body, bool $handoff = false): void
    {
        $conversation->messages()->create([
            'sender_type' => 'assistant',
            'body' => $body,
        ]);
        $conversation->update([
            'last_message_at' => now(),
            'ai_handoff_at' => $handoff ? now() : $conversation->ai_handoff_at,
        ]);
    }

    private function client(): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->withToken((string) config('services.ai.api_key'))
            ->connectTimeout(5)
            ->timeout(max(5, (int) config('services.ai.support_chat_timeout', 12)));

        $proxy = trim((string) config('services.ai.openai_proxy', ''));

        return $proxy !== '' ? $client->withOptions(['proxy' => $proxy]) : $client;
    }
}
