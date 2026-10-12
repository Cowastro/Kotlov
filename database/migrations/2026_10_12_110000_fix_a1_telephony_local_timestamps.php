<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_calls') || ! Schema::hasTable('telephony_call_events')) {
            return;
        }

        DB::table('telephony_calls')
            ->where('provider', 'a1')
            ->orderBy('id')
            ->chunkById(100, function ($calls): void {
                foreach ($calls as $call) {
                    if (! $call->started_at) {
                        continue;
                    }

                    $updates = [
                        'started_at' => $this->belarusLocalToUtc($call->started_at),
                    ];

                    // History records were created with both timestamps in A1
                    // local time. Live webhook FINISH timestamps came from the
                    // server in UTC and, before this fix, are earlier than the
                    // incorrectly stored START by roughly three hours.
                    if ($call->finished_at && strtotime((string) $call->finished_at) >= strtotime((string) $call->started_at)) {
                        $updates['finished_at'] = $this->belarusLocalToUtc($call->finished_at);
                    }

                    DB::table('telephony_calls')->where('id', $call->id)->update($updates);
                }
            });

        DB::table('telephony_call_events')
            ->where('provider', 'a1')
            ->whereNotNull('occurred_at')
            ->orderBy('id')
            ->chunkById(100, function ($events): void {
                foreach ($events as $event) {
                    DB::table('telephony_call_events')->where('id', $event->id)->update([
                        'occurred_at' => $this->belarusLocalToUtc($event->occurred_at),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // This is a one-time correction of timestamps that were stored with an
        // incorrect timezone assumption. Reintroducing the error is unsafe.
    }

    private function belarusLocalToUtc(string $value): string
    {
        return CarbonImmutable::parse($value, 'Europe/Minsk')
            ->utc()
            ->format('Y-m-d H:i:s');
    }
};
