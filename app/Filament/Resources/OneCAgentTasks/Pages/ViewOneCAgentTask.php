<?php

namespace App\Filament\Resources\OneCAgentTasks\Pages;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Models\OneCAgentTask;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;

/** A task of «Мост 1С»; for a read task — what 1C answered, list by list. */
class ViewOneCAgentTask extends ViewRecord
{
    protected static string $resource = OneCAgentTaskResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Задача 1С №'.$this->record->id.': '.$this->record->title;
    }

    public function infolist(Schema $schema): Schema
    {
        $sections = [
            Section::make('Задача')->columns(4)->schema([
                TextEntry::make('status')->label('Состояние')->badge()
                    ->formatStateUsing(fn (string $state): string => OneCAgentTask::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => OneCAgentTaskResource::color($state)),
                TextEntry::make('summary')->label('Результат')->placeholder('—'),
                TextEntry::make('created_at')->label('Поставлена')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextEntry::make('finished_at')->label('Ответ агента')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                TextEntry::make('error')->label('Ошибка')->columnSpanFull()->visible(fn (OneCAgentTask $record): bool => filled($record->error)),
            ]),
        ];
        if (in_array($this->record->type, [ODataReadTaskHandler::TYPE, 'doc_status'], true)) {
            foreach (array_keys((array) data_get($this->record->payload, 'queries', [])) as $name) {
                $sections[] = Section::make($name)->collapsible()
                    ->schema([TextEntry::make('part_'.$name)->hiddenLabel()->state(fn (OneCAgentTask $record) => $this->part($record, $name))]);
            }
        }

        return $schema->components($sections);
    }

    private function part(OneCAgentTask $task, string $name): HtmlString
    {
        $part = ODataReadTaskHandler::part($task, $name);
        if ($part['code'] === null) {
            return new HtmlString('<span style="opacity:.6">Агент ещё не прислал</span>');
        }
        $head = '<div style="margin-bottom:.5rem">1С ответила <b>'.$part['code'].'</b>, '.number_format($part['bytes'] / 1024, 1, ',', ' ').' КБ</div>';
        $rows = is_array($part['json']) ? ($part['json']['value'] ?? null) : null;
        if ($part['code'] !== 200 || ! is_array($rows)) {
            return new HtmlString($head.'<pre style="white-space:pre-wrap;font-size:12px;max-height:30rem;overflow:auto">'.e(mb_substr((string) $part['raw'], 0, 20000)).'</pre>');
        }
        if (str_ends_with($name, '_sample')) {
            $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return new HtmlString($head.'<pre style="white-space:pre-wrap;font-size:12px;max-height:40rem;overflow:auto">'.e(mb_substr((string) $json, 0, 60000)).'</pre>');
        }

        $columns = ['Description', 'Ref_Key'];
        foreach ($rows[0] ?? [] as $key => $value) {
            if (count($columns) < 8 && ! in_array($key, $columns, true) && is_scalar($value) && mb_strlen((string) $value) <= 60
                && ! str_contains($key, '@') && ! in_array($key, ['DataVersion', 'Predefined', 'PredefinedDataName'], true)) {
                $columns[] = $key;
            }
        }
        $html = $head.'<div style="overflow:auto;max-height:40rem"><table style="font-size:12px;border-collapse:collapse;width:100%"><tr>';
        foreach ($columns as $c) {
            $html .= '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #444">'.e($c).'</th>';
        }
        $html .= '</tr>';
        foreach (array_slice($rows, 0, 300) as $row) {
            $html .= '<tr>';
            foreach ($columns as $c) {
                $v = $row[$c] ?? '';
                $html .= '<td style="padding:4px 8px;border-bottom:1px solid #333;user-select:all">'.e(is_bool($v) ? ($v ? 'да' : 'нет') : (is_scalar($v) ? (string) $v : '…')).'</td>';
            }
            $html .= '</tr>';
        }

        return new HtmlString($html.'</table></div>');
    }
}
