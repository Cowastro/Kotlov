<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commercial_documents')
            ->whereNotNull('contact_history')
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    $history = json_decode((string) $document->contact_history, true);
                    if (! is_array($history)) {
                        continue;
                    }

                    $clean = [];
                    foreach ($history as $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }

                        $entry['note'] = $this->cleanNote($entry['note'] ?? null);
                        $previous = end($clean);
                        if (is_array($previous) && $this->isDuplicateTerminalContact($previous, $entry)) {
                            continue;
                        }

                        $clean[] = $entry;
                    }

                    $updates = ['contact_history' => json_encode(
                        array_values($clean),
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
                    )];
                    $outcomeNote = $this->cleanNote($document->outcome_note ?? null);
                    $followUpNote = $this->cleanNote($document->follow_up_note ?? null);
                    $updates['outcome_note'] = $outcomeNote !== '' ? $outcomeNote : null;
                    $updates['follow_up_note'] = $followUpNote !== '' ? $followUpNote : null;

                    DB::table('commercial_documents')->where('id', $document->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Exact duplicate actions cannot be reconstructed reliably.
    }

    private function isDuplicateTerminalContact(array $previous, array $current): bool
    {
        if (! in_array($current['result'] ?? null, ['accepted', 'rejected'], true)) {
            return false;
        }

        if (($previous['stage'] ?? null) !== ($current['stage'] ?? null)
            || ($previous['result'] ?? null) !== ($current['result'] ?? null)
            || mb_strtolower($this->cleanNote($previous['note'] ?? null)) !== mb_strtolower($this->cleanNote($current['note'] ?? null))) {
            return false;
        }

        if (blank($previous['contacted_at'] ?? null) || blank($current['contacted_at'] ?? null)) {
            return false;
        }

        try {
            $previousAt = Carbon::parse($previous['contacted_at'] ?? null);
            $currentAt = Carbon::parse($current['contacted_at'] ?? null);
        } catch (Throwable) {
            return false;
        }

        return $previousAt->diffInMinutes($currentAt) <= 15;
    }

    private function cleanNote(mixed $note): string
    {
        return trim((string) $note, " \t\n\r\0\x0B,.;:–—-");
    }
};
