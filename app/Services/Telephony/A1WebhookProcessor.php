<?php

namespace App\Services\Telephony;

use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\TelephonyCall;
use App\Models\TelephonyCallEvent;
use App\Models\TelephonyConnection;
use App\Models\TelephonyEmployee;
use App\Models\User;
use App\Services\Crm\CrmContactMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class A1WebhookProcessor
{
    public function __construct(private readonly CrmContactMatcher $crmContactMatcher) {}

    /** @param array<string, mixed> $payload */
    public function process(array $payload): TelephonyCall
    {
        $uuid = trim((string) ($payload['uuid'] ?? ''));
        $notificationType = strtoupper(trim((string) ($payload['notificationType'] ?? '')));

        if ($uuid === '' || ! in_array($notificationType, ['START', 'STATE', 'FINISH'], true)) {
            throw new InvalidArgumentException('Webhook A1 не содержит корректные uuid и notificationType.');
        }

        return DB::transaction(function () use ($payload, $uuid, $notificationType): TelephonyCall {
            $hash = $this->payloadHash($payload);
            $call = TelephonyCall::query()->firstOrCreate(
                ['provider' => 'a1', 'external_uuid' => $uuid],
                $this->initialAttributes($payload),
            );

            $event = TelephonyCallEvent::query()->firstOrCreate([
                'provider' => 'a1',
                'external_uuid' => $uuid,
                'payload_hash' => $hash,
            ], [
                'telephony_call_id' => $call->id,
                'notification_type' => $notificationType,
                'state' => $payload['crmCallState'] ?? $payload['crmCallFinishedStatus'] ?? null,
                'occurred_at' => $this->parseDate($payload['date'] ?? null),
                'payload' => $payload,
                'received_at' => now(),
            ]);

            if (! $event->wasRecentlyCreated) {
                return $call->fresh();
            }

            $this->applyEvent($call, $payload, $notificationType);
            $this->attachContext($call);
            $call->save();

            TelephonyConnection::query()->where('provider', 'a1')->update([
                'webhook_last_received_at' => now(),
                'last_error' => null,
            ]);

            return $call->fresh();
        });
    }

    /** @param array<string, mixed> $payload */
    private function initialAttributes(array $payload): array
    {
        $direction = strtolower((string) ($payload['crmCallType'] ?? 'inbound'));

        return [
            'company_id' => isset($payload['companyId']) ? (string) $payload['companyId'] : null,
            'direction' => in_array($direction, ['inbound', 'outbound', 'internal'], true) ? $direction : 'inbound',
            'dial_type' => $payload['dialType'] ?? null,
            'customer_phone' => PhoneNumber::normalize($payload['numberA'] ?? null),
            'employee_phone' => PhoneNumber::normalize($payload['numberB'] ?? null),
            'line_number' => PhoneNumber::normalize($payload['lineNumber'] ?? null),
            'status' => 'ringing',
            'handling_status' => 'unprocessed',
            'started_at' => $this->parseDate($payload['date'] ?? null) ?? now(),
            'metadata' => Arr::only($payload, ['crmUserIds']),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function applyEvent(TelephonyCall $call, array $payload, string $type): void
    {
        $call->fill(array_filter([
            'company_id' => isset($payload['companyId']) ? (string) $payload['companyId'] : null,
            'dial_type' => $payload['dialType'] ?? null,
            'customer_phone' => PhoneNumber::normalize($payload['numberA'] ?? null),
            'employee_phone' => PhoneNumber::normalize($payload['numberB'] ?? null),
            'line_number' => PhoneNumber::normalize($payload['lineNumber'] ?? null),
            'record_name' => $payload['recordName'] ?? null,
            'recording_url' => $payload['fullUrl'] ?? null,
            'failure_reason' => $payload['failedReason'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($type === 'STATE' && strtoupper((string) ($payload['crmCallState'] ?? '')) === 'UP') {
            $call->status = 'answered';
            $call->answered_at ??= now();
        }

        if ($type !== 'FINISH') {
            return;
        }

        $call->status = match (strtoupper((string) ($payload['crmCallFinishedStatus'] ?? ''))) {
            'ANSWERED' => 'answered',
            'NOT_ANSWERED' => 'missed',
            'CANCELLED' => 'cancelled',
            'BUSY' => 'busy',
            'DENIED' => 'denied',
            default => 'failed',
        };
        $call->duration = isset($payload['duration']) ? max(0, (int) $payload['duration']) : $call->duration;
        $call->finished_at = ($payload['historySource'] ?? false) && $call->started_at
            ? $call->started_at->copy()->addSeconds((int) ($call->duration ?? 0))
            : now();

        if ($call->status !== 'answered' && $call->handling_status === 'unprocessed') {
            $call->handling_status = 'callback_required';
        }
    }

    private function attachContext(TelephonyCall $call): void
    {
        if (! $call->assigned_user_id && $call->employee_phone) {
            $employee = TelephonyEmployee::query()
                ->where('provider', 'a1')
                ->where('is_active', true)
                ->where(function (Builder $query) use ($call): void {
                    $query->where('full_number', $call->employee_phone)
                        ->orWhere('short_number', $call->employee_phone);
                })
                ->first();
            $call->assigned_user_id = $employee?->user_id;
        }

        if (! $call->customer_phone) {
            return;
        }

        $variants = PhoneNumber::databaseVariants($call->customer_phone);
        $digitsExpression = $this->digitsExpression('%s');

        $call->order_id ??= Order::query()
            ->whereIn(DB::raw(sprintf($digitsExpression, 'customer_phone')), $variants)
            ->latest('id')->value('id');
        $call->matched_user_id ??= User::query()
            ->whereIn(DB::raw(sprintf($digitsExpression, 'phone')), $variants)
            ->latest('id')->value('id');
        $call->contact_request_id ??= ContactRequest::query()
            ->whereIn(DB::raw(sprintf($digitsExpression, 'phone')), $variants)
            ->latest('id')->value('id');

        if (! $call->crm_contact_id) {
            $this->crmContactMatcher->attachToCall($call);
        }
    }

    private function digitsExpression(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // A1 sends ISO_LOCAL_DATE_TIME without an offset and documents it
            // as Belarus local time. Persist UTC so Eloquent does not interpret
            // the same clock value as UTC and add three hours when displaying it.
            return CarbonImmutable::parse($value, config('telephony.timezone', 'Europe/Minsk'))->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        $sort = function (array &$values) use (&$sort): void {
            ksort($values);
            foreach ($values as &$value) {
                if (is_array($value)) {
                    $sort($value);
                }
            }
        };
        $sort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
