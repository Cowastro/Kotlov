<?php

namespace App\Services\PriceList;

class BelomoPriceList
{
    /** @return array<int, array{article:string,name:string,center_distance_mm:?int,flow_direction:?string,partner_price_byn:float}> */
    public function rows(?string $path = null): array
    {
        $path ??= database_path('data/supplier-price-lists/belomo-2026-09-15.csv');
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть зафиксированный прайс БелОМО: {$path}");
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Прайс БелОМО пуст.');
        }
        $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");

        $rows = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if (count($values) !== count($headers)) {
                fclose($handle);
                throw new \RuntimeException('Некорректная строка в прайсе БелОМО.');
            }
            $row = array_combine($headers, $values);
            $rows[] = [
                'article' => trim((string) $row['article']),
                'name' => trim((string) $row['name']),
                'center_distance_mm' => trim((string) $row['center_distance_mm']) === '' ? null : (int) $row['center_distance_mm'],
                'flow_direction' => trim((string) $row['flow_direction']) ?: null,
                'partner_price_byn' => round((float) $row['partner_price_byn'], 2),
            ];
        }
        fclose($handle);

        return $rows;
    }

    public function compactArticle(string $article): string
    {
        return mb_strtoupper(preg_replace('/[^\pL\pN]+/u', '', str_replace('*', '', $article)) ?? '');
    }

    public function shortArticle(string $article): string
    {
        $compact = $this->compactArticle($article);
        if (! preg_match('/^(\d{4}).*(\d{2})$/', $compact, $match)) {
            return $compact;
        }

        return $match[1].$match[2];
    }
}
