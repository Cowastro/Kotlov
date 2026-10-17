<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSupportChatAiReply;
use App\Models\SupportChatSetting;
use App\Models\SupportConversation;
use App\Rules\PhoneNotSpam;
use App\Services\SupportChatNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $conversation = $this->currentConversation($request);

        if (! $conversation) {
            return response()->json(['conversation' => null, 'messages' => []]);
        }

        $conversation->messages()
            ->whereIn('sender_type', ['manager', 'assistant'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->conversationResponse($conversation->fresh());
    }

    public function start(Request $request, SupportChatNotifier $notifier): JsonResponse
    {
        abort_if($request->filled('_hpf'), 422, 'Не удалось открыть чат. Обновите страницу и попробуйте снова.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:50', new PhoneNotSpam],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'page_url' => ['nullable', 'url:http,https', 'max:1000'],
        ]);

        $conversation = DB::transaction(function () use ($request, $validated): SupportConversation {
            $current = $this->currentConversation($request);
            if ($current && $current->status !== 'closed') {
                $current->update([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                ]);

                return $current;
            }

            $conversation = SupportConversation::query()->create([
                'public_id' => (string) Str::uuid(),
                'session_hash' => hash('sha256', $request->session()->getId()),
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'status' => 'new',
                'page_url' => $validated['page_url'] ?? null,
                'last_message_at' => now(),
                'last_visitor_message_at' => now(),
            ]);

            $request->session()->put('support_conversation_id', $conversation->id);

            return $conversation;
        });

        $isFirstMessage = ! $conversation->messages()->exists();
        $message = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => $validated['message'],
        ]);
        $conversation->update([
            'status' => 'new',
            'last_message_at' => now(),
            'last_visitor_message_at' => now(),
        ]);

        if ($isFirstMessage) {
            $notifier->notifyNewConversation($conversation);
        }
        $this->dispatchAiReply($conversation, $message->id);

        return $this->conversationResponse($conversation->fresh(), 201);
    }

    public function send(Request $request, SupportChatNotifier $notifier): JsonResponse
    {
        $conversation = $this->currentConversation($request);
        abort_if(! $conversation, 404, 'Диалог не найден. Начните новый чат.');
        abort_if($conversation->status === 'closed', 409, 'Диалог закрыт. Начните новый чат.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => $validated['message'],
        ]);
        $conversation->update([
            'status' => 'new',
            'last_message_at' => now(),
            'last_visitor_message_at' => now(),
        ]);
        $notifier->notifyNewMessage($conversation);
        $this->dispatchAiReply($conversation, $message->id);

        return $this->conversationResponse($conversation->fresh(), 201);
    }

    private function currentConversation(Request $request): ?SupportConversation
    {
        $id = $request->session()->get('support_conversation_id');

        return $id ? SupportConversation::query()->find($id) : null;
    }

    private function conversationResponse(SupportConversation $conversation, int $status = 200): JsonResponse
    {
        $conversation->load(['messages.sender']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->public_id,
                'name' => $conversation->name,
                'phone' => $conversation->phone,
                'status' => $conversation->status,
            ],
            'messages' => $conversation->messages->map(fn ($message): array => [
                'id' => $message->id,
                'sender' => $message->sender_type,
                'sender_name' => match ($message->sender_type) {
                    'manager' => $message->sender?->name ?: 'Консультант KOTLOV',
                    'assistant' => 'AI-помощник KOTLOV',
                    default => $conversation->name,
                },
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ], $status);
    }

    private function dispatchAiReply(SupportConversation $conversation, int $messageId): void
    {
        if (SupportChatSetting::aiEnabled()) {
            GenerateSupportChatAiReply::dispatchAfterResponse($conversation->id, $messageId);
        }
    }
}
