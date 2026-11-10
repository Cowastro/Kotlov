<ul class="fireplace-card-specs list-unstyled d-flex flex-wrap gap-1 text-caption-01 cl-text-2 m-0" aria-label="Характеристики печи">
    @foreach ($fireplaceCardSpecs as $label => $value)
        <li class="border rounded-pill px-2"><span class="visually-hidden">{{ $label }}: </span>{{ $value }}</li>
    @endforeach
</ul>
