@php
    $terms = trim((string) $document->delivery_terms);
    $terms = $terms !== '' ? $terms : 'Срок и способ поставки согласовываются с менеджером.';
    // Keep the manager's specific wording instead of repeating a generic delivery prefix.
    if ($document->delivery_to_client && !preg_match('/достав|самовывоз|вывоз|перевоз|курьер|терминал/iu', $terms)) {
        $terms = 'Доставка до клиента. '.$terms;
    }
@endphp
<div>{{ $terms }}</div>
@if((float) $document->delivery_price > 0)
    <div>Стоимость: {{ number_format((float) $document->delivery_price, 2, ',', ' ') }} {{ $document->currency ?: 'BYN' }} (включена в итог).</div>
@endif
