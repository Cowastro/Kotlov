<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\CommercialDocumentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommercialDocumentRevisionComparison
{
    /**
     * @return array{
     *     previous: CommercialDocument|null,
     *     changes: array<int, array<string, mixed>>,
     *     total_delta: float,
     *     subtotal_delta: float
     * }
     */
    public function compare(CommercialDocument $document): array
    {
        $previous = $this->previousRevision($document);

        if (! $previous) {
            return [
                'previous' => null,
                'changes' => [],
                'total_delta' => 0.0,
                'subtotal_delta' => 0.0,
            ];
        }

        $document->loadMissing('items');
        $previous->loadMissing('items');

        $before = $this->keyItems($previous->items);
        $after = $this->keyItems($document->items);
        $changes = [];

        foreach ($after as $key => $item) {
            if (! $before->has($key)) {
                $changes[] = $this->change('added', null, $item);

                continue;
            }

            $oldItem = $before->get($key);
            $changedFields = $this->changedFields($oldItem, $item);

            if ($changedFields !== []) {
                $changes[] = $this->change('changed', $oldItem, $item, $changedFields);
            }
        }

        foreach ($before as $key => $item) {
            if (! $after->has($key)) {
                $changes[] = $this->change('removed', $item, null);
            }
        }

        return [
            'previous' => $previous,
            'changes' => $changes,
            'total_delta' => round((float) $document->total - (float) $previous->total, 2),
            'subtotal_delta' => round((float) $document->subtotal - (float) $previous->subtotal, 2),
        ];
    }

    public function previousRevision(CommercialDocument $document): ?CommercialDocument
    {
        $revisionNumber = (int) ($document->revision_number ?: 1);

        if ($revisionNumber <= 1) {
            return null;
        }

        $rootId = $document->revision_of_id ?: $document->id;

        return CommercialDocument::query()
            ->where(function ($query) use ($rootId): void {
                $query->whereKey($rootId)->orWhere('revision_of_id', $rootId);
            })
            ->where('revision_number', '<', $revisionNumber)
            ->orderByDesc('revision_number')
            ->first();
    }

    /** @return Collection<int, CommercialDocument> */
    public function history(CommercialDocument $document): Collection
    {
        $rootId = $document->revision_of_id ?: $document->id;

        return CommercialDocument::query()
            ->where(function ($query) use ($rootId): void {
                $query->whereKey($rootId)->orWhere('revision_of_id', $rootId);
            })
            ->orderBy('revision_number')
            ->get();
    }

    /** @param Collection<int, CommercialDocumentItem> $items */
    private function keyItems(Collection $items): Collection
    {
        $occurrences = [];

        return $items->mapWithKeys(function (CommercialDocumentItem $item) use (&$occurrences): array {
            $identity = $this->itemIdentity($item);
            $occurrences[$identity] = ($occurrences[$identity] ?? 0) + 1;

            return [$identity.'#'.$occurrences[$identity] => $item];
        });
    }

    private function itemIdentity(CommercialDocumentItem $item): string
    {
        if ($item->product_id) {
            return 'product:'.$item->product_id;
        }

        if (filled($item->sku)) {
            return 'sku:'.Str::lower(trim((string) $item->sku));
        }

        return 'name:'.Str::lower(trim((string) $item->name));
    }

    /** @return array<int, string> */
    private function changedFields(CommercialDocumentItem $before, CommercialDocumentItem $after): array
    {
        $fields = ['name', 'sku', 'unit', 'quantity', 'unit_price', 'discount_percent', 'line_total'];

        return array_values(array_filter($fields, function (string $field) use ($before, $after): bool {
            if (in_array($field, ['quantity', 'unit_price', 'discount_percent', 'line_total'], true)) {
                return abs((float) $before->{$field} - (float) $after->{$field}) > 0.0001;
            }

            return trim((string) $before->{$field}) !== trim((string) $after->{$field});
        }));
    }

    /**
     * @param  array<int, string>  $changedFields
     * @return array<string, mixed>
     */
    private function change(
        string $type,
        ?CommercialDocumentItem $before,
        ?CommercialDocumentItem $after,
        array $changedFields = [],
    ): array {
        $item = $after ?: $before;

        return [
            'type' => $type,
            'name' => (string) $item?->name,
            'sku' => $item?->sku,
            'before' => $before,
            'after' => $after,
            'changed_fields' => $changedFields,
        ];
    }
}
