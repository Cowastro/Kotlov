<?php

namespace App\Support;

/**
 * One naming scheme for Теплов и Сухов chimney parts. Cards came from several price lists, so the same
 * elbow reads «Отвод моно ТиС ОМ-Р 45*, 430, 0.8, Ø 150», «ТиС Отвод 45° ОМ-Р 430-0.8 D115 (2S)» or
 * «Отвод ТиС 45 OM-Р AISI 304 0,8-120». The scheme:
 *
 *   <изделие> [Моно|Термо] Теплов и Сухов [Стандарт 30|50] <код> [угол°] [дополнение], [L…], <сталь-толщина>, [покрытие], Ø <диаметр>[, с хомутом…]
 *   «Отвод Моно Теплов и Сухов ОМ-Р 45°, 430-0,8, Ø 150»
 *   «Труба Термо Теплов и Сухов Стандарт 50 ТТ-Р, L1000, 304-0,8/304, Ø 150/250, с хомутом»
 *
 * Manufacturer variant marks (2S/3S, М, У, ФМ…) stay in the article and 1C, not in the name.
 * A name whose kind, steel or diameter is not recognised is left as it is (normalize() returns null).
 */
class TeplovSukhovName
{
    public const BRAND = 'Теплов и Сухов';

    /** Kind of item as written => as named. Longer phrases first. */
    private const KINDS = [
        'Шибер-переход Моно/Термо' => 'Шибер-переход Моно/Термо',
        'Шибер - переход Моно-Термо' => 'Шибер-переход Моно/Термо',
        'Шибер-переход Моно-Термо' => 'Шибер-переход Моно/Термо',
        'Шибер-задвижка' => 'Шибер-задвижка',
        'Шибер' => 'Шибер',
        'Зонт-Конус Термо' => 'Зонт-конус Термо',
        'Зонт' => 'Зонт',
        'Дефлектор' => 'Дефлектор',
        'Отвод' => 'Отвод',
        'Тройник' => 'Тройник',
        'Четверик' => 'Четверик',
        'Труба телескопическая' => 'Труба телескопическая',
        'Труба Телескоп' => 'Труба телескопическая',
        'Труба телескоп' => 'Труба телескопическая',
        'Труба' => 'Труба',
        'Конденсатоотвод' => 'Конденсатоотвод',
        'Конденсатоовод' => 'Конденсатоотвод',
        'Заглушка ревизии' => 'Заглушка ревизии',
        'Площадка сквозная монтажная' => 'Площадка сквозная монтажная',
        'Площадка монтажная' => 'Площадка монтажная',
        'Площадка Монтажная' => 'Площадка монтажная',
        'Адаптер котла' => 'Адаптер котла',
        'Адаптер-переход' => 'Адаптер-переход',
        'Адаптер переход' => 'Адаптер-переход',
        'Адаптер' => 'Адаптер',
        'Конус' => 'Конус',
        'Переход Моно/Термо' => 'Переход Моно/Термо',
        'Переход Моно' => 'Переход Моно',
        'Хомут трубный' => 'Хомут трубный',
        'Хомут растяжки' => 'Хомут растяжки',
        'Хомут штанги регулируемой' => 'Хомут штанги регулируемой',
        'Фартук' => 'Фартук',
        'Крепление универсальное' => 'Крепление универсальное',
        'Крепление настенное' => 'Крепление настенное',
    ];

    /** Kinds that come as single-wall or insulated. */
    private const WITH_SYSTEM = ['Отвод', 'Тройник', 'Четверик', 'Труба', 'Труба телескопическая', 'Конденсатоотвод', 'Заглушка ревизии',
        'Площадка монтажная', 'Адаптер', 'Адаптер котла', 'Адаптер-переход', 'Конус', 'Шибер', 'Зонт', 'Дефлектор'];

    private const ANGLED = ['Отвод', 'Тройник'];

    private const STEELS = '(?:201|304|310|316|321|430)';

    private const LATIN = ['A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'K' => 'К', 'M' => 'М', 'O' => 'О', 'P' => 'Р', 'T' => 'Т', 'X' => 'Х'];

    public static function normalize(string $name): ?string
    {
        $s = ' '.trim(preg_replace('/\s+/u', ' ', html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'))).' ';
        if (preg_match('/ТермоВент|Феррит|ППШ|Бак|Титан|Теплообменник|ЛПУ|ППУ|ЛПУР|ККО|прямоугольник|овала/u', $s)) {
            return null;
        }
        $s = preg_replace('/(?<!\p{L})(?:Теплов\s+и\s+[Сс]ухов|ТиС)(?!\p{L})/u', ' ', $s);

        $paint = null;
        if (preg_match('/(?:MC\s+)?Black\s*\(?\s*t\s*(?:краски\s*)?<\s*°?\s*(\d{3})\s*°?\s*[СC]\s*\)?/u', $s, $m)) {
            $paint = 'MC Black до '.$m[1].' °С';
            $s = str_replace($m[0], ' ', $s);
        } elseif (preg_match('/MC\s+Black\s+до\s+(\d{3})\s*°\s*С/u', $s, $m)) {
            // Already in the scheme.
            $paint = 'MC Black до '.$m[1].' °С';
            $s = str_replace($m[0], ' ', $s);
        } elseif (preg_match('/ч[её]рный,?\s+до\s+(\d{3})\s*°\s*С/ui', $s, $m)) {
            $paint = 'чёрный, до '.$m[1].' °С';
            $s = str_replace($m[0], ' ', $s);
        } elseif (preg_match('/\(\s*Ч[её]рный\s*\(\s*Т\s*(\d{3})\s*[СC]\*?\s*\)\s*\)/u', $s, $m)) {
            $paint = 'чёрный, до '.$m[1].' °С';
            $s = str_replace($m[0], ' ', $s);
        }

        $series = null;
        if (preg_match('/Стандарт\s+(30|50)/u', $s, $m)) {
            $series = 'Стандарт '.$m[1];
            $s = str_replace($m[0], ' ', $s);
        }

        $extras = [];
        foreach (['/с\s+хомутом\s+на\s+замке/u' => 'с хомутом на замке', '/с\s+хомутом/u' => 'с хомутом', '/\(?керамоволокно\)?/u' => 'керамоволокно',
            '/«Энерго»/u' => '«Энерго»', '/\(?\s*по\s+наруж(?:\.|ному)?\s*диаметру\s*\)?/u' => 'по наружному диаметру'] as $pattern => $label) {
            if (preg_match($pattern, $s, $m)) {
                $extras[] = $label;
                $s = str_replace($m[0], ' ', $s);
            }
        }

        $kind = null;
        $kindAt = PHP_INT_MAX;
        foreach (self::KINDS as $written => $named) {
            $at = mb_stripos($s, ' '.$written.' ');
            $at = $at === false ? mb_stripos($s, ' '.$written) : $at;
            if ($at !== false && $at < $kindAt) {
                [$kind, $kindAt, $kindWritten] = [$named, $at, $written];
            }
        }
        if ($kind === null) {
            return null;
        }
        $s = mb_substr($s, 0, $kindAt).' '.mb_substr($s, $kindAt + mb_strlen(' '.$kindWritten));

        $code = null;
        if (preg_match('/(?<!\p{L})([A-ZА-ЯЁ]{1,5}р?(?:\([МM]\d?\))?)\s*-\s*[РP](?:\s*\(([МM]2)\))?(?!\p{L})/u', $s, $m)) {
            $code = strtr($m[1], self::LATIN).'-Р'.(isset($m[2]) && $m[2] !== '' ? '(М2)' : '');
            $s = str_replace($m[0], ' ', $s);
        }

        $length = null;
        if (preg_match('/(?<!\p{L})L\s*-?\s*\(?\s*(\d{3,4})(?:\s*-\s*(\d{3,4}))?\s*\)?(?:\s*мм)?/u', $s, $m)) {
            $length = 'L'.$m[1].(isset($m[2]) && $m[2] !== '' ? '-'.$m[2] : '');
            $s = str_replace($m[0], ' ', $s);
        }

        $steel = null;
        $diameter = null;
        // «0.50» → «0,5», «1.0» → «1,0», «0,8» stays.
        $thickness = function (string $t): string {
            $value = (float) str_replace(',', '.', $t);

            return str_replace('.', ',', sprintf(round($value, 1) == $value ? '%.1f' : '%.2f', $value));
        };
        if (preg_match('/AISI\s*('.self::STEELS.')\s*(\d[.,]\d+)\s*(?:мм)?\s*-\s*(\d{2,3})(?!\d)/u', $s, $m)) {
            [$steel, $diameter] = [$m[1].'-'.$thickness($m[2]), $m[3]];
            $s = str_replace($m[0], ' ', $s);
        } elseif (preg_match('/\(?\s*(?:AISI\s*)?('.self::STEELS.')\s*(?:-|,)?\s*(\d[.,]\d+)\s*(?:мм)?\s*\/\s*('.self::STEELS.')(?:\s*(?:-|,)?\s*(\d[.,]\d+))?\s*\)?/u', $s, $m)) {
            $steel = $m[1].'-'.$thickness($m[2]).'/'.$m[3].(isset($m[4]) && $m[4] !== '' ? '-'.$thickness($m[4]) : '');
            $s = str_replace($m[0], ' ', $s);
        } elseif (preg_match('/\(?\s*(?:AISI\s*)?('.self::STEELS.')\s*(?:-|,)?\s*(\d[.,]\d+)\s*(?:мм)?\s*\)?/u', $s, $m)) {
            $steel = $m[1].'-'.$thickness($m[2]);
            $s = str_replace($m[0], ' ', $s);
        }

        if ($diameter === null && preg_match('/(?:Ø|⌀|(?<!\p{L})[DdДдф])\s*=?\s*(\d{2,3})(?:\s*([\/-])\s*(\d{2,3}))?(\*)?(?!\d)/u', $s, $m)) {
            $diameter = $m[1].(isset($m[2]) && $m[2] !== '' ? $m[2].$m[3] : '').($m[4] ?? '');
            $s = str_replace($m[0], ' ', $s);
        }
        // The angle goes before a bare trailing number is taken for the diameter («Отвод 45 OM-Р … MC Black»).
        $angle = null;
        if (in_array($kind, self::ANGLED, true) && preg_match('/(?<![\d.,\/])(45|87)\s*(?:\*|°|"|гр\.?)?(?!\d|[.,]\d)/u', $s, $m)) {
            $angle = $m[1].'°';
            $s = str_replace($m[0], ' ', $s);
        }

        if ($diameter === null && preg_match('/(?<![\d.,])(\d{2,3}(?:\/\d{2,3})?)\s*(?:мм)?\s*$/u', trim($s), $m)) {
            $diameter = $m[1];
            $s = preg_replace('/'.preg_quote($m[0], '/').'\s*$/u', ' ', trim($s)).' ';
        }

        $system = null;
        if (preg_match('/(?<!\p{L})(Моно|Термо)(?:-Р)?(?!\p{L})/ui', $s, $m)) {
            $system = mb_convert_case($m[1], MB_CASE_TITLE);
            $s = str_replace($m[0], ' ', $s);
        }
        // A «моно»/«термо» of the old name always stays (the price list link compares it); the kinds made
        // in both versions get it from the code or the steel too.
        if (in_array($kind, self::WITH_SYSTEM, true)) {
            $system ??= match (true) {
                $steel !== null && str_contains($steel, '/') => 'Термо',
                $code !== null && preg_match('/^(ОМ|ТМ|ТРМ|ПММ|ЗРМ|КМ|ШМ|ТТМ|ЗМ|ДМ)/u', $code) === 1 => 'Моно',
                $code !== null && preg_match('/^(ОТ|ТТ|ТРТ|ЗРТ|ЧТ|ЗКТ)/u', $code) === 1 => 'Термо',
                default => null,
            };
        }
        if (str_contains($kind, 'Моно') || str_contains($kind, 'Термо')) {
            $system = null;
        }

        $connection = null;
        if (preg_match('/(?<!\p{L})([МП]-[МП])(?:\s*\(С\))?(?!\p{L})/u', $s, $m)) {
            $connection = $m[0];
            $s = str_replace($m[0], ' ', $s);
        }

        // What is left: variant marks go, words stay («на болте», «(полимер)»).
        $rest = preg_replace(['/\((?:2S|3S)\)/u', '/\(\s*(?:М|У\d?|М\s*У)\s*\)/u', '/(?<!\p{L})(?:М|У\d?|ФМ|ФР|ТФ|ТФ-ФР)(?!\p{L})/u', '/[-–,.*]+/u', '/(?<!\p{L})мм(?!\p{L})/u'], ' ', $s);
        $rest = trim(preg_replace('/\s+/u', ' ', $rest));
        if (preg_match('/\d/u', $rest) || ($steel === null && $paint === null && $diameter === null)) {
            // Numbers we could not place: do not guess.
            return null;
        }

        $head = implode(' ', array_filter([$kind, $system, self::BRAND, $series, $code, $angle, $connection, $rest !== '' ? $rest : null]));
        $specs = array_filter([$length, $steel, $paint, $diameter !== null ? 'Ø '.$diameter : null]);

        return trim($head.($specs ? ', '.implode(', ', $specs) : '').($extras ? ', '.implode(', ', $extras) : ''));
    }
}
