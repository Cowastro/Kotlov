<?php

namespace App\Support\Catalog;

/**
 * Обозначения вариантов исполнения ультразвуковых счетчиков газа БелОМО «КАТА» и «СКАТ».
 * Источник: таблицы соответствия обозначений 8348.00.00.000 ТБ и 8349.00.00.000 ТБ (ред. 12.11.2025).
 */
final class BelomoGasMeterDesignations
{
    public const PAGE_URL = '/partners/belomo-kata-skat-oboznacheniya';

    public const PDF_URL = '/docs/belomo-kata-skat-varianty-ispolneniya.pdf';

    public const CSV_URL = '/partners/belomo-kata-skat-oboznacheniya.csv';

    public const OPTIONS = [
        'B' => 'с цифровым выходом',
        'R' => 'с каналом беспроводной передачи данных (NB-IoT/GSM)',
        'RK' => 'с каналом беспроводной передачи данных (NB-IoT/GSM) и клапаном',
        'P' => 'с датчиком давления',
        'BP' => 'с цифровым выходом и датчиком давления',
        'RP' => 'с каналом беспроводной передачи данных (NB-IoT/GSM) и датчиком давления',
        'RKP' => 'с каналом беспроводной передачи данных (NB-IoT/GSM), клапаном и датчиком давления',
    ];

    public const THREADS = [
        1 => 'G¾',
        2 => 'G1',
        3 => 'G1¼',
    ];

    public const ADAPTERS = [
        'pc' => 'с адаптером для подключения ПК',
        'modbus' => 'с адаптером для подключения по протоколу Modbus',
    ];

    public static function models(): array
    {
        return [
            'kata' => [
                'name' => 'КАТА',
                'title' => 'Счетчик газа ультразвуковой КАТА',
                'base' => '8348.00.00.000',
                'tu' => 'ТУ BY 100185185.277-2023',
                'pattern' => 'КАТА – [типоразмер] [доп. устройство] – [резьба]',
                'sizes' => ['G4', 'G6'],
                'options' => ['B', 'R', 'RK'],
                'has_thread' => true,
            ],
            'skat' => [
                'name' => 'СКАТ',
                'title' => 'Счетчик газа ультразвуковой СКАТ',
                'base' => '8349.00.00.000',
                'tu' => 'ТУ BY 100185185.281-2023',
                'pattern' => 'СКАТ – [типоразмер] [доп. устройство]',
                'sizes' => ['G6', 'G10'],
                'options' => ['B', 'R', 'RK', 'P', 'BP', 'RP', 'RKP'],
                'has_thread' => false,
            ],
        ];
    }

    /**
     * @return list<array{model:string, code:string, size:string, option:?string, thread:?int, network:?string, adapter:?string, name:string}>
     */
    public static function variants(): array
    {
        return [...self::kataVariants(), ...self::skatVariants()];
    }

    private static function kataVariants(): array
    {
        $rows = [];

        foreach (['G4' => 0, 'G6' => 20] as $size => $offset) {
            // -00…-11 / -20…-31: базовое, B, R, RK (NB-IoT) для каждой резьбы
            foreach (self::THREADS as $thread => $_) {
                foreach ([null, 'B', 'R', 'RK'] as $i => $option) {
                    $network = in_array($option, ['R', 'RK'], true) ? 'NB-IoT' : null;
                    $rows[] = self::kata($offset + ($thread - 1) * 4 + $i, $size, $option, $thread, $network);
                }
            }

            // -12…-17 / -32…-37: B с адаптером ПК, затем Modbus
            foreach (['pc' => 12, 'modbus' => 15] as $adapter => $start) {
                foreach (self::THREADS as $thread => $_) {
                    $rows[] = self::kata($offset + $start + $thread - 1, $size, 'B', $thread, null, $adapter);
                }
            }
        }

        // -40…-45 / -50…-55: R и RK с GSM
        foreach (['G4' => 40, 'G6' => 50] as $size => $start) {
            foreach (self::THREADS as $thread => $_) {
                foreach (['R', 'RK'] as $i => $option) {
                    $rows[] = self::kata($start + ($thread - 1) * 2 + $i, $size, $option, $thread, 'GSM');
                }
            }
        }

        return $rows;
    }

    private static function skatVariants(): array
    {
        $rows = [];

        // -00…-07 / -10…-17
        foreach (['G6' => 0, 'G10' => 10] as $size => $offset) {
            foreach ([null, 'B', 'R', 'RK', 'P', 'BP', 'RP', 'RKP'] as $i => $option) {
                $network = $option !== null && str_starts_with($option, 'R') ? 'NB-IoT' : null;
                $rows[] = self::skat($offset + $i, $size, $option, $network);
            }
        }

        // -20…-23 / -24…-27: B и BP с адаптером ПК / Modbus
        foreach (['G6' => 20, 'G10' => 24] as $size => $start) {
            $code = $start;
            foreach (['pc', 'modbus'] as $adapter) {
                foreach (['B', 'BP'] as $option) {
                    $rows[] = self::skat($code++, $size, $option, null, $adapter);
                }
            }
        }

        // -30…-33 / -34…-37: R, RK, RP, RKP с GSM
        foreach (['G6' => 30, 'G10' => 34] as $size => $start) {
            foreach (['R', 'RK', 'RP', 'RKP'] as $i => $option) {
                $rows[] = self::skat($start + $i, $size, $option, 'GSM');
            }
        }

        return $rows;
    }

    private static function kata(int $index, string $size, ?string $option, int $thread, ?string $network, ?string $adapter = null): array
    {
        $name = 'Счетчик газа ультразвуковой КАТА – ' . $size . ($option ? ' ' . $option : '') . ' – ' . $thread;

        return self::row('kata', '8348.00.00.000', $index, $size, $option, $thread, $network, $adapter, $name);
    }

    private static function skat(int $index, string $size, ?string $option, ?string $network, ?string $adapter = null): array
    {
        $name = 'Счетчик газа ультразвуковой СКАТ – ' . $size . ($option ? ' ' . $option : '');

        return self::row('skat', '8349.00.00.000', $index, $size, $option, null, $network, $adapter, $name);
    }

    private static function row(string $model, string $base, int $index, string $size, ?string $option, ?int $thread, ?string $network, ?string $adapter, string $name): array
    {
        if ($network) {
            $name .= ' (' . $network . ')';
        }
        if ($adapter) {
            $name .= ' ' . self::ADAPTERS[$adapter];
        }

        return [
            'model' => $model,
            'code' => $index === 0 ? $base : $base . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'size' => $size,
            'option' => $option,
            'thread' => $thread,
            'network' => $network,
            'adapter' => $adapter,
            'name' => $name,
        ];
    }
}
