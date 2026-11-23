<?php

namespace App\Services\Shipping;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressDraft;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VamExpressDrafts
{
    public static function defaults(): array
    {
        return ['boxes' => 1, 'express' => false, 'ship_date' => now('Europe/Minsk')->addDay()->toDateString(),
            'recipient_street_prefix' => 'ул.', 'sender_street_prefix' => 'ул.',
            'goods' => [['description' => null, 'model' => '', 'declared_price' => '0.00', 'number' => 1]]];
    }

    public function fromOrder(Order $order, User $actor): VamExpressDraft
    {
        $this->authorize($actor, $order);

        return DB::transaction(function () use ($order, $actor): VamExpressDraft {
            $order = OrderResource::getEloquentQuery()->lockForUpdate()->findOrFail($order->id);
            $existing = VamExpressDraft::where('order_id', $order->id)->where('status', 'draft')->first();
            if ($existing) {
                return $existing;
            }
            $payload = self::defaults();
            // Customer_name and address have no reliable structured order; retain them verbatim for the manager to check.
            $payload['recipient_source_name'] = (string) $order->customer_name;
            $payload['recipient_source_address'] = (string) $order->delivery_address;
            $payload['recipient_city_hint'] = (string) $order->delivery_city;
            $payload['recipient_phone'] = $this->phone((string) $order->customer_phone);
            $payload['goods'] = $order->items->take(20)->map(fn ($item) => ['description' => null,
                'model' => mb_substr((string) $item->product_name, 0, 50), 'declared_price' => (string) $item->price,
                'number' => 1])->all() ?: $payload['goods'];

            return $this->create($payload, $actor, $order);
        });
    }

    public function create(array $payload, User $actor, ?Order $order = null, ?string $creationKey = null): VamExpressDraft
    {
        $this->authorize($actor, $order);
        if ($creationKey !== null) {
            $key = hash('sha256', $actor->id.':'.$creationKey);

            return Cache::lock('vam-draft-create:'.$key, 15)->block(3, function () use ($key, $payload, $actor, $order): VamExpressDraft {
                $existing = VamExpressDraft::where('creation_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
                $draft = new VamExpressDraft;
                $draft->forceFill(['creation_key' => $key, 'payload' => $this->clean($payload), 'order_id' => $order?->id, 'created_by' => $actor->id, 'status' => 'draft', 'version' => 1])->save();

                return $draft;
            });
        }
        $draft = new VamExpressDraft;
        $draft->forceFill(['payload' => $this->clean($payload), 'order_id' => $order?->id, 'created_by' => $actor->id,
            'status' => 'draft', 'version' => 1])->save();

        return $draft;
    }

    public function save(VamExpressDraft $draft, array $payload, int $version, User $actor): VamExpressDraft
    {
        $this->authorize($actor, $draft->order);
        $payload = $this->clean($payload);

        return DB::transaction(function () use ($draft, $payload, $version): VamExpressDraft {
            $record = VamExpressDraft::lockForUpdate()->findOrFail($draft->id);
            if ($record->version !== $version || $record->status !== 'draft') {
                throw ValidationException::withMessages(['data.payload.profile_id' => 'Черновик изменён в другой вкладке. Обновите страницу, чтобы не затереть изменения.']);
            }
            if (VamExpressQuote::fingerprint($record->payload) !== VamExpressQuote::fingerprint($payload)) {
                $record->forceFill(['quote' => null, 'quoted_amount' => null, 'quoted_at' => null]);
            }
            $record->forceFill(['payload' => $payload, 'version' => $record->version + 1])->save();

            return $record;
        });
    }

    public function attachQuote(VamExpressDraft $draft, array $quote, int $version, User $actor): VamExpressDraft
    {
        $this->authorize($actor, $draft->order);

        return DB::transaction(function () use ($draft, $quote, $version): VamExpressDraft {
            $record = VamExpressDraft::lockForUpdate()->findOrFail($draft->id);
            if ($record->version !== $version || $record->status !== 'draft' || VamExpressQuote::fingerprint($record->payload) !== ($quote['fingerprint'] ?? '')) {
                throw new VamExpressException('Черновик изменился во время расчёта. Обновите его и рассчитайте снова.');
            }
            $record->forceFill(['quote' => $quote, 'quoted_amount' => $quote['amount'], 'quoted_at' => $quote['calculated_at'], 'version' => $record->version + 1])->save();

            return $record;
        });
    }

    public function missing(array $payload): array
    {
        $required = ['profile_id' => 'профиль отправителя', 'city_id' => 'населённый пункт доставки', 'sender_city_id' => 'населённый пункт отправления',
            'sender_street' => 'улица отправителя', 'sender_house' => 'дом отправителя', 'recipient_last_name' => 'фамилия получателя',
            'recipient_first_name' => 'имя получателя', 'recipient_patronymic' => 'отчество получателя', 'recipient_phone' => 'телефон получателя',
            'recipient_street' => 'улица получателя', 'recipient_house' => 'дом получателя', 'ship_date' => 'дата отправки'];
        $missing = [];
        foreach ($required as $key => $label) {
            if (blank($payload[$key] ?? null)) {
                $missing[] = $label;
            }
        }
        if (in_array(substr((string) ($payload['profile_id'] ?? ''), 0, 1), ['2', '3'], true)) {
            if (! preg_match('/^\p{L}{2}$/u', $payload['ttn_series'] ?? '')) {
                $missing[] = 'серия ТТН: 2 буквы';
            }
            if (! preg_match('/^\d{7}$/', $payload['ttn_number'] ?? '')) {
                $missing[] = 'номер ТТН: 7 цифр';
            }
        }
        foreach ($payload['goods'] ?? [] as $index => $good) {
            if (blank($good['description'] ?? null) || blank($good['model'] ?? null)) {
                $missing[] = 'категория и модель груза №'.($index + 1);
            }
        }
        foreach (['recipient_last_name' => 'фамилия', 'recipient_first_name' => 'имя', 'recipient_patronymic' => 'отчество'] as $key => $label) {
            if (filled($payload[$key] ?? null) && (! preg_match('/^[^\d\s]{2,30}$/u', $payload[$key]))) {
                $missing[] = $label.' получателя: 2–30 символов без цифр и пробелов';
            }
        }
        if (filled($payload['recipient_phone'] ?? null) && ! preg_match('/^\+375\d{9}$/', $this->phone($payload['recipient_phone']))) {
            $missing[] = 'основной телефон в формате +375 и 9 цифр';
        }
        if (filled($payload['ship_date'] ?? null) && $payload['ship_date'] <= now('Europe/Minsk')->toDateString()) {
            $missing[] = 'дата отправки должна быть позже сегодняшней';
        }

        return $missing;
    }

    private function authorize(User $actor, ?Order $order): void
    {
        abort_unless($actor->is_active && $actor->isManager() && (! $order || OrderResource::canEdit($order)), 403);
    }

    private function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 9) {
            return '+375'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '375')) {
            return '+'.$digits;
        }

        return trim($value);
    }

    private function clean(array $payload): array
    {
        $strings = ['profile_id', 'city_id', 'city_label', 'sender_city_id', 'sender_city_label', 'sender_street_prefix', 'sender_street', 'sender_house',
            'sender_housing', 'sender_apartment', 'recipient_first_name', 'recipient_last_name', 'recipient_patronymic', 'recipient_phone', 'recipient_phone2',
            'recipient_street_prefix', 'recipient_street', 'recipient_house', 'recipient_housing', 'recipient_apartment', 'ttn_series', 'ttn_number',
            'other_documents', 'note', 'recipient_source_name', 'recipient_source_address', 'recipient_city_hint', 'ship_date'];
        $rules = array_fill_keys($strings, 'nullable|string|max:500');
        $rules += ['goods' => 'required|array|min:1|max:20', 'goods.*.description' => 'nullable|string|max:255', 'goods.*.model' => 'nullable|string|max:50',
            'goods.*.number' => 'required|integer|min:1|max:10000', 'goods.*.declared_price' => 'required|numeric|decimal:0,2|min:0|max:1000000',
            'boxes' => 'required|integer|min:1|max:1000', 'express' => 'boolean'];
        foreach (['recipient_first_name', 'recipient_last_name', 'recipient_patronymic'] as $key) {
            $rules[$key] = 'nullable|string|max:30';
        }
        foreach (['recipient_house', 'recipient_housing', 'recipient_apartment', 'sender_house', 'sender_housing', 'sender_apartment'] as $key) {
            $rules[$key] = 'nullable|string|max:30';
        }
        $rules['ship_date'] = 'nullable|date_format:Y-m-d';
        $rules['ttn_series'] = 'nullable|string|max:2';
        $rules['ttn_number'] = 'nullable|string|max:7';
        $payload = array_replace(self::defaults(), $payload);
        foreach ($strings as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $payload[$key] = trim((string) $payload[$key]);
            }
        }
        if (filled($payload['recipient_phone'] ?? null)) {
            $payload['recipient_phone'] = $this->phone($payload['recipient_phone']);
        }
        $valid = Validator::make($payload, $rules)->validate();
        $clean = array_intersect_key($valid, array_flip(array_merge($strings, ['goods', 'boxes', 'express'])));
        $clean['goods'] = array_map(fn ($good) => array_intersect_key($good, array_flip(['description', 'model', 'number', 'declared_price'])), $clean['goods']);

        return $clean;
    }
}
