<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\PartnerProgramLevel;
use Illuminate\Support\Facades\DB;

class PartnerLevelEvaluator
{
    public function progress(B2bClient $client): array
    {
        $current = PartnerProgramLevel::find($client->partner_program_level_id);
        $next = PartnerProgramLevel::query()->where('is_active', true)
            ->where('rank', '>', $current?->rank ?? 0)->orderBy('rank')->first();

        $now = now()->toImmutable();
        $rules = collect($next?->rules ?? [])->map(function (array $rule) use ($client, $now): array {
            $orders = $client->orders()
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$now->subMonthsNoOverflow((int) $rule['months']), $now]);

            $revenue = $rule['metric'] === 'revenue';
            $scale = $revenue ? 100 : 1;
            $value = (int) round(($revenue ? $orders->sum('total') : $orders->count()) * $scale);
            $target = (int) round($rule['target'] * $scale);

            return array_merge($rule, [
                'remaining' => max(0, $target - $value) / $scale,
                'percent' => $target <= 0 || $value >= $target
                    ? 100
                    : (int) floor(100 * max(0, $value) / $target),
            ]);
        });

        return compact('current', 'next', 'rules');
    }

    public function evaluate(B2bClient $client): void
    {
        DB::transaction(function () use ($client): void {
            $client = B2bClient::query()->lockForUpdate()->findOrFail($client->id);
            if ($client->status !== 'approved') {
                return;
            }

            while (true) {
                ['current' => $current, 'next' => $next, 'rules' => $rules] = $this->progress($client);
                if (! $next || ($current && $rules->isEmpty()) || $rules->contains(fn ($rule) => $rule['remaining'] > 0)) {
                    return;
                }

                $client->partner_program_level_id = $next->id;
                $client->saveQuietly();
            }
        }, 3);
    }
}
