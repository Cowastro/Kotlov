<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 15mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.35; color: #111; }
        h1 { margin: 0 0 8px; text-align: center; font-size: 15px; }
        h2 { margin: 12px 0 5px; text-align: center; font-size: 11px; }
        p { margin: 0 0 5px; text-align: justify; }
        .meta, .requisites, .items { width: 100%; border-collapse: collapse; }
        .meta td { width: 50%; padding: 0 0 9px; }
        .right { text-align: right; }
        .requisites { margin-top: 7px; page-break-inside: avoid; }
        .requisites th, .requisites td { width: 50%; border: 1px solid #777; padding: 7px; vertical-align: top; text-align: left; }
        .requisites th { text-align: center; }
        .signature { margin-top: 28px; }
        .page-break { page-break-before: always; }
        .items th, .items td { border: 1px solid #777; padding: 5px; vertical-align: top; }
        .items th { background: #eee; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
    </style>
</head>
<body>
<h1>ДОГОВОР ПОСТАВКИ № {{ $contract->number }}</h1>
<table class="meta"><tr><td>г. Минск</td><td class="right">{{ $contract->contract_date->translatedFormat('d F Y г.') }}</td></tr></table>

<p>{{ $seller['name'] ?? 'ООО «СанБизнесГруп»' }}, именуемое в дальнейшем «Поставщик», в лице {{ $seller['representative'] ?? '' }}, действующего на основании {{ $seller['authority_basis'] ?? '' }}, и {{ $buyer['name'] ?? '' }}, именуемое в дальнейшем «Покупатель», в лице {{ trim(($buyer['position'] ?? '').' '.($buyer['representative'] ?? '')) }}, действующего на основании {{ $buyer['authority_basis'] ?? 'Устава' }}, заключили настоящий договор о нижеследующем:</p>

<h2>1. ПРЕДМЕТ И ЦЕЛЬ ДОГОВОРА</h2>
<p>1.1. Поставщик обязуется на основании заявок Покупателя поставить, а Покупатель принять и оплатить товары согласно выставленным счетам и (или) спецификациям, являющимся неотъемлемой частью настоящего договора.</p>
<p>1.2. Цель приобретения товара — {{ $purposeText }}.</p>
<p>1.3. Общая сумма договора определяется стоимостью всех партий товара, поставленных в течение срока его действия.</p>
@if((bool) ($terms['regulated_goods'] ?? false))
    <p>1.4. В отношении товаров, для которых законодательством Республики Беларусь установлено регулирование цен или ограничения по цели приобретения, Покупатель обязуется использовать и реализовывать их только в заявленных целях и соблюдать применимые требования законодательства.</p>
@endif

<h2>2. УСЛОВИЯ ПОСТАВКИ И ОБЯЗАТЕЛЬСТВА СТОРОН</h2>
<p>2.1. Поставщик обеспечивает соответствие качества товара требованиям изготовителя и передаёт относящиеся к товару документы, если их наличие обязательно.</p>
<p>2.2. {{ $deliveryText }}</p>
<p>2.3. Покупатель обязан принять товар по количеству, ассортименту, комплектности и внешнему состоянию, а также обеспечить надлежащие условия его транспортировки и хранения.</p>

<h2>3. ЦЕНА И ПОРЯДОК РАСЧЁТОВ</h2>
<p>3.1. Цена товара указывается в счетах и (или) спецификациях. Стоимость упаковки включается в цену товара, если в товаросопроводительных документах не указано иное.</p>
<p>3.2. {{ $paymentText }}</p>

<h2>4. КАЧЕСТВО, УПАКОВКА И ГАРАНТИЯ</h2>
<p>4.1. Качество, упаковка и маркировка товара должны соответствовать обязательным требованиям законодательства Республики Беларусь и документации изготовителя.</p>
<p>4.2. Гарантийный срок определяется изготовителем и указывается в паспорте, гарантийном талоне либо ином документе на товар.</p>

<h2>5. ПРИЁМКА ТОВАРА</h2>
<p>5.1. Приёмка товара по количеству и качеству производится в порядке, установленном законодательством Республики Беларусь. Видимые недостатки и расхождения фиксируются при передаче товара.</p>
<p>5.2. Требования в связи с недостатками товара рассматриваются в порядке и сроки, установленные законодательством и условиями гарантии изготовителя.</p>

<h2>6. ОТВЕТСТВЕННОСТЬ СТОРОН</h2>
<p>6.1. За неисполнение или ненадлежащее исполнение обязательств стороны несут ответственность в соответствии с настоящим договором и законодательством Республики Беларусь.</p>
<p>6.2. При нарушении срока оплаты наличие товара и первоначально согласованный срок поставки не гарантируются.</p>

<h2>7. СРОК ДЕЙСТВИЯ И ПРОЧИЕ УСЛОВИЯ</h2>
<p>7.1. Договор вступает в силу с момента подписания и действует до {{ $contract->valid_until?->format('d.m.Y') }} включительно, а в части неисполненных обязательств — до их полного исполнения.@if((bool) ($terms['auto_renew'] ?? true)) Если ни одна из сторон письменно не заявит о прекращении договора не позднее чем за 14 календарных дней до окончания срока, он продлевается на следующий календарный год.@endif</p>
<p>7.2. Споры разрешаются путём переговоров, а при недостижении соглашения — в экономическом суде в соответствии с законодательством Республики Беларусь.</p>
<p>7.3. Во всём остальном стороны руководствуются законодательством Республики Беларусь.</p>

<h2>8. РЕКВИЗИТЫ И ПОДПИСИ СТОРОН</h2>
<table class="requisites">
    <tr><th>ПОСТАВЩИК</th><th>ПОКУПАТЕЛЬ</th></tr>
    <tr>
        <td>
            {{ $seller['name'] ?? '' }}<br>
            УНП: {{ $seller['unp'] ?? '' }}<br>
            Юридический адрес: {{ $seller['legal_address'] ?? '' }}<br>
            IBAN: {{ $seller['iban'] ?? '' }}<br>
            {{ $seller['bank'] ?? '' }}<br>
            BIC: {{ $seller['bic'] ?? '' }}<br>
            Тел.: {{ implode(', ', (array) ($seller['phones'] ?? [])) }}<br>
            Email: {{ $seller['email'] ?? '' }}
            <div class="signature">________________ {{ $seller['director'] ?? '' }}</div>
        </td>
        <td>
            {{ $buyer['name'] ?? '' }}<br>
            УНП: {{ $buyer['unp'] ?? '' }}<br>
            Юридический адрес: {{ $buyer['legal_address'] ?? '' }}<br>
            IBAN: {{ $buyer['iban'] ?? '' }}<br>
            {{ $buyer['bank'] ?? '' }}<br>
            BIC: {{ $buyer['bic'] ?? '' }}<br>
            Тел.: {{ $buyer['phone'] ?? '' }}<br>
            Email: {{ $buyer['email'] ?? '' }}
            <div class="signature">________________ {{ $buyer['representative'] ?? '' }}</div>
        </td>
    </tr>
</table>

<div class="page-break"></div>
<h1>ПРИЛОЖЕНИЕ № 1<br>СПЕЦИФИКАЦИЯ К ДОГОВОРУ № {{ $contract->number }}</h1>
<p class="center">от {{ $contract->contract_date->format('d.m.Y') }}</p>
<table class="items">
    <thead><tr><th>№</th><th>Наименование</th><th>Артикул</th><th>Количество</th><th>Цена</th><th>Сумма</th></tr></thead>
    <tbody>
    @foreach($document->items as $item)
        <tr>
            <td class="center">{{ $loop->iteration }}</td>
            <td>{{ $item->name }}</td>
            <td>{{ $item->sku }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</td>
            <td class="num">{{ number_format((float) $item->unit_price, 2, ',', ' ') }}</td>
            <td class="num">{{ number_format((float) $item->line_total, 2, ',', ' ') }}</td>
        </tr>
    @endforeach
    <tr><th colspan="5" class="right">ИТОГО</th><th class="num">{{ number_format((float) $document->total, 2, ',', ' ') }} {{ $document->currency }}</th></tr>
    </tbody>
</table>
<p>Настоящая спецификация является неотъемлемой частью договора.</p>
</body>
</html>
