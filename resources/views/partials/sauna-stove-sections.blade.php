<div class="container sauna-offers">
    <div class="sauna-offers__groups">
        @foreach ($saunaSections as $title => $offers)
            <section class="sauna-offers__group" aria-labelledby="sauna-group-{{ $loop->index }}">
                <h2 id="sauna-group-{{ $loop->index }}">{{ $title }}</h2>
                @forelse ($offers as $offer)
                    <a class="sauna-offers__item" href="{{ url('/'.$offer->category->slug.'/'.$offer->slug) }}">
                        <img src="{{ $offer->image_url }}" width="76" height="76" alt="" loading="lazy">
                        <div>
                            <span class="sauna-offers__name">{{ $offer->name }}</span>
                            <strong>{{ $offer->hasVerifiedPublicPrice() ? number_format($offer->price, 2, ',', ' ').' BYN' : 'Цену уточняйте' }}</strong>
                            <span class="sauna-offers__stock">{{ $offer->publicAvailabilityLabel() }}</span>
                        </div>
                    </a>
                @empty
                    <p>По выбранным условиям моделей нет.</p>
                @endforelse
            </section>
        @endforeach
    </div>
    <a class="link text-decoration-underline" href="{{ url('/aksessuary-dlya-parnoy') }}">Аксессуары для парной</a>
</div>
