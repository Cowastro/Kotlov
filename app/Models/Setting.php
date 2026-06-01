<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    // Получить значение по ключу
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting_' . $key, function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    // Установить значение
    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting_' . $key);
    }
}
