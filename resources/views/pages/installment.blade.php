@extends('layouts.amerce')

@section('title', 'Рассрочка до 6 месяцев — как оформить | KOTLOV')
@section('description', 'Как оформить покупку товара в рассрочку до 6 месяцев в интернет-магазине KOTLOV: порядок заявки, условия участия и видеоинструкция.')

@push('styles')
<style>
    .installment-page{padding-bottom:72px}.installment-lead{max-width:820px;margin:0 auto 34px;color:var(--text-2);font-size:17px;line-height:1.65;text-align:center}
    .installment-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:42px}.installment-summary__item{padding:22px;border:1px solid var(--line);border-radius:16px;background:#fff;text-align:center}.installment-summary__item strong{display:block;margin-bottom:5px;font-size:20px}.installment-summary__item span{color:var(--text-2);font-size:14px}
    .installment-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.85fr);gap:28px;align-items:start}.installment-card{padding:28px;border:1px solid var(--line);border-radius:20px;background:#fff}.installment-card h2{margin-bottom:20px}
    .installment-steps{display:grid;gap:12px;counter-reset:installment-step}.installment-step{position:relative;padding:17px 18px 17px 64px;border-radius:14px;background:#f7f7f7;counter-increment:installment-step}.installment-step::before{content:counter(installment-step);position:absolute;left:18px;top:17px;display:grid;width:30px;height:30px;place-items:center;border-radius:50%;background:#111;color:#fff;font-weight:700}.installment-step strong{display:block;margin-bottom:3px}.installment-step p{margin:0;color:var(--text-2);font-size:14px;line-height:1.5}
    .installment-video{overflow:hidden;padding:0}.installment-video__frame{position:relative;padding-top:56.25%;background:#111}.installment-video__frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}.installment-video__copy{padding:20px 24px}.installment-video__copy p{margin:5px 0 0;color:var(--text-2);font-size:14px}
    .installment-section{margin-top:42px}.installment-rules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0 0;padding:0;list-style:none}.installment-rules li{padding:18px;border:1px solid var(--line);border-radius:14px}.installment-rules strong{display:block;margin-bottom:4px}.installment-rules span{color:var(--text-2);font-size:14px;line-height:1.5}
    .installment-note{margin-top:18px;padding:18px;border-left:4px solid #d89b00;border-radius:0 12px 12px 0;background:#fff7df;color:#5e4800;line-height:1.55}.installment-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.installment-actions .tf-btn{min-width:220px}
    .installment-faq{display:grid;gap:10px;margin-top:18px}.installment-faq details{padding:17px 18px;border:1px solid var(--line);border-radius:14px;background:#fff}.installment-faq summary{cursor:pointer;font-weight:650}.installment-faq p{margin:10px 0 0;color:var(--text-2);line-height:1.55}
    @media(max-width:991px){.installment-layout{grid-template-columns:1fr}.installment-summary{grid-template-columns:1fr}.installment-video{order:-1}}
    @media(max-width:575px){.installment-page{padding-bottom:48px}.installment-lead{text-align:left;font-size:15px}.installment-card{padding:20px}.installment-rules{grid-template-columns:1fr}.installment-actions .tf-btn{width:100%}}
</style>
@endpush

@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ url('/') }}" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Рассрочка до 6 месяцев</p>
                </div>
                <h1 class="h3">Покупка в рассрочку до 6 месяцев</h1>
            </div>
        </div>
    </section>

    <section class="installment-page flat-spacing">
        <div class="container">
            <p class="installment-lead">Выберите товар и отправьте заказ с оплатой в рассрочку. Менеджер проверит, участвует ли товар в программе, согласует срок и подготовит дальнейшее оформление.</p>

            <div class="installment-summary">
                <div class="installment-summary__item"><strong>До 6 месяцев</strong><span>точный срок согласуется при оформлении</span></div>
                <div class="installment-summary__item"><strong>Заявка на сайте</strong><span>достаточно товара и контактных данных</span></div>
                <div class="installment-summary__item"><strong>Помощь менеджера</strong><span>проверим условия и подготовим документы</span></div>
            </div>

            <div class="installment-layout">
                <div class="installment-card">
                    <h2 class="h4">Как оформить рассрочку</h2>
                    <div class="installment-steps">
                        <div class="installment-step"><strong>Выберите товар</strong><p>Откройте карточку нужного товара и нажмите «Купить в рассрочку до 6 месяцев».</p></div>
                        <div class="installment-step"><strong>Заполните заказ</strong><p>Укажите имя, телефон, адрес и проверьте, что выбран способ оплаты «Рассрочка на 6 месяцев».</p></div>
                        <div class="installment-step"><strong>Дождитесь звонка менеджера</strong><p>Мы проверим участие товара в программе, подтвердим актуальную цену, наличие и возможный срок рассрочки.</p></div>
                        <div class="installment-step"><strong>Завершите оформление</strong><p>Менеджер поможет заполнить необходимые данные и объяснит дальнейшие действия по документам и получению товара.</p></div>
                    </div>
                </div>

                <div class="installment-card installment-video">
                    <div class="installment-video__frame">
                        <iframe src="https://www.youtube-nocookie.com/embed/nw2v3z5BZms?rel=0" title="Как оформить покупку в рассрочку до 6 месяцев" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                    </div>
                    <div class="installment-video__copy">
                        <strong>Видеоинструкция</strong>
                        <p>Видео показывает порядок оформления. В ролике может быть указан прежний срок программы; актуальный срок — до 6 месяцев — подтверждает менеджер.</p>
                    </div>
                </div>
            </div>

            <div class="installment-section">
                <h2 class="h4">Важные условия</h2>
                <ul class="installment-rules">
                    <li><strong>Не для всех товаров</strong><span>Рассрочка доступна для товаров, участвующих в программе. Возможность оформления подтверждает менеджер.</span></li>
                    <li><strong>Скидки не суммируются</strong><span>Рассрочка не применяется одновременно со специальными ценами, акциями и другими скидками.</span></li>
                    <li><strong>Итоговые условия подтверждаются</strong><span>До оформления документов менеджер сообщит точную цену, срок рассрочки и состав заказа.</span></li>
                    <li><strong>Заказ не равен одобрению</strong><span>Отправка заявки фиксирует ваш выбор и передаёт его менеджеру для проверки и дальнейшего оформления.</span></li>
                </ul>
                <div class="installment-note"><strong>Обратите внимание:</strong> цена товара при покупке в рассрочку может отличаться от онлайн-цены со скидкой. Менеджер обязательно согласует итоговую сумму до оформления.</div>
                <div class="installment-actions">
                    <a href="{{ route('catalog') }}" class="tf-btn type-xl animate-btn">Выбрать товар</a>
                    <a href="{{ route('contacts') }}" class="tf-btn type-xl btn-outline animate-btn">Задать вопрос менеджеру</a>
                </div>
            </div>

            <div class="installment-section">
                <h2 class="h4">Частые вопросы</h2>
                <div class="installment-faq">
                    <details><summary>Нужно ли сразу заполнять документы?</summary><p>Нет. На сайте достаточно оформить заказ и оставить контакты. Заполнение и проверку необходимых данных продолжит менеджер.</p></details>
                    <details><summary>Можно ли оформить любой товар?</summary><p>Нет. Сначала менеджер проверит, участвует ли выбранный товар в программе рассрочки.</p></details>
                    <details><summary>Сохранится ли скидка интернет-магазина?</summary><p>Рассрочка не суммируется с акциями и специальными ценами. Итоговую стоимость менеджер сообщит до оформления.</p></details>
                    <details><summary>Когда заказ будет передан в работу?</summary><p>После подтверждения товара, цены и условий менеджером. Статус заказа можно отслеживать в личном кабинете.</p></details>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
