<?php

namespace App\Console\Commands\Concerns;

/**
 * Heuristic detector for "bad" generated content: measurement units left
 * without a number (e.g. "Мощность Вт", "до м²", "~В", "уровень шума дБ").
 * These appear when a description was built/generated from empty spec values.
 */
trait DetectsBadContent
{
    /** Distinctive units — flagged whenever they appear as an orphan token. */
    private array $distinctiveUnits = ['кВт', 'Вт', 'м³/час', 'м³/ч', 'м3/час', 'об/мин', 'дБ', 'м²', 'м2', 'мм'];

    /** Ambiguous short units — only flagged after a value separator (~, :, —, до …). */
    private array $ambiguousUnits = ['В', 'ч', 'л', 'см', 'кг', 'м'];

    /**
     * Return distinct orphan-unit snippets found in the content (empty = clean).
     *
     * @return string[]
     */
    protected function badContentMatches(?string $html): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');
        if ($text === '') {
            return [];
        }

        $hits = [];

        foreach (array_merge($this->distinctiveUnits, $this->ambiguousUnits) as $unit) {
            $ambiguous = in_array($unit, $this->ambiguousUnits, true);
            // Token boundaries: not inside a word/number, not followed by a letter.
            $re = '/(?<![\p{L}\d])' . preg_quote($unit, '/') . '(?![\p{L}])/u';
            if (! preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[0] as [$tok, $bytePos]) {
                $before = rtrim(mb_strcut($text, 0, $bytePos));
                $prev   = mb_substr($before, -1);

                if ($prev !== '' && preg_match('/\d/u', $prev)) {
                    continue; // has a number → fine ("150 Вт")
                }

                if ($ambiguous) {
                    // Require a value-slot separator just before, else skip (avoids
                    // false hits like "класс В", "в комнате").
                    if (! preg_match('/(?:[:~—–\-(]|\b(?:до|от|на|около|свыше))\s*$/u', mb_substr($before, -10))) {
                        continue;
                    }
                }

                $snippet = trim(mb_substr($before, -20) . ' [' . $unit . '] ' . mb_substr(mb_strcut($text, $bytePos + strlen($tok)), 0, 12));
                $hits[$unit] = preg_replace('/\s+/u', ' ', $snippet);
                break; // one example per unit is enough
            }
        }

        return array_values($hits);
    }

    protected function hasBadContent(?string $html): bool
    {
        return $this->badContentMatches($html) !== [];
    }
}
