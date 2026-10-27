<?php

namespace App\Services\Telephony;

use App\Models\TelephonyCall;
use Carbon\CarbonImmutable;

class A1HistoryReconciler
{
    public function __construct(private readonly A1WebhookProcessor $processor) {}

    /** @param array<string, mixed> $row */
    public function reconcile(array $row): ?TelephonyCall
    {
        $uuid = trim((string) ($row['uuid'] ?? ''));
        if ($uuid === '') {
            return null;
        }

        $callType = strtoupper(trim((string) ($row['callType'] ?? '')));
        $outbound = in_array($callType, ['OUTGOING', 'CRM_OUTGOING'], true);
        $status = strtoupper(trim((string) ($row['callStatus'] ?? '')));
        $answered = str_starts_with($status, 'ANSWERED');

        $payload = [
            'notificationType' => 'FINISH',
            'uuid' => $uuid,
            'crmCallType' => $outbound ? 'OUTBOUND' : ($callType === 'INTERNAL' ? 'INTERNAL' : 'INBOUND'),
            'numberA' => $row['callerNumber'] ?? null,
            'numberB' => $row['calleeNumber'] ?? ($outbound ? ($row['destinationNumber'] ?? null) : null),
            // For incoming calls destinationNumber is the originally dialled
            // multichannel company line. For outgoing history it is the client,
            // so keep the more precise lineNumber already received by webhook.
            'lineNumber' => $outbound ? null : ($row['destinationNumber'] ?? null),
            'date' => isset($row['callTimestamp'])
                ? CarbonImmutable::createFromTimestampUTC((int) $row['callTimestamp'])
                    ->setTimezone(config('telephony.timezone', 'Europe/Minsk'))->format('Y-m-d\TH:i:s')
                : null,
            'duration' => $row['callDuration'] ?? null,
            'crmCallFinishedStatus' => $answered ? 'ANSWERED' : match (true) {
                str_contains($status, 'BUSY') => 'BUSY',
                str_contains($status, 'CANCEL') => 'CANCELLED',
                str_contains($status, 'DENIED') => 'DENIED',
                default => 'NOT_ANSWERED',
            },
            'historySource' => true,
        ];

        $call = $this->processor->process(array_filter($payload, static fn (mixed $value): bool => $value !== null));
        $callbackStatus = $row['calledBackStatus'] ?? null;
        $updates = [
            'conversation_duration' => isset($row['conversationDuration']) ? max(0, (int) $row['conversationDuration']) : null,
            'callback_status' => $callbackStatus,
        ];

        if (in_array($callbackStatus, ['CALLED_BACK', 'CALLER_CALLED_AGAIN'], true)
            && $call->handling_status === 'callback_required') {
            $updates['handling_status'] = 'handled';
            $updates['handled_at'] = now();
        }

        $call->update(array_filter($updates, static fn (mixed $value): bool => $value !== null));

        return $call->fresh();
    }
}
