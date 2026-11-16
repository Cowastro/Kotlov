<!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $name }} | KOTLOV</title>
<style>
body{font:14px/1.45 Arial,sans-serif;color:#202020;margin:32px auto;padding:0 20px;max-width:1100px}header{display:flex;gap:20px;justify-content:space-between;border-bottom:2px solid #222;padding-bottom:16px}h1{font-size:24px;margin:24px 0 8px}p{margin:8px 0}small{color:#666}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:10px 6px;text-align:left;border-bottom:1px solid #ddd;vertical-align:top}.number{text-align:right;white-space:nowrap}thead{display:table-header-group}tr{break-inside:avoid}tfoot{font-weight:bold}button{border:0;border-radius:24px;background:#222;color:white;padding:12px 20px;cursor:pointer}.note{white-space:pre-wrap;border-left:3px solid #ddd;padding-left:12px}@media print{body{margin:0;padding:0;font-size:11px}button{display:none}@page{size:A4;margin:14mm}h1{font-size:20px}}
</style></head><body>
<header><strong>KOTLOV.BY<br><small>Подбор оборудования</small></strong><div>kotlov.by<br>+375 (29) 377-70-92</div></header>
<h1>{{ $name }}</h1><p><small>{{ $date->format('d.m.Y H:i') }} · {{ count($items) }} позиций · BYN</small></p>
<p>Цены на момент формирования подбора. Актуальную цену, наличие и срок поставки подтвердит менеджер. Этот документ не является счётом на оплату.</p>
@if ($note)<p class="note">{{ $note }}</p>@endif
<p><button type="button" onclick="window.print()">Распечатать / сохранить PDF</button></p>
<table><thead><tr><th>№</th><th>Наименование / артикул</th><th class="number">Цена</th><th class="number">Кол-во</th><th class="number">Сумма</th></tr></thead><tbody>
@foreach ($items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item['name'] }}<br><small>{{ $item['sku'] ?? '' }}</small></td><td class="number">{{ number_format((float) $item['price'], 2, ',', ' ') }}</td><td class="number">{{ $item['quantity'] }}</td><td class="number">{{ number_format($item['price'] * $item['quantity'], 2, ',', ' ') }}</td></tr>@endforeach
</tbody><tfoot><tr><td colspan="4">Итого, BYN</td><td class="number">{{ number_format(collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']), 2, ',', ' ') }}</td></tr></tfoot></table>
</body></html>
