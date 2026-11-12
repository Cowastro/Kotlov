<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SplitMailRequest
{
    public function split(CrmMailThread $source, int $messageId, string $subject, User $actor): CrmMailThread
    {
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 998) {
            throw ValidationException::withMessages(['subject' => 'Укажите тему нового запроса (до 998 символов).']);
        }

        return DB::transaction(function () use ($source, $messageId, $subject, $actor): CrmMailThread {
            $source = CrmMailThread::query()->visibleTo($actor)->lockForUpdate()->findOrFail($source->id);
            abort_if(in_array($source->status, ['spam', 'draft'], true), 422);
            $message = $source->messages()->lockForUpdate()->findOrFail($messageId);
            abort_unless($message->direction === 'incoming', 422);

            $new = CrmMailThread::create([
                'source_thread_id' => $source->id,
                'mailbox_id' => $source->mailbox_id,
                'crm_counterparty_id' => $source->crm_counterparty_id,
                'crm_contact_id' => $source->crm_contact_id,
                'assigned_user_id' => $source->assigned_user_id ?: $actor->id,
                'thread_key' => 'split:'.Str::uuid(),
                'subject' => $subject,
                'participant_name' => $message->from_name ?: $source->participant_name,
                'participant_email' => $message->from_email,
                'status' => 'waiting_manager',
                'sales_stage' => 'new_inquiry',
                'priority' => $source->priority ?: 'normal',
            ]);

            $metadata = $message->metadata ?? [];
            $metadata['request_split_history'][] = [
                'source_thread_id' => $source->id,
                'target_thread_id' => $new->id,
                'user_id' => $actor->id,
                'user_name' => $actor->name,
                'at' => now()->toIso8601String(),
            ];
            $message->update(['thread_id' => $new->id, 'metadata' => $metadata]);

            foreach ([$source, $new] as $thread) {
                $thread->forceFill([
                    'messages_count' => $thread->messages()->count(),
                    'unread_count' => $thread->messages()->where('direction', 'incoming')->where('is_read', false)->count(),
                    'last_message_at' => $thread->messages()->max('received_at'),
                ])->saveQuietly();
            }

            return $new;
        });
    }
}
