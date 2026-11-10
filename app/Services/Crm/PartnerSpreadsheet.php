<?php

namespace App\Services\Crm;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Partners to Excel and back, so a manager can bring the cards up to date in a spreadsheet. The «ID» column ties a
 * row to its card; a row without ID becomes a new card. Company phones and e-mails are compared with the card:
 * new ones are added, crossed-out ones are switched off (never deleted). Contact persons are shown for reference only.
 */
class PartnerSpreadsheet
{
    /** Column title => counterparty attribute (or a virtual key). */
    public const COLUMNS = [
        'ID' => 'id',
        'Название' => 'name',
        'Юридическое название' => 'legal_name',
        'УНП' => 'unp',
        'Категория' => 'classification',
        'Статус' => 'status',
        'Подразделение' => 'business_unit',
        'Регион' => 'business_region',
        'Ответственный' => 'responsible',
        'Сайт' => 'website',
        'Адрес' => 'legal_address',
        'Телефоны' => 'phones',
        'E-mail' => 'emails',
        'Контактные лица (не меняется при загрузке)' => 'contacts',
        'Скидка, %' => 'commercial_discount_percent',
        'Условия оплаты' => 'commercial_payment_terms',
        'Условия поставки' => 'commercial_delivery_terms',
        'Заметки' => 'notes',
    ];

    private const READ_ONLY = ['id', 'contacts'];

    private const CHOICES = [
        'classification' => CrmCounterparty::CLASSIFICATIONS,
        'status' => CrmCounterparty::STATUSES,
        'business_unit' => CrmCounterparty::BUSINESS_UNITS,
    ];

    /** @param  array<int, string>  $classifications */
    public function export(array $classifications): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Партнёры');
        $titles = array_keys(self::COLUMNS);
        $sheet->fromArray($titles, null, 'A1');

        $row = 2;
        CrmCounterparty::query()->when($classifications !== [], fn ($q) => $q->whereIn('classification', $classifications))
            ->with(['contactPoints', 'contacts.contactPoints', 'responsibleUser'])->orderBy('name')
            ->chunk(500, function (Collection $chunk) use ($sheet, &$row): void {
                foreach ($chunk as $counterparty) {
                    $col = 1;
                    foreach (self::COLUMNS as $key) {
                        // Text cells: УНП and phones must not turn into numbers.
                        $sheet->setCellValueExplicit([$col++, $row], (string) $this->value($counterparty, $key), $key === 'id' ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                    $row++;
                }
            });

        $last = Coordinate::stringFromColumnIndex(count($titles));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE7E7');
        foreach ([1 => 'EEEEEE', array_search('contacts', array_values(self::COLUMNS), true) + 1 => 'EEEEEE'] as $index => $color) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$letter}2:{$letter}".max(2, $row - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:{$last}".max(1, $row - 1));
        foreach (['A' => 8, 'B' => 34, 'C' => 34, 'D' => 12, 'E' => 18, 'F' => 14, 'G' => 22, 'H' => 16, 'I' => 18, 'J' => 22, 'K' => 36, 'L' => 30, 'M' => 30, 'N' => 50, 'O' => 10, 'P' => 28, 'Q' => 28, 'R' => 40] as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
        $this->addChoiceLists($book, $sheet, $row - 1);
        $this->addHelp($book);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/crm-exports/partners-'.now()->format('Y-m-d-His').'-'.bin2hex(random_bytes(3)).'.xlsx');
        is_dir(dirname($path)) || mkdir(dirname($path), 0775, true);
        (new Xlsx($book))->save($path);

        return $path;
    }

    /**
     * @return array{rows:int,updated:int,created:int,unchanged:int,phones_added:int,phones_off:int,emails_added:int,emails_off:int,errors:array<int,string>,changes:array<int,string>}
     */
    public function import(string $path, bool $dryRun, ?int $userId = null): array
    {
        try {
            $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (Throwable) {
            throw new RuntimeException('Не удалось прочитать файл. Загрузите .xlsx, выгруженный кнопкой «Экспорт партнёров».');
        }
        $header = array_map(fn ($t): string => trim((string) $t), (array) array_shift($rows));
        $map = [];
        foreach ($header as $index => $title) {
            if (isset(self::COLUMNS[$title])) {
                $map[self::COLUMNS[$title]] = $index;
            }
        }
        if (! isset($map['id'], $map['name'])) {
            throw new RuntimeException('В первой строке нет колонок «ID» и «Название». Не меняйте заголовки выгруженного файла.');
        }

        $stats = ['rows' => 0, 'updated' => 0, 'created' => 0, 'unchanged' => 0, 'phones_added' => 0, 'phones_off' => 0,
            'emails_added' => 0, 'emails_off' => 0, 'errors' => [], 'changes' => []];
        $users = User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => $id]);

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $cells) {
                $line = $i + 2;
                $get = fn (string $key): ?string => isset($map[$key]) ? (trim((string) ($cells[$map[$key]] ?? '')) ?: null) : '__absent__';
                if (collect($cells)->filter(fn ($c) => trim((string) $c) !== '')->isEmpty()) {
                    continue;
                }
                $stats['rows']++;
                $this->importRow($line, $get, $users, $stats, $userId);
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $stats['errors'] = array_slice($stats['errors'], 0, 30);
        $stats['changes'] = array_slice($stats['changes'], 0, 30);

        return $stats;
    }

    private function importRow(int $line, \Closure $get, Collection $users, array &$stats, ?int $userId): void
    {
        $id = $get('id');
        $name = $get('name');
        if ($name === null) {
            $stats['errors'][] = "Строка {$line}: пустое «Название» — строка пропущена.";

            return;
        }

        $counterparty = null;
        if ($id !== null) {
            $counterparty = ctype_digit($id) ? CrmCounterparty::query()->find((int) $id) : null;
            if (! $counterparty) {
                $stats['errors'][] = "Строка {$line}: карточки с ID {$id} нет (удалена или ID изменён) — строка пропущена.";

                return;
            }
        }

        $attributes = [];
        foreach (self::COLUMNS as $key) {
            if (in_array($key, [...self::READ_ONLY, 'phones', 'emails'], true) || ($value = $get($key)) === '__absent__') {
                continue;
            }
            if (isset(self::CHOICES[$key])) {
                if ($value === null) {
                    continue;
                }
                $choice = $this->choice($key, $value);
                if ($choice === null) {
                    $stats['errors'][] = "Строка {$line}: «{$value}» — нет такого значения в колонке «".array_search($key, self::COLUMNS, true).'», оставлено как было.';

                    continue;
                }
                $value = $choice;
            } elseif ($key === 'responsible') {
                $key = 'responsible_user_id';
                if ($value !== null && ! isset($users[mb_strtolower($value)])) {
                    $stats['errors'][] = "Строка {$line}: сотрудника «{$value}» нет среди менеджеров, ответственный оставлен как был.";

                    continue;
                }
                $value = $value === null ? null : (int) $users[mb_strtolower($value)];
            } elseif ($key === 'commercial_discount_percent') {
                $number = str_replace([',', '%', ' '], ['.', '', ''], (string) $value);
                if ($value !== null && (! is_numeric($number) || $number < 0 || $number > 100)) {
                    $stats['errors'][] = "Строка {$line}: скидка «{$value}» — нужно число от 0 до 100, оставлено как было.";

                    continue;
                }
                $value = $value === null ? 0 : round((float) $number, 2);
            }
            $attributes[$key] = $value;
        }

        if (! $counterparty) {
            $counterparty = CrmCounterparty::query()->create([
                'type' => 'organization', 'classification' => 'b2b_partner', 'business_unit' => 'wholesale', 'status' => 'active',
                'source' => 'manual', 'responsible_user_id' => $userId, ...array_filter($attributes, fn ($v) => $v !== null),
            ]);
            $stats['created']++;
            $stats['changes'][] = "Строка {$line}: новая карточка «{$name}».";
            $changed = true;
        } else {
            $dirty = [];
            foreach ($attributes as $key => $value) {
                $current = $counterparty->getAttribute($key);
                $same = $key === 'commercial_discount_percent' ? (float) $current === (float) $value : (string) $current === (string) $value;
                if (! $same) {
                    $dirty[$key] = $value;
                }
            }
            $changed = $dirty !== [];
            if ($changed) {
                $counterparty->forceFill($dirty)->save();
                $stats['changes'][] = "Строка {$line}: «{$name}» — ".implode(', ', array_map(fn ($k) => $this->title($k), array_keys($dirty))).'.';
            }
        }

        $points = $this->syncPoints($counterparty, $get('phones'), $get('emails'));
        foreach ($points as $key => $count) {
            $stats[$key] += $count;
        }
        if (array_sum($points) > 0 && ! $changed) {
            $stats['changes'][] = "Строка {$line}: «{$name}» — телефоны/e-mail.";
        }
        if ($counterparty->wasRecentlyCreated) {
            return;
        }
        $changed || array_sum($points) > 0 ? $stats['updated']++ : $stats['unchanged']++;
    }

    /** @return array{phones_added:int,phones_off:int,emails_added:int,emails_off:int} */
    private function syncPoints(CrmCounterparty $counterparty, ?string $phones, ?string $emails): array
    {
        $result = ['phones_added' => 0, 'phones_off' => 0, 'emails_added' => 0, 'emails_off' => 0];
        $sets = [];
        if ($phones !== '__absent__') {
            $sets['phone'] = collect(preg_split('/[,;\n]+/u', (string) $phones) ?: [])
                ->flatMap(fn (string $part): array => PhoneNumber::extract($part)['phones'])
                ->mapWithKeys(fn (string $p): array => [(string) PhoneNumber::normalize($p) => $p])->filter(fn ($p, $k) => strlen($k) >= 7);
        }
        if ($emails !== '__absent__') {
            $sets['email'] = collect(preg_split('/[\s,;]+/u', mb_strtolower((string) $emails)) ?: [])
                ->filter(fn (string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)->mapWithKeys(fn (string $e): array => [$e => $e]);
        }

        foreach ($sets as $type => $wanted) {
            $existing = CrmContactPoint::query()->where('counterparty_id', $counterparty->id)->whereNull('contact_id')->where('type', $type)->get();
            foreach ($existing as $point) {
                $keep = $wanted->has($point->normalized_value);
                if ($point->is_active !== $keep) {
                    $point->forceFill(['is_active' => $keep])->save();
                    $result[$type.($keep ? 's_added' : 's_off')]++;
                }
            }
            $known = $existing->pluck('normalized_value')->flip();
            foreach ($wanted as $key => $value) {
                if (! isset($known[$key])) {
                    CrmContactPoint::query()->create(['counterparty_id' => $counterparty->id, 'type' => $type, 'value' => $value, 'is_active' => true,
                        'is_primary' => $existing->isEmpty() && $result[$type.'s_added'] === 0]);
                    $result[$type.'s_added']++;
                }
            }
        }

        return $result;
    }

    private function value(CrmCounterparty $c, string $key): string
    {
        return match ($key) {
            'classification', 'status', 'business_unit' => self::CHOICES[$key][$c->{$key}] ?? (string) $c->{$key},
            'responsible' => (string) $c->responsibleUser?->name,
            'phones' => $c->contactPoints->where('type', 'phone')->where('is_active', true)->whereNull('contact_id')->pluck('value')->unique()->implode(', '),
            'emails' => $c->contactPoints->where('type', 'email')->where('is_active', true)->whereNull('contact_id')->pluck('value')->unique()->implode(', '),
            'contacts' => $c->contacts->where('is_active', '!=', false)->map(fn ($p): string => trim(implode(' — ', array_filter([
                $p->full_name, $p->position, $p->contactPoints->where('is_active', true)->pluck('value')->implode(', '),
            ]))))->filter()->implode("; \n"),
            'commercial_discount_percent' => $c->commercial_discount_percent !== null && (float) $c->commercial_discount_percent > 0 ? rtrim(rtrim((string) $c->commercial_discount_percent, '0'), '.') : '',
            default => (string) $c->getAttribute($key),
        };
    }

    private function choice(string $key, string $value): ?string
    {
        $options = self::CHOICES[$key];
        if (isset($options[$value])) {
            return $value;
        }
        $found = array_search(mb_strtolower($value), array_map('mb_strtolower', $options), true);

        return $found === false ? null : (string) $found;
    }

    private function title(string $key): string
    {
        $key = $key === 'responsible_user_id' ? 'responsible' : $key;

        return mb_strtolower((string) array_search($key, self::COLUMNS, true));
    }

    /** Drop-down lists for Категория / Статус / Подразделение. */
    private function addChoiceLists(Spreadsheet $book, $sheet, int $lastRow): void
    {
        $lists = $book->createSheet()->setTitle('Списки');
        $columns = array_values(self::COLUMNS);
        $i = 1;
        foreach (self::CHOICES as $key => $options) {
            $letter = Coordinate::stringFromColumnIndex($i++);
            $lists->setCellValue("{$letter}1", array_search($key, self::COLUMNS, true));
            $lists->fromArray(array_map(fn ($o) => [$o], array_values($options)), null, "{$letter}2");
            $target = Coordinate::stringFromColumnIndex(array_search($key, $columns, true) + 1);
            $validation = $sheet->getCell("{$target}2")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                ->setShowErrorMessage(true)->setErrorTitle('Выберите из списка')->setError('Значение берётся из списка.')
                ->setFormula1("'Списки'!\${$letter}\$2:\${$letter}\$".(count($options) + 1));
            $sheet->setDataValidation("{$target}2:{$target}".max(2, $lastRow + 500), clone $validation);
        }
        $lists->getColumnDimension('A')->setWidth(22);
        $lists->getColumnDimension('B')->setWidth(18);
        $lists->getColumnDimension('C')->setWidth(32);
    }

    private function addHelp(Spreadsheet $book): void
    {
        $help = $book->createSheet()->setTitle('Как заполнять');
        $lines = [
            'Как обновить партнёров',
            '1. Правьте ячейки на листе «Партнёры». Заголовки и колонку «ID» не меняйте — по ID строка находит свою карточку.',
            '2. Новый партнёр — новая строка с пустым ID. Будет создана карточка «B2B-партнёр».',
            '3. Телефоны и e-mail — через запятую. Новые добавятся, удалённые из ячейки отключатся в карточке (не удаляются).',
            '4. Пустая ячейка очищает поле (кроме «Название», «Категория», «Статус», «Подразделение» — они остаются как были).',
            '5. «Контактные лица» — только для просмотра, их меняют в карточке контрагента.',
            '6. Строки не удаляйте, чтобы убрать партнёра: поставьте статус «Неактуален».',
            '7. В CRM → Контрагенты → «Импорт партнёров из Excel» сначала сделайте пробную загрузку, проверьте список изменений, затем загрузите по-настоящему.',
        ];
        foreach ($lines as $i => $text) {
            $help->setCellValue('A'.($i + 1), $text);
        }
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $help->getColumnDimension('A')->setWidth(130);
    }
}
