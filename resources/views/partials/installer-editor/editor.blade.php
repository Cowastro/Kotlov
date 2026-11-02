{{-- Installer page editor: the installer cabinet and the B2B cabinet section "Моя страница". --}}
@php
    $previewMode = $previewMode ?? false;
    $portfolioPhoto = $profile->works->pluck('photos')->filter()->flatten()->filter()->first();
    $profilePhoto = \App\Support\InstallerMedia::url($profile->photo ?: ($profile->logo ?: $portfolioPhoto));
    $profileInitials = collect(preg_split('/\s+/u', trim($profile->contact_name ?: $profile->company_name ?: 'KOTLOV')))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $publishedWorksCount = $profile->works->where('is_published', true)->count();
    $statusLabels = ['active' => 'Активен', 'pending' => 'На проверке', 'suspended' => 'Приостановлен', 'blocked' => 'Заблокирован'];
@endphp
            <div class="installer-cabinet__grid">
                <div>
                    <section class="installer-cabinet__card mb-24">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Данные профиля</h3><p class="mb-0">Информация, которую увидят клиенты в каталоге монтажников.</p></div>
                        </div>

                        <form method="POST" action="{{ route('account.installer-profile.update') }}" enctype="multipart/form-data" class="form-setting">
                            @csrf
                            @method('PUT')
                            <fieldset @disabled($previewMode) style="border:0;padding:0;margin:0;min-width:0;">

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Имя специалиста *</label>
                                    <input name="contact_name" value="{{ old('contact_name', $profile->contact_name) }}" required>
                                </fieldset>
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Компания</label>
                                    <input name="company_name" value="{{ old('company_name', $profile->company_name) }}">
                                </fieldset>
                            </div>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Телефон *</label><input name="phone" value="{{ old('phone', $profile->phone) }}" required></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Дополнительный телефон</label><input name="additional_phone" value="{{ old('additional_phone', $profile->additional_phone) }}"></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Email</label><input type="email" name="email" value="{{ old('email', $profile->email) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Сайт</label><input type="url" name="website" value="{{ old('website', $profile->website) }}" placeholder="https://..."></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-3 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Telegram</label><input name="telegram" value="{{ old('telegram', $profile->telegram) }}" placeholder="@username"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Viber</label><input name="viber" value="{{ old('viber', $profile->viber) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $profile->whatsapp) }}"></fieldset>
                            </div>

                            <fieldset class="tf-field mb-16">
                                <label class="tf-lable fw-medium">Краткое описание</label>
                                <input name="short_description" maxlength="255" value="{{ old('short_description', $profile->short_description) }}" placeholder="Основная специализация и опыт">
                            </fieldset>
                            <fieldset class="tf-field mb-16">
                                <label class="tf-lable fw-medium">О специалисте</label>
                                <textarea name="bio" maxlength="3000" placeholder="Опыт, виды систем, подход к работе...">{{ old('bio', $profile->bio) }}</textarea>
                            </fieldset>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Опыт, лет</label><input type="number" min="0" max="70" name="experience_years" value="{{ old('experience_years', $profile->experience_years) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Стоимость работ от, BYN</label><input type="number" min="0" step="1" name="price_from" value="{{ old('price_from', $profile->price_from) }}"></fieldset>
                            </div>

                            <p class="fw-semibold mb-10">Специализации</p>
                            <div class="installer-cabinet__check-grid mb-20">
                                @foreach($specializations as $value => $label)
                                <label class="installer-cabinet__check">
                                    <input type="checkbox" name="specializations[]" value="{{ $value }}" @checked(in_array($value, old('specializations', $profile->specializations ?? []), true))>
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Город</label><input name="city" value="{{ old('city', $profile->city) }}"></fieldset>
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Регион</label>
                                    <select name="region"><option value="">— Не указан —</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(old('region', $profile->region) === $region)>{{ $region }}</option>@endforeach</select>
                                </fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Рабочие регионы</label><input name="work_regions_text" value="{{ old('work_regions_text', implode(', ', $profile->work_regions ?? [])) }}" placeholder="Через запятую"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Рабочие города</label><input name="work_cities_text" value="{{ old('work_cities_text', implode(', ', $profile->work_cities ?? [])) }}" placeholder="Через запятую"></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-20">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Радиус выезда, км</label><input type="number" min="0" max="2000" name="work_radius_km" value="{{ old('work_radius_km', $profile->work_radius_km) }}"></fieldset>
                                <label class="installer-cabinet__check align-self-end"><input type="checkbox" name="nationwide" value="1" @checked(old('nationwide', $profile->nationwide))><span>Выезжаю по всей Беларуси</span></label>
                            </div>

                            <fieldset class="tf-field mb-20">
                                <label class="tf-lable fw-medium">Фотография профиля</label>
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                                <span class="text-caption-01 cl-text-3 mt-4">JPG, PNG или WebP, до 5 МБ.</span>
                            </fieldset>

                            @unless($previewMode)<button class="tf-btn animate-btn" type="submit">Сохранить профиль</button>@endunless
                            </fieldset>
                        </form>
                    </section>

                    <section class="installer-cabinet__card">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Мои работы</h3><p class="mb-0">Редактируйте описание объектов и управляйте их видимостью.</p></div>
                            <span class="installer-cabinet__status" style="background:#f4f4f4;color:#222;">{{ $profile->works->count() }} работ</span>
                        </div>

                        @forelse($profile->works as $work)
                        @php $workPhoto = is_array($work->photos) && count($work->photos) ? \App\Support\InstallerMedia::url($work->photos[0]) : null; @endphp
                        <article class="installer-cabinet__work">
                            <div class="installer-cabinet__work-head">
                                <div class="installer-cabinet__work-thumb">@if($workPhoto)<img src="{{ $workPhoto }}" alt="">@endif</div>
                                <div>
                                    <p class="fw-semibold mb-4">{{ $work->title }}</p>
                                    <p class="text-caption-01 cl-text-3 mb-0">{{ implode(' · ', array_filter([$work->city, $work->completed_at?->format('m.Y')])) }}</p>
                                </div>
                                <span class="installer-cabinet__status" style="background:{{ $work->is_published ? '#eaf8ef' : '#f3f3f3' }};color:{{ $work->is_published ? '#16713a' : '#666' }};">{{ $work->is_published ? 'Опубликована' : 'Скрыта' }}</span>
                            </div>
                            <details class="mt-16">
                                <summary>Редактировать работу ↓</summary>
                                <form method="POST" action="{{ route('account.installer-works.update', $work) }}" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    <fieldset @disabled($previewMode) style="border:0;padding:0;margin:0;min-width:0;">
                                    @include('pages.partials.installer-work-fields', ['item' => $work])
                                    @unless($previewMode)<button class="tf-btn animate-btn mt-18" type="submit">Сохранить работу</button>@endunless
                                    </fieldset>
                                </form>
                                @unless($previewMode)
                                <form class="mt-10" method="POST" action="{{ route('account.installer-works.destroy', $work) }}" onsubmit="return confirm('Удалить эту работу из портфолио?');">
                                    @csrf @method('DELETE')
                                    <button class="tf-btn btn-outline" type="submit">Удалить</button>
                                </form>
                                @endunless
                            </details>
                        </article>
                        @empty
                        <p class="cl-text-3 mb-0">Работ пока нет. Добавьте первый объект ниже.</p>
                        @endforelse
                    </section>

                    @unless($previewMode)
                    <section class="installer-cabinet__card mt-24" id="add-work">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Добавить работу</h3><p class="mb-0">Покажите объект, оборудование и результат — хорошее портфолио повышает доверие клиентов.</p></div>
                        </div>
                        <form method="POST" action="{{ route('account.installer-works.store') }}" enctype="multipart/form-data">
                            @csrf
                            <fieldset style="border:0;padding:0;margin:0;min-width:0;">
                            @include('pages.partials.installer-work-fields', ['item' => null])
                            <button class="tf-btn animate-btn mt-18" type="submit">Добавить в портфолио</button>
                            </fieldset>
                        </form>
                    </section>
                    @endunless
                </div>

                <aside>
                    <section class="installer-cabinet__card installer-cabinet__preview-card">
                        <div class="installer-cabinet__photo">
                            @if($profilePhoto)
                            <img src="{{ $profilePhoto }}" alt="{{ $profile->display_name }}">
                            @else
                            <div class="installer-cabinet__photo-fallback">{{ $profileInitials }}</div>
                            @endif
                            <span class="installer-cabinet__photo-label"><i class="icon icon-CheckCircle"></i> Так профиль видит клиент</span>
                        </div>
                        <div class="installer-cabinet__preview-body">
                            <div class="installer-cabinet__preview-kicker mb-8">Публичный профиль</div>
                            <h3 class="h6 mb-7">{{ $profile->display_name }}</h3>
                            <p class="text-caption-01 cl-text-3 mb-0">{{ $profile->short_description ?: 'Добавьте краткое описание профиля' }}</p>
                            <div class="installer-cabinet__metrics">
                                <div class="installer-cabinet__metric"><strong>{{ $profile->experience_years ?: '—' }}</strong><span>лет опыта</span></div>
                                <div class="installer-cabinet__metric"><strong>{{ $publishedWorksCount }}</strong><span>работ в портфолио</span></div>
                            </div>
                            <div class="installer-cabinet__hint mb-14">Контакты, описание и работы монтажник меняет сам. Статус публикации и отметку «Проверен KOTLOV» контролирует администратор.</div>
                            @if($profile->is_published && $profile->slug)
                            <a href="{{ route('installers.show', $profile->slug) }}" target="_blank" class="tf-btn btn-outline w-100">Открыть публичную страницу</a>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
