<?php

namespace App\Services\Shipping;

use DOMDocument;
use DOMXPath;

class VamExpressHtml
{
    public function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return new DOMXPath($dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function hidden(string $html): array
    {
        $fields = [];
        foreach ($this->xpath($html)->query('//input[@type="hidden"][@name]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return $fields;
    }

    public function rows(string $html): array
    {
        $xpath = $this->xpath($html);
        $headers = $xpath->query('//th');
        $text = implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($headers)));
        if (! str_contains($text, 'Город доставки') || ! str_contains($text, 'Статус')) {
            throw new VamExpressException('Не удалось прочитать список заявок. Проверьте данные входа; возможно, перевозчик изменил страницу.');
        }
        $rows = [];
        foreach ($xpath->query('//a[contains(@href,"TicketEdit.aspx?id=")]') as $link) {
            $href = html_entity_decode($link->getAttribute('href'));
            if (! preg_match('/^TicketEdit\.aspx\?id=([a-f0-9]{32})$/i', $href, $id)) {
                throw new VamExpressException('Неожиданный формат ID заявки ВАМ Экспресс.');
            }
            $cells = $xpath->query('ancestor::tr[1]/td', $link);
            if ($cells->length !== 11) {
                throw new VamExpressException('Изменился формат таблицы ВАМ Экспресс. Данные сохранены без изменений.');
            }
            $value = fn (int $index): string => trim(preg_replace('/\s+/u', ' ', $cells->item($index)->textContent) ?? '');
            $rows[] = ['remote_id' => strtolower($id[1]), 'display_id' => trim($link->textContent),
                'city' => $value(2), 'cargo' => $value(3), 'total_amount' => $this->money($value(4)),
                'delivery_cost' => $this->money($value(5)), 'remote_created_at' => $this->date($value(6)),
                'accepted_at' => $this->date($value(7)), 'dispatch_at' => $this->date($value(8)), 'carrier_status' => $value(9)];
        }

        return $rows;
    }

    public function nextPageTarget(string $html, int $number): ?string
    {
        foreach ($this->xpath($html)->query('//a') as $link) {
            if (trim($link->textContent) === (string) $number
                && preg_match('/^javascript:__doPostBack\(\'(ctl00\$MainContent\$lvDataPager\$[a-zA-Z0-9$]+)\',\'\'\)$/', $link->getAttribute('href'), $match)) {
                return $match[1];
            }
        }

        return null;
    }

    private function money(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace(["\xc2\xa0", ' '], '', $value);
        $value = preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $value)
            ? str_replace(',', '', $value)
            : str_replace(',', '.', $value);
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            throw new VamExpressException('Перевозчик вернул неизвестный формат стоимости.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value);
        if (! $date || $date->format('d.m.Y') !== $value) {
            throw new VamExpressException('Перевозчик вернул неизвестный формат даты.');
        }

        return $date->format('Y-m-d');
    }
}
