<?php

namespace App\Support\Catalog;

class CardSpecificationSummary
{
    /** Preserve the supplied facts; select, never infer or relabel nominal as maximum. */
    public static function select(array $facts): array
    {
        $facts = array_filter($facts, fn ($value) => is_scalar($value) && trim((string) $value) !== '');
        unset($facts['Данные']);
        $pump = array_intersect_key($facts, array_flip(['Макс. напор', 'Макс. расход', 'Номин. напор', 'Номин. расход', 'Мощность двигателя', 'Потребляемая мощность']));
        if ($pump !== []) {
            // One head and flow measurement each; extra duties stay on the product page.
            $selected = [];
            foreach ([['Макс. напор', 'Номин. напор', 'Напор по таблице'], ['Макс. расход', 'Номин. расход', 'Расход по таблице'], ['Мощность двигателя', 'Потребляемая мощность']] as $keys) {
                foreach ($keys as $key) {
                    if (isset($facts[$key])) {
                        $selected[$key] = $facts[$key];
                        break;
                    }
                }
            }
            $remaining = array_diff_key($facts, array_flip(['Макс. напор', 'Номин. напор', 'Напор по таблице', 'Макс. расход', 'Номин. расход', 'Расход по таблице', 'Мощность двигателя', 'Потребляемая мощность']));
            $facts = $selected + $remaining;
        }

        return array_slice($facts, 0, 3, true);
    }
}
