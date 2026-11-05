<div class="b2b-cabinet-heading"><div><span>Магазины KOTLOV</span><h2>Мои магазины и филиалы</h2></div><p>Контакты, фотографии и описание ваших точек продаж</p></div>
<div class="b2b-cabinet-alert is-preview">Заполните магазин и отправьте на проверку. KOTLOV согласует публикацию и подключит городской сайт. Изменения действующего магазина появятся на сайте после проверки.</div>
@forelse($partnerBranches as $branch)
    @php($draft = array_replace($branch->only(\App\Services\PartnerBranchModeration::FIELDS), $branch->pending_changes ?? []))
    <article class="b2b-cabinet-card" style="margin-bottom:20px">
        <div class="b2b-cabinet-card__head"><h3>{{ $draft['name'] }}</h3><small>{{ $branch->changes_submitted_at ? 'На проверке KOTLOV' : ($branch->is_active ? 'На сайте' : 'Не опубликован') }}</small></div>
        <p>{{ $branch->city?->storefrontUrl() }} · {{ $draft['address'] ?? '' }}</p>
        @if($branch->changes_rejection_reason)<div class="b2b-cabinet-alert is-error">Нужно уточнить: {{ $branch->changes_rejection_reason }}</div>@endif
        <p><a href="{{ route('b2b.branches.preview', $branch) }}" target="_blank" rel="noopener">Предпросмотр магазина →</a></p>
        <details><summary>Заполнить или изменить данные</summary>
            @include('partials.b2b.branch-form', ['branchForm' => $branch, 'draft' => $draft])
        </details>
    </article>
@empty
    <article class="b2b-cabinet-card"><h3>Добавьте первый магазин</h3><p>Укажите город, адрес и контакты. Покупатели смогут найти вашу точку на KOTLOV.</p></article>
@endforelse
<article class="b2b-cabinet-card"><h3>Добавить магазин или филиал</h3>
    @include('partials.b2b.branch-form', ['branchForm' => null, 'draft' => ['name' => $client->company_name, 'email' => $client->contact_email ?: $user->email, 'phones' => [$client->contact_phone ?: $user->phone]]])
</article>
