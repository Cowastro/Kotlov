<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;

class EnsureSupplierCommand extends Command
{
    protected $signature = 'supplier:ensure
        {code : Stable supplier code}
        {name : Supplier display name}
        {--currency=BYN : ISO 4217 currency code}
        {--rate=1 : Currency rate to BYN}
        {--contact= : Supplier contact or source URL}
        {--notes= : Internal notes}
        {--apply : Create the supplier when it is missing}';

    protected $description = 'Create a supplier only when its code does not already exist';

    public function handle(): int
    {
        $code = strtolower(trim((string) $this->argument('code')));
        $name = trim((string) $this->argument('name'));
        $currency = strtoupper(trim((string) $this->option('currency')));
        $currencyRate = (float) $this->option('rate');

        if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)) {
            $this->error('Supplier code must contain only lowercase Latin letters, digits, underscores or hyphens.');

            return self::FAILURE;
        }

        if ($name === '') {
            $this->error('Supplier name is required.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->error('Currency must be a three-letter ISO code.');

            return self::FAILURE;
        }

        if ($currencyRate <= 0) {
            $this->error('Currency rate must be greater than zero.');

            return self::FAILURE;
        }

        $existing = Supplier::query()->where('code', $code)->first();

        if ($existing) {
            $this->info(sprintf(
                'Supplier already exists: id=%d code=%s name=%s currency=%s',
                $existing->id,
                $existing->code,
                $existing->name,
                $existing->currency,
            ));

            return self::SUCCESS;
        }

        $attributes = [
            'code' => $code,
            'name' => $name,
            'currency' => $currency,
            'currency_rate' => $currencyRate,
            'contact' => $this->nullIfBlank($this->option('contact')),
            'notes' => $this->nullIfBlank($this->option('notes')),
            'is_active' => true,
        ];

        if (! $this->option('apply')) {
            $this->warn('[DRY-RUN] Supplier is missing and would be created.');
            $this->table(['code', 'name', 'currency', 'contact'], [[
                $attributes['code'],
                $attributes['name'],
                $attributes['currency'],
                $attributes['contact'] ?? '-',
            ]]);

            return self::SUCCESS;
        }

        $supplier = Supplier::query()->create($attributes);

        $this->info(sprintf(
            'Supplier created: id=%d code=%s name=%s currency=%s',
            $supplier->id,
            $supplier->code,
            $supplier->name,
            $supplier->currency,
        ));

        return self::SUCCESS;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
