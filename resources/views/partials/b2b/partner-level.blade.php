@if($can('orders.view'))
    @php($program = app(\App\Services\PartnerLevelEvaluator::class)->progress($client))
    @if($program['current'] || $program['next'])
        <article class="b2b-cabinet-card">
            <h3>Партнёрская программа</h3>
            @if($program['current'])
                <p>Ваш уровень — {{ $program['current']->name }}</p>
                <p>{{ $program['current']->benefits }}</p>
                @if($client->partner_type === 'installer')
                    <p>{{ $program['current']->installer_benefits }}</p>
                @endif
            @endif
            @if($program['next'])
                <p>Следующий уровень — {{ $program['next']->name }}</p>
                <p>{{ $program['next']->benefits }}</p>
                @if($client->partner_type === 'installer')
                    <p>{{ $program['next']->installer_benefits }}</p>
                @endif
                @foreach($program['rules'] as $rule)
                    <p>{{ \App\Models\PartnerProgramLevel::METRICS[$rule['metric']] }}. Период, мес. — {{ $rule['months'] }}. Осталось {{ number_format($rule['remaining'], $rule['metric'] === 'revenue' ? 2 : 0, ',', ' ') }}.</p>
                @endforeach
            @else
                <p>Вы достигли максимального уровня.</p>
            @endif
        </article>
    @endif
@endif
