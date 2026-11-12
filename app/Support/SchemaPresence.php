<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class SchemaPresence
{
    public static function table(string $table): bool
    {
        return self::remember('table:'.$table, fn (): bool => Schema::hasTable($table));
    }

    public static function column(string $table, string $column): bool
    {
        return self::remember('column:'.$table.':'.$column, fn (): bool => Schema::hasColumn($table, $column));
    }

    private static function remember(string $key, \Closure $check): bool
    {
        if (app()->runningUnitTests()) {
            return $check();
        }

        $connection = config('database.default');
        $key = 'schema-presence:'.sha1($connection.':'.config('database.connections.'.$connection.'.database').':'.$key);
        if (Cache::get($key) === true) {
            return true;
        }
        $exists = $check();
        if ($exists) {
            Cache::put($key, true, 300);
        }

        return $exists;
    }
}
