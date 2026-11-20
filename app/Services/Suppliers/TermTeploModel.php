<?php

namespace App\Services\Suppliers;

class TermTeploModel
{
    public static function key(string $name, string $context = ''): ?string
    {
        $brand = mb_strtoupper($name.' '.$context);
        if (preg_match('/\b(?:HAIER|ХАЙЕР)\b/u', $brand)) {
            $model = HaierTechnicalCatalog::model($name);

            return $model ? 'haier:'.$model : null;
        }
        if (preg_match('/\bKRATS\b/ui', $brand)) {
            $name = trim(mb_strtoupper(str_replace('ё', 'е', $name)));
            if (! preg_match('/^(?:(?:КОТ[ЕЁ]Л|ЭЛЕКТРИЧЕСКИЙ|НАСТЕННЫЙ|ЭЛЕКТРОКОТ[ЕЁ]Л)\s+)*(?:KRATS\b|KEB-)/u', $name)
                || preg_match('/\b(?:ДЛЯ|КОМПЛЕКТ|СОВМЕСТИМ|ИЛИ)\b/u', $name)
                || preg_match_all('/\bKEB-/u', $name) > 1 || preg_match_all('/\d+\s*КВТ/u', $name) > 1) {
                return null;
            }
            if (preg_match('/\bKRATS\s+(?:(ECO)\s+)?(\d+)\s*КВТ\b/u', $name, $power)) {
                $article = 'KEB-'.$power[2].'VW'.($power[1] === 'ECO' ? '-ECO' : '');
                if (str_contains($name, 'KEB-') && ! preg_match('/\b'.preg_quote($article, '/').'(?![\w-])/u', $name)) {
                    return null;
                }

                return 'krats:'.$article;
            }
            if (preg_match('/\b(KEB-\d+VW(?:-ECO)?)(?![\w-])/u', $name, $part)) {
                return 'krats:'.$part[1];
            }
        }
        if (preg_match('/\bARDERIA\b/ui', $brand) && preg_match('/^(?:электрический\s+кот[её]л\s+|кот[её]л\s+электрический\s+)?(?:Arderia\s+)?E\s*(\d+)\s*$/iu', trim($name), $match)) {
            return 'arderia:E'.$match[1];
        }

        return null;
    }

    public static function article(string $article, string $name): string
    {
        // KRATS passport, p. 6: 12 kW ECO and 24 kW Grundfos have different articles.
        return strtoupper(trim($article)) === 'KEB-24VW' && self::key($name) === 'krats:KEB-12VW-ECO'
            ? 'KEB-12VW-ECO' : $article;
    }

    /** Coarser signature for the review report only. Never used to link stock or prices. */
    public static function family(string $name, string $context = ''): ?string
    {
        if (! preg_match('/\b(?:HAIER|ХАЙЕР)\b/ui', $name.' '.$context)) {
            return null;
        }
        if (preg_match('/\b(ProLine|GreenLine|TechLine|EliteLine|EvoLine|Urban)\s*(?:Eco|S)?\s*(?:[12]\.)?(\d{2})\b/ui', $name, $m)
            || preg_match('/\b(ProLine|GreenLine|TechLine|EliteLine|EvoLine|Urban)\s*(?:Eco|S)?\s*[12]\.(\d{2})(?=[A-Za-z])/ui', $name, $m)) {
            return 'haier:'.strtoupper($m[1]).':'.$m[2];
        }
        if (preg_match('/\b(ES\d+[VH])-/iu', $name, $m)) {
            return 'haier:'.strtoupper($m[1]);
        }

        return null;
    }
}
