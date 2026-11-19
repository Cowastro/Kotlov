@php
    $categorySlug = str($slug);
    $icon = match (true) {
        $categorySlug->contains(['otopl', 'kotel', 'kotly', 'radiator', 'dymohod', 'dymokhod']) => 'fire',
        $categorySlug->contains(['nasos', 'vod', 'filtr', 'santeh', 'santekh']) => 'beaker',
        $categorySlug->contains(['schetchik', 'schyotchik', 'izmer', 'manometr']) => 'adjustments-horizontal',
        $categorySlug->contains(['fiting', 'fitting', 'trub', 'armatur', 'montazh', 'instrument']) => 'wrench-screwdriver',
        $categorySlug->contains(['elektr', 'avtomat']) => 'bolt',
        default => 'rectangle-stack',
    };
@endphp
@svg('heroicon-o-'.$icon, 'b2b-category-icon', ['aria-hidden' => 'true'])
