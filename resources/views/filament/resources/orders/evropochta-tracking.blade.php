<div class="space-y-4 text-sm">
    <p>Трек-номер <strong>{{ $number }}</strong></p>

    @if ($error)
        <p role="status">{{ $error }}</p>
    @elseif ($history === [])
        <p>Европочта пока не вернула событий. После оформления заявки подождите три минуты.</p>
    @else
        <ol class="space-y-3">
            @foreach ($history as $event)
                <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold">{{ $event['status'] }}</p>
                    @if ($event['time'] !== '')<p>{{ $event['time'] }}</p>@endif
                    @if ($event['detail'] !== '')<p>{{ $event['detail'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
