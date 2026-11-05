@once
@push('styles')
<style>
.regional-news__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.regional-news__card{min-width:0;border:1px solid #e7e4df;border-radius:16px;overflow:hidden;background:#fff}.regional-news__card>a{display:block;width:100%}.regional-news__card img{display:block;width:100%;height:210px;object-fit:cover}.regional-news__body{padding:22px}.regional-news__date{display:block;font-size:13px;color:#737980;margin-bottom:10px}.regional-news__body h3{font-size:23px;line-height:1.3;margin-bottom:12px}.regional-news__body p{color:#62676b;line-height:1.6}.regional-news__more{display:inline-block;color:#c83339;font-weight:600;margin-top:14px}.regional-news__heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}.regional-news__heading h2{font-size:clamp(27px,3vw,38px)}
@media(max-width:991px){.regional-news__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.regional-news__grid{grid-template-columns:1fr}.regional-news__heading{align-items:start;flex-direction:column}.regional-news__body{padding:18px}.regional-news__card img{height:190px}}
</style>
@endpush
@endonce
<div class="regional-news__grid">
    @foreach($newsPosts as $newsPost)
    <article class="regional-news__card">
        <a href="{{ $newsPost->publicUrl() }}" aria-label="{{ $newsPost->title }}"><img src="{{ $newsPost->cover_image_url }}" alt="{{ $newsPost->title }}" loading="lazy" width="640" height="360"></a>
        <div class="regional-news__body">
            <time class="regional-news__date" datetime="{{ $newsPost->published_at->toDateString() }}">{{ $newsPost->published_at->format('d.m.Y') }} · {{ $newsPost->city->name }}</time>
            <h3><a href="{{ $newsPost->publicUrl() }}">{{ $newsPost->title }}</a></h3>
            <p>{{ Str::limit(strip_tags($newsPost->excerpt ?? ''), 180) }}</p>
            <a class="regional-news__more" href="{{ $newsPost->publicUrl() }}">Читать новость →</a>
        </div>
    </article>
    @endforeach
</div>
