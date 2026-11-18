<?php

namespace App\Services\Suppliers;

class TdKomplektReviewedSpecs
{
    public function entries(): array
    {
        return json_decode(file_get_contents(resource_path('data/td-komplekt-reviewed-specs.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function forRow(array $row): array
    {
        $entry = $this->entries()[trim($row['vendor_code'] ?? '')] ?? [];

        return $entry && TdKomplektMatcher::brandKey($entry['brand']) === TdKomplektMatcher::brandKey($row['brand'] ?? '') ? $entry : [];
    }
}
