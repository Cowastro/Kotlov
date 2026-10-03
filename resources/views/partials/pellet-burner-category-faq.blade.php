<section class="pellet-category-faq flat-spacing pt-0" id="pellet-burner-faq" aria-labelledby="pellet-faq-title">
    <div class="container">
        <div class="heading-section mb-24">
            <p class="text-caption-01 cl-text-3 mb-8">Помощь перед покупкой</p>
            <h2 id="pellet-faq-title">Частые вопросы о пеллетных горелках</h2>
        </div>
        <div class="pellet-category-faq__grid">
            @foreach ($pelletBurnerFaq as $item)
                <details>
                    <summary>{{ $item['question'] }}</summary>
                    <p>{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
