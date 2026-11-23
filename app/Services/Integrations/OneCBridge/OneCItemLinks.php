<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** The exchange registry owns links; onec_items is only its display projection. */
class OneCItemLinks
{
    public static function identity(string $externalId): ?string
    {
        $parts = explode('#', strtolower(trim($externalId)));
        if (count($parts) > 2 || ! preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $parts[0]) || $parts[0] === OneCReferences::ZERO) {
            return null;
        }
        if (count($parts) === 1 || $parts[1] === OneCReferences::ZERO) {
            return $parts[0];
        }

        return preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $parts[1]) ? implode('#', $parts) : null;
    }

    public function query(string $key): Builder
    {
        return IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))
            ->where(fn ($q) => $q->where('external_id', $key)->orWhere('external_id', 'like', $key.'#%'));
    }

    public static function summary(Collection $rows): array
    {
        $groups = $rows->filter(fn ($r) => self::identity($r->external_id) !== null)->groupBy(fn ($r) => self::identity($r->external_id));
        $variants = $groups->keys()->filter(fn ($key) => str_contains($key, '#'))->count();
        $linked = $groups->map(fn ($group) => $group->where('match_status', '!=', 'ignored')->pluck('product_id')->filter()->unique()->values());
        $assigned = $linked->filter(fn ($ids) => $ids->count() === 1)->map(fn ($ids) => $ids->first());
        $conflict = $assigned->unique()->count() !== $assigned->count() || $linked->contains(fn ($ids) => $ids->count() > 1)
            || ($variants > 0 && $linked->contains(fn ($ids, $key) => ! str_contains($key, '#') && $ids->isNotEmpty()));
        $card = $variants === 0 && ! $conflict ? ($linked->flatten()->unique()->count() === 1 ? $linked->flatten()->unique()->first() : null) : null;

        return ['product_id' => $card, 'site_link_count' => $linked->filter(fn ($ids) => $ids->count() === 1)->count(),
            'characteristic_count' => $variants, 'link_conflict' => $conflict];
    }

    private function fingerprint(Collection $rows): string
    {
        return hash('sha256', $rows->sortBy('id')->map(fn ($r) => [$r->id, $r->external_id, $r->product_id, $r->match_status])->values()->toJson());
    }

    public function formData(OneCItem $item): array
    {
        $rows = $this->query($item->ref_key)->with('product:id,name,sku')->get();
        $links = $rows->filter(fn ($r) => self::identity($r->external_id))->groupBy(fn ($r) => self::identity($r->external_id))
            ->map(function ($group, $key) use ($item): array {
                $ids = $group->where('match_status', '!=', 'ignored')->pluck('product_id')->filter()->unique();

                return ['external_id' => $key, 'name' => $group->first()->name ?: $item->description,
                    'article' => $group->first()->external_sku ?: $item->article,
                    'product_id' => $ids->count() === 1 ? $ids->first() : null,
                    'current' => $group->filter(fn ($r) => $r->product_id)->map(fn ($r) => ($r->product?->sku ?: '#'.$r->product_id).' — '.($r->product?->name ?: 'Карточка удалена'))->unique()->implode('; ')];
            })->values()->all();
        if ($links === []) {
            $links[] = ['external_id' => $item->ref_key, 'name' => $item->description, 'article' => $item->article, 'product_id' => null, 'current' => ''];
        }

        return ['snapshot' => $this->fingerprint($rows), 'links' => $links];
    }

    public function save(OneCItem $item, array $data, User $actor): void
    {
        abort_unless($actor->isManager(), 403);
        DB::transaction(function () use ($item, $data): void {
            $locked = OneCItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->is_folder || $locked->deletion_mark) {
                $this->fail('Группу или удалённый товар нельзя привязать.');
            }
            $rows = $this->query($locked->ref_key)->lockForUpdate()->get();
            if (! hash_equals($this->fingerprint($rows), (string) ($data['snapshot'] ?? ''))) {
                $this->fail('Привязки уже изменились. Закройте окно и откройте заново.');
            }
            $groups = $rows->groupBy(fn ($r) => self::identity($r->external_id) ?? 'invalid');
            $expected = $groups->keys()->sort()->values()->all() ?: [$locked->ref_key];
            $submitted = collect($data['links'] ?? []);
            if ($submitted->pluck('external_id')->sort()->values()->all() !== $expected) {
                $this->fail('Состав характеристик изменился. Откройте окно заново.');
            }
            $hasVariants = collect($expected)->contains(fn ($key) => str_contains($key, '#'));
            $selected = [];
            foreach ($submitted as $link) {
                $key = (string) $link['external_id'];
                $productId = filled($link['product_id'] ?? null) ? (int) $link['product_id'] : null;
                if (! self::identity($key)) {
                    $this->fail('Некорректный идентификатор 1С.');
                }
                if ($hasVariants && ! str_contains($key, '#') && $productId) {
                    $this->fail('Есть отдельные характеристики: снимите общую привязку и выберите карточку для каждого исполнения.');
                }
                if ($productId) {
                    if (in_array($productId, $selected, true)) {
                        $this->fail('Разные характеристики нельзя привязать к одной карточке. Выберите карточки соответствующих исполнений.');
                    }
                    $product = Product::query()->find($productId);
                    if (! $product || $product->is_archived) {
                        $this->fail('Выбранная карточка удалена или архивирована.');
                    }
                    $selected[] = $productId;
                }
                $targets = $groups->get($key, collect());
                if ($targets->isEmpty() && ! $productId) {
                    continue;
                }
                if ($targets->isEmpty()) {
                    $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
                    $targets = collect([new IntegrationProduct(['integration_source_id' => $source->id, 'external_id' => $key,
                        'external_code' => $locked->code, 'external_sku' => $locked->article, 'name' => $locked->description, 'price' => 0])]);
                }
                foreach ($targets as $target) {
                    if ((int) $target->product_id === (int) $productId && ($productId ? $target->match_status === 'matched' : $target->match_status === 'ignored')) {
                        continue;
                    }
                    $target->forceFill(['product_id' => $productId, 'match_status' => $productId ? 'matched' : 'ignored',
                        'match_method' => $productId ? 'manual_onec_catalog' : 'manual_onec_unlink', 'match_confidence' => $productId ? 1 : null,
                        'matched_at' => $productId ? now() : null])->save();
                }
            }
            $this->refresh($locked->ref_key);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['links' => $message]);
    }

    public function refresh(string $key): void
    {
        DB::table('onec_items')->where('ref_key', $key)->update(self::summary($this->query($key)->get(['external_id', 'product_id', 'match_status'])));
    }

    public function changed(IntegrationProduct $row, bool $deleted = false, bool $created = false): void
    {
        if ($row->source?->code !== 'onec' || ! Schema::hasColumn('onec_items', 'site_link_count')) {
            return;
        }
        $old = $created ? null : $row->getRawOriginal('product_id');
        $new = $deleted ? null : $row->product_id;
        if ((int) $old !== (int) $new) {
            DB::table('onec_item_link_changes')->insert([
                'external_id' => $row->external_id, 'integration_product_id' => $row->id,
                'old_product_id' => $old, 'new_product_id' => $new, 'user_id' => auth()->id(), 'created_at' => now(),
            ]);
        }
        foreach (array_unique([$row->external_id, $row->getRawOriginal('external_id')]) as $identity) {
            if ($canonical = self::identity((string) $identity)) {
                $this->refresh(explode('#', $canonical)[0]);
            }
        }
    }
}
