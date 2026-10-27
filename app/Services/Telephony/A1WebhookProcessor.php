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
        [$customerPhone, $employeePhone] = $this->partyPhones($payload, $direction);
        $lineNumber = $this->companyLine($payload, $direction, $employeePhone);

        return [
            'company_id' => isset($payload['companyId']) ? (string) $payload['companyId'] : null,
            'direction' => in_array($direction, ['inbound', 'outbound', 'internal'], true) ? $direction : 'inbound',
            'dial_type' => $payload['dialType'] ?? null,
            'customer_phone' => $customerPhone,
            'employee_phone' => $employeePhone,
            'line_number' => $lineNumber,
            'status' => 'ringing',
            'handling_status' => 'unprocessed',
            'started_at' => $this->parseDate($payload['date'] ?? null) ?? now(),
            'metadata' => Arr::only($payload, ['crmUserIds']),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function applyEvent(TelephonyCall $call, array $payload, string $type): void
    {
        $direction = strtolower((string) ($payload['crmCallType'] ?? $call->direction ?? 'inbound'));
        [$customerPhone, $employeePhone] = $this->partyPhones($payload, $direction);
        $lineNumber = $this->companyLine($payload, $direction, $employeePhone);

        if ($customerPhone && $call->customer_phone && $customerPhone !== $call->customer_phone) {
            $this->clearCustomerContext($call);
        }

        if ($employeePhone && $call->employee_phone && $employeePhone !== $call->employee_phone) {
            $call->assigned_user_id = null;
        }

        if ($this->employeeWasMatchedAsCustomer($call, $customerPhone, $employeePhone)) {
            $this->clearCustomerContext($call);
        }

        $call->fill(array_filter([
            'company_id' => isset($payload['companyId']) ? (string) $payload['companyId'] : null,
            'direction' => in_array($direction, ['inbound', 'outbound', 'internal'], true) ? $direction : null,
            'dial_type' => $payload['dialType'] ?? null,
            'customer_phone' => $customerPhone,
            'employee_phone' => $employeePhone,
            'line_number' => $lineNumber,
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

        $finishedStatus = strtoupper((string) ($payload['crmCallFinishedStatus'] ?? ''));
        $call->status = match ($finishedStatus) {
            'ANSWERED' => 'answered',
            'NOT_ANSWERED' => 'missed',
            // For an inbound call A1 uses CANCELLED when the caller stops
            // waiting before an employee answers. For the sales team this is
            // a missed call that requires a callback, not a cancelled deal.
            'CANCELLED' => $call->direction === 'inbound' ? 'missed' : 'cancelled',
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

    /** @param array<string, mixed> $payload
     *  @return array{0: ?string, 1: ?string}
     */
    private function partyPhones(array $payload, string $direction): array
    {
        $numberA = PhoneNumber::normalize($payload['numberA'] ?? null);
        $numberB = PhoneNumber::normalize($payload['numberB'] ?? null);

        return $direction === 'outbound'
            ? [$numberB, $numberA]
            : [$numberA, $numberB];
    }

    private function clearCustomerContext(TelephonyCall $call): void
    {
        $call->order_id = null;
        $call->matched_user_id = null;
        $call->contact_request_id = null;
        $call->crm_counterparty_id = null;
        $call->crm_contact_id = null;
    }

    private function employeeWasMatchedAsCustomer(
        TelephonyCall $call,
        ?string $customerPhone,
        ?string $employeePhone,
    ): bool {
        if (! $call->matched_user_id || ! $customerPhone || ! $employeePhone || $customerPhone === $employeePhone) {
            return false;
        }

        return TelephonyEmployee::query()
            ->where('provider', 'a1')
            ->where('is_active', true)
            ->where('user_id', $call->matched_user_id)
            ->where(function (Builder $query) use ($employeePhone): void {
                $query->where('full_number', $employeePhone)
                    ->orWhere('short_number', $employeePhone);
            })
            ->exists();
    }

    /** @param array<string, mixed> $payload */
    private function companyLine(array $payload, string $direction, ?string $employeePhone): ?string
    {
        $provided = PhoneNumber::normalize($payload['lineNumber'] ?? null);

        if ($direction !== 'outbound') {
            return $provided;
        }

        if ($provided && array_key_exists($provided, config('telephony.company_lines', []))) {
            return $provided;
        }

        return $employeePhone
            ? config("telephony.employee_lines.{$employeePhone}", $provided)
            : $provided;
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
