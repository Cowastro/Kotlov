@php
    $icon = match (true) {
        IlluminateSupportStr::contains($slug, ['otopl', 'kotel', 'kotly', 'radiator', 'dymohod', 'dymokhod']) => 'fire',
        IlluminateSupportStr::contains($slug, ['nasos', 'vod', 'filtr', 'santeh', 'santekh']) => 'beaker',
        IlluminateSupportStr::contains($slug, ['schetchik', 'schyotchik', 'izmer', 'manometr']) => 'adjustments-horizontal',
        IlluminateSupportStr::contains($slug, ['fiting', 'fitting', 'trub', 'armatur', 'montazh', 'instrument']) => 'wrench-screwdriver',
        IlluminateSupportStr::contains($slug, ['elektr', 'avtomat']) => 'bolt',
        default => 'rectangle-stack',
    };
@endphp
@svg('heroicon-o-'.$icon, 'b2b-category-icon', ['aria-hidden' => 'true'])
