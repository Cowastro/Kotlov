@extends('admin.releases.layout')

@section('title', 'Что нового')

@section('content')
  <header>
    <div class="eyebrow">Для сотрудников</div>
    <h1>Что нового в KOTLOV</h1>
    <p class="lead">Внутренние релизы: что изменилось в админке, каталоге и на сайте. Ссылка ниже открывает релиз без входа и работает {{ $linkHours }} часов — её можно отправить в общий чат.</p>
  </header>
  <div class="release-list">
    @foreach ($releases as $date => $release)
      <article class="release">
        <time datetime="{{ $date }}">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') }}</time>
        <a class="title" href="{{ route('releases.show', $date) }}">{{ $release['title'] }}</a>
        <p>{{ $release['summary'] }}</p>
        <div class="share">
          <input id="link-{{ $date }}" type="text" readonly value="{{ $release['link'] }}" aria-label="Ссылка на релиз на {{ $linkHours }} часов">
          <button type="button" data-copy="link-{{ $date }}">Скопировать ссылку</button>
          <small>Действует до {{ now()->addHours($linkHours)->format('d.m.Y H:i') }}. Обновите страницу, чтобы получить новую.</small>
        </div>
      </article>
    @endforeach
  </div>
  <script>
    document.querySelectorAll('[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.copy);
        var done = function () { button.textContent = 'Скопировано'; setTimeout(function () { button.textContent = 'Скопировать ссылку'; }, 2000); };
        if (navigator.clipboard) {
          navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
        } else {
          input.select(); document.execCommand('copy'); done();
        }
      });
    });
  </script>
@endsection
