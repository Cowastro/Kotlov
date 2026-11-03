<?php

namespace App\Services;

use App\Mail\PartnerLevelReachedMail;
use App\Models\B2bClient;
use App\Models\PartnerProgramLevel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
                ->whereBetween('paid_at', [$now->subMonthsNoOverflow((int) $rule['months']), $now]);

            $revenue = $rule['metric'] === 'revenue';
            $scale = $revenue ? 100 : 1;
            $value = (int) round(($revenue ? $orders->sum('total') : $orders->count()) * $scale);
            $target = (int) round($rule['target'] * $scale);

            return array_merge($rule, [
                'actual' => $value / $scale,
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
                $price = $next->auto_apply_price_level ? $next->priceLevel : null;
                if ($price?->is_active && (! $client->priceLevel || $price->rank > $client->priceLevel->rank)) {
                    $client->b2b_price_level_id = $price->id;
                    $client->unsetRelation('priceLevel');
                }
                $client->saveQuietly();

                DB::table('partner_level_transitions')->insert([
                    'b2b_client_id' => $client->id,
                    'old_level' => $current ? json_encode($current->only(['id', 'name']), JSON_THROW_ON_ERROR) : null,
                    'new_level' => json_encode($next->only(['id', 'name']), JSON_THROW_ON_ERROR),
                    'metrics' => $rules->toJson(JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);

                if ($manager = $client->responsibleUser) {
                    Notification::make()
                        ->title("Компания {$client->company_name} достигла уровня {$next->name} — проверьте уровень цен")
                        ->sendToDatabase($manager);
                }

                $email = $client->contact_email ?: $client->user?->email;
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    DB::afterCommit(fn () => Mail::mailer('transactional')->to($email)
                        ->queue(new PartnerLevelReachedMail($next->name)));
                }
            }
        }, 3);
    }
}
