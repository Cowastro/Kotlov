@php
    // schema.org LocalBusiness for the home and contacts pages: lets Google and
    // Yandex tie the site to the company card (address, phones, logo).
    $seller = config('legal.seller', []);
    $phones = collect($seller['phones'] ?? [])
        ->map(fn ($phone) => '+' . preg_replace('/\D+/', '', (string) $phone))
        ->values()
        ->all();
    $organizationSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'HomeAndConstructionBusiness',
        '@id' => 'https://kotlov.by/#organization',
        'name' => 'KOTLOV',
        'legalName' => $seller['name'] ?? null,
        'url' => 'https://kotlov.by/',
        'logo' => asset('img/logo-kotlov.png'),
        'image' => asset('img/og-default.jpg'),
        'email' => $seller['email'] ?? null,
        'telephone' => $phones[0] ?? null,
        'taxID' => $seller['unp'] ?? null,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'ул. Селицкого, 39Б, каб. 23',
            'addressLocality' => 'Минск',
            'postalCode' => '220075',
            'addressCountry' => 'BY',
        ],
        'contactPoint' => collect($phones)->map(fn ($phone) => [
            '@type' => 'ContactPoint',
            'telephone' => $phone,
            'contactType' => 'sales',
            'areaServed' => 'BY',
            'availableLanguage' => ['ru', 'be'],
        ])->all(),
        'areaServed' => ['@type' => 'Country', 'name' => 'Беларусь'],
    ], fn ($value) => $value !== null && $value !== []);
@endphp
<script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
