<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $remindAt = now();
        $followUpNote = 'Срок действия КП истёк. Требуется связаться с клиентом и при необходимости продлить срок.';

        DB::table('commercial_documents')
            ->where('type', 'quote')
            ->whereIn('status', ['expired', 'rejected'])
            ->where('outcome_reason', 'no_response')
            ->where(function ($query): void {
                $query->where('outcome_note', 'like', 'Срок действия%истёк%')
                    ->orWhere('follow_up_note', 'like', 'Срок действия%истёк%');
            })
            ->orderBy('id')
            ->eachById(function (object $document) use ($remindAt, $followUpNote): void {
                $history = json_decode((string) ($document->contact_history ?? '[]'), true);
                $history = is_array($history) ? $history : [];
                $history = array_values(array_filter($history, static function (mixed $entry): bool {
                    if (! is_array($entry) || ($entry['result'] ?? null) !== 'rejected') {
                        return true;
                    }

                    $note = mb_strtolower((string) ($entry['note'] ?? ''));

                    return ! (str_contains($note, 'срок действия') && str_contains($note, 'истёк'));
                }));
                $lastEntry = collect($history)->last();
                $lastResult = is_array($lastEntry) ? ($lastEntry['result'] ?? null) : null;

                DB::table('commercial_documents')->where('id', $document->id)->update([
                    'status' => 'expired',
                    'follow_up_stage' => 'contact_planned',
                    'last_contact_result' => $lastResult,
                    'next_follow_up_at' => $remindAt,
                    'follow_up_note' => $followUpNote,
                    'contact_history' => json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'outcome_reason' => null,
                    'outcome_note' => null,
                    'closed_at' => null,
                    'last_control_notified_at' => null,
                    'updated_at' => $remindAt,
                ]);

                if ($document->crm_mail_thread_id) {
                    DB::table('crm_mail_threads')->where('id', $document->crm_mail_thread_id)->update([
                        'sales_stage' => 'contact_planned',
                        'status' => 'waiting_manager',
                        'next_follow_up_at' => $remindAt,
                        'follow_up_note' => $followUpNote,
                        'updated_at' => $remindAt,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Это исправление ошибочно закрытых данных; безопасного автоматического отката нет.
    }
};
