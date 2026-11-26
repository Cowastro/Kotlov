<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EvropochtaRecipient
{
    public static function validate(array $input): array
    {
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input);
        $data = Validator::make($input, [
            'receiver_name' => ['required', 'string', 'min:2', 'max:100'],
            'receiver_surname' => ['required', 'string', 'min:2', 'max:100'],
            'receiver_patronymic_name' => ['nullable', 'string', 'max:100'],
            'receiver_phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/D'],
        ])->validate();
        $phone = PhoneNumber::normalize(PhoneNumber::format($data['receiver_phone_number']) ?? $data['receiver_phone_number']);
        if (! $phone || ! preg_match('/^\d{7,15}$/D', $phone)) {
            throw ValidationException::withMessages(['receiver_phone_number' => 'Укажите один номер телефона с кодом страны.']);
        }
        $data = array_map(fn ($value): string => trim((string) $value), $data);
        $data['receiver_phone_number'] = $phone;

        return $data;
    }

    public static function defaults(Order $order): array
    {
        return ($order->evropochta_quote['recipient'] ?? []) + [
            'receiver_name' => preg_match('/^\S+$/u', trim((string) $order->customer_name)) ? trim($order->customer_name) : null,
            'receiver_surname' => null, 'receiver_patronymic_name' => null,
            'receiver_phone_number' => $order->customer_phone,
        ];
    }

    public static function name(Order $order): string
    {
        $recipient = $order->evropochta_quote['recipient'] ?? [];

        return collect([$recipient['receiver_surname'] ?? null, $recipient['receiver_name'] ?? null,
            $recipient['receiver_patronymic_name'] ?? null])->filter()->implode(' ') ?: (string) $order->customer_name;
    }

    public static function phone(Order $order): ?string
    {
        return $order->evropochta_quote['recipient']['receiver_phone_number'] ?? $order->customer_phone;
    }
}
