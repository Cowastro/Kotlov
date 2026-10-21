<?php

namespace App\Services\Suppliers;

use RuntimeException;

class AzbukaTeplaPriceList
{
    public const RETAIL_SOURCE_URL = 'https://docs.google.com/spreadsheets/d/1dXDdXhq26L9WtWxjac5kyzpKZXu0RjlQAQntHtWCS_k/edit?gid=2045946143#gid=2045946143';

    /** @return array<string,mixed> */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Снимок прайса Азбуки Тепла не найден: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ! isset($payload['rows']) || ! is_array($payload['rows'])) {
            throw new RuntimeException('Некорректный снимок прайса Азбуки Тепла.');
        }

        foreach ($payload['rows'] as $index => &$row) {
            foreach (['brand', 'model', 'description', 'retail_byn', 'purchase_byn'] as $field) {
                if (! isset($row[$field]) || trim((string) $row[$field]) === '') {
                    throw new RuntimeException("В строке {$index} отсутствует поле {$field}.");
                }
            }
            if ((float) $row['retail_byn'] <= 0 || (float) $row['purchase_byn'] <= 0) {
                throw new RuntimeException("В строке {$index} указана неположительная цена.");
            }

            $row['brand'] = trim((string) $row['brand']);
            $row['model'] = trim((string) $row['model']);
            $row['description'] = trim((string) $row['description']);
            $row['retail_byn'] = round((float) $row['retail_byn'], 2);
            $row['purchase_byn'] = round((float) $row['purchase_byn'], 2);
            $row['article'] = $row['brand'].' '.$row['model'];
            $row['article_key'] = $this->key($row['article']);
            $row['model_key'] = $this->key($row['model']);
            if ($row['article_key'] === '' || $row['model_key'] === '') {
                throw new RuntimeException("В строке {$index} не удалось определить модель.");
            }
        }
        unset($row);

        return $payload;
    }

    public function key(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = strtr($value, [
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T',
            'Х' => 'X', 'У' => 'Y',
        ]);

        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }
}
