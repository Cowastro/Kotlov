<?php

use App\Models\BlogPost;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $post = BlogPost::query()
            ->where('slug', 'montazh-teplovogo-nasosa-kotlov-ge-10-kvt-r290-smolevichskiy-rayon')
            ->first();

        if (! $post || str_contains((string) $post->content, 'class="blog-faq"')) {
            return;
        }

        $post->content = str_replace($this->plainFaq(), $this->formattedFaq(), (string) $post->content);
        $post->save();
    }

    public function down(): void
    {
        $post = BlogPost::query()
            ->where('slug', 'montazh-teplovogo-nasosa-kotlov-ge-10-kvt-r290-smolevichskiy-rayon')
            ->first();

        if (! $post) {
            return;
        }

        $post->content = str_replace($this->formattedFaq(), $this->plainFaq(), (string) $post->content);
        $post->save();
    }

    private function plainFaq(): string
    {
        return <<<'HTML'
<h2>Частые вопросы о тепловом насосе 10 кВт</h2>

<h3>На какую площадь рассчитан тепловой насос 10 кВт?</h3>
<p class="text text-body-1">Универсального ответа нет. Два дома одинаковой площади могут отличаться по теплопотерям в несколько раз. Мощность подбирают по расчёту тепловой нагрузки, температуре подачи, потребности в ГВС и режиму резервного источника.</p>

<h3>Можно ли подключить KOTLOV GE R290 к радиаторам?</h3>
<p class="text text-body-1">Да, но сначала проверяют теплоотдачу радиаторов при реальной температуре воды. Возможность высокой подачи у R290 расширяет варианты применения, однако наиболее эффективным остаётся минимально достаточный температурный режим.</p>

<h3>Нужна ли отдельная ёмкость в котельной?</h3>
<p class="text text-body-1">Это определяется схемой. Учитывают минимальный объём воды, количество зон, требования к протоку при разморозке и способ приготовления ГВС. Решение принимается после обследования объекта.</p>
HTML;
    }

    private function formattedFaq(): string
    {
        return <<<'HTML'
<section class="blog-faq" aria-labelledby="heat-pump-faq-title">
    <div class="blog-faq-heading">
        <span class="blog-faq-kicker">Вопросы и ответы</span>
        <h2 class="blog-faq-title" id="heat-pump-faq-title">Частые вопросы о тепловом насосе 10 кВт</h2>
    </div>
    <div class="blog-faq-list">
        <details class="blog-faq-item" open>
            <summary class="blog-faq-question">На какую площадь рассчитан тепловой насос 10 кВт?</summary>
            <div class="blog-faq-answer"><p>Универсального ответа нет. Два дома одинаковой площади могут отличаться по теплопотерям в несколько раз. Мощность подбирают по расчёту тепловой нагрузки, температуре подачи, потребности в ГВС и режиму резервного источника.</p></div>
        </details>
        <details class="blog-faq-item">
            <summary class="blog-faq-question">Можно ли подключить KOTLOV GE R290 к радиаторам?</summary>
            <div class="blog-faq-answer"><p>Да, но сначала проверяют теплоотдачу радиаторов при реальной температуре воды. Возможность высокой подачи у R290 расширяет варианты применения, однако наиболее эффективным остаётся минимально достаточный температурный режим.</p></div>
        </details>
        <details class="blog-faq-item">
            <summary class="blog-faq-question">Нужна ли отдельная ёмкость в котельной?</summary>
            <div class="blog-faq-answer"><p>Это определяется схемой. Учитывают минимальный объём воды, количество зон, требования к протоку при разморозке и способ приготовления ГВС. Решение принимается после обследования объекта.</p></div>
        </details>
    </div>
</section>
HTML;
    }
};
