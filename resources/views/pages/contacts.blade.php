@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Контакты</p>
                </div>
                <h3>Контакты</h3>
                <p class="text-body-1 cl-text-2">
                    Свяжитесь с нами — ответим на вопросы, поможем выбрать оборудование<br class="d-none d-lg-block">
                    и подберём монтажную организацию.
                </p>
            </div>
        </div>
    </section>

    {{-- Карта --}}
    <div class="section-map flat-spacing-2 pb-0">
        <div class="container">
            <div class="wg-map">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2350.8!2d27.5615!3d53.9065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcfd35b1e6ad3%3A0xb97b72b3def99842!2z0YPQuy4g0KHQtdC70LjRhtC60L7Qs9C-LCAzOdCkLCDQnNC40L3RgdC6!5e0!3m2!1sru!2sby!4v1700000000000"
                    allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>

    {{-- Контакты + форма --}}
    <section class="section-contact flat-spacing">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success mb-20">{{ session('success') }}</div>
            @endif

            <div class="row gy-5 flex-wrap-reverse">

                {{-- Информация --}}
                <div class="col-md-6">
                    <div class="col-left">
                        <div class="heading d-grid gap-8">
                            <h4>Наши контакты</h4>
                            <p class="cl-text-2">
                                Есть вопрос? Свяжитесь с нами любым удобным способом.
                            </p>
                        </div>
                        <div class="grid-info tf-grid-layout sm-col-2">
                            <div class="d-grid gap-8">
                                <h6>Телефон:</h6>
                                <p>
                                    <a href="tel:+375293544041" class="cl-text-2 link">
                                        +375 (29) 354-40-41
                                    </a>
                                </p>
                                <p>
                                    <a href="tel:+375296322311" class="cl-text-2 link">
                                        +375 (29) 632-23-11
                                    </a>
                                </p>
                                <p>
                                    <a href="tel:+375173885958" class="cl-text-2 link">
                                        +375 (17) 388-59-58
                                    </a>
                                </p>
                            </div>
                            <div class="d-grid gap-8">
                                <h6>Email:</h6>
                                <p>
                                    <a href="mailto:info@kotlov.by" class="cl-text-2 link">
                                        info@kotlov.by
                                    </a>
                                </p>
                            </div>
                            <div class="wd-full d-grid gap-8">
                                <h6>Адрес:</h6>
                                <p>
                                    <a href="https://www.google.com/maps?q=Минск,+ул.+Селицкого,+39Б"
                                        target="_blank" class="cl-text-2 link">
                                        Беларусь, г. Минск, ул. Селицкого, 39Б, каб. 23
                                    </a>
                                </p>
                            </div>
                            <div class="wd-full d-grid gap-8">
                                <h6>Режим работы:</h6>
                                <ul class="open-text">
                                    <li class="d-flex gap-4 mb-4">
                                        <span class="cl-text-2">Пн–Пт:</span> 9:00 – 18:00
                                    </li>
                                    <li class="d-flex gap-4 mb-4">
                                        <span class="cl-text-2">Сб:</span> 10:00 – 14:00
                                    </li>
                                    <li class="d-flex gap-4">
                                        <span class="cl-text-2">Вс:</span> выходной
                                    </li>
                                </ul>
                            </div>
                            <div class="wd-full d-grid gap-8">
                                <h6>Мы в соцсетях:</h6>
                                <ul class="tf-social-icon-2 hv-dark d-flex gap-8">
                                    <li>
                                        <a href="https://www.instagram.com/kotlov.by/" target="_blank">
                                            <i class="icon icon-InstagramLogo"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="https://www.facebook.com/" target="_blank">
                                            <i class="icon icon-FacebookLogo"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="https://t.me/kotlovby" target="_blank">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.289c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.48 13.9l-2.95-.924c-.642-.204-.657-.642.136-.953l11.527-4.445c.535-.194 1.003.131.37.67z"/>
                                            </svg>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="https://wa.me/375296322311" target="_blank">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12.002 0C5.374 0 .006 5.368.006 11.996c0 2.1.546 4.15 1.588 5.953L0 24l6.242-1.566a11.99 11.99 0 0 0 5.76 1.466h.005C18.63 23.9 24 18.53 24 11.902 24 5.368 18.63 0 12.002 0zm0 21.9a9.95 9.95 0 0 1-5.075-1.385l-.363-.216-3.766.945.984-3.658-.237-.376A9.896 9.896 0 0 1 2.1 11.996C2.1 6.518 6.522 2.1 12.002 2.1c5.476 0 9.898 4.418 9.898 9.896 0 5.48-4.422 9.904-9.898 9.904zm5.442-7.414c-.298-.149-1.766-.87-2.04-.97-.273-.098-.472-.148-.67.15-.2.298-.77.969-.944 1.168-.174.2-.347.224-.645.075-.298-.15-1.26-.464-2.4-1.48-.888-.79-1.487-1.766-1.66-2.064-.175-.298-.019-.46.13-.608.133-.133.298-.347.448-.522.149-.174.198-.298.298-.497.099-.198.05-.373-.025-.522-.075-.149-.67-1.617-.918-2.214-.242-.581-.487-.502-.67-.511l-.571-.01c-.198 0-.52.075-.793.373-.273.298-1.04 1.018-1.04 2.484 0 1.467 1.065 2.884 1.213 3.083.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                            </svg>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Форма --}}
                <div class="col-md-6">
                    <h4 class="mb-8">Написать нам</h4>
                    <p class="mb-24 cl-text-2">
                        Оставьте заявку — перезвоним в течение рабочего дня
                    </p>
                    <form class="form-get" action="/contacts" method="POST">
                        @csrf
                        <x-form-protection />
                        <div class="form-content">
                            <div class="tf-grid-layout sm-col-2">
                                <fieldset class="tf-field">
                                    <label for="contact-name" class="tf-lable fw-medium">
                                        Ваше имя <span class="text-primary">*</span>
                                    </label>
                                    <input type="text" id="contact-name" name="name"
                                        value="{{ old('name') }}"
                                        placeholder="Ваше имя*" required>
                                </fieldset>
                                <fieldset class="tf-field">
                                    <label for="contact-phone" class="tf-lable fw-medium">
                                        Телефон <span class="text-primary">*</span>
                                    </label>
                                    <input type="tel" id="contact-phone" name="phone"
                                        value="{{ old('phone') }}"
                                        placeholder="+375 (29) 000-00-00" required>
                                </fieldset>
                            </div>
                            <fieldset class="tf-field">
                                <label for="contact-email" class="tf-lable fw-medium">
                                    Email
                                </label>
                                <input type="email" id="contact-email" name="email"
                                    value="{{ old('email') }}"
                                    placeholder="Ваш email">
                            </fieldset>
                            <fieldset class="tf-field">
                                <label for="contact-message" class="tf-lable fw-medium">
                                    Сообщение <span class="text-primary">*</span>
                                </label>
                                <textarea id="contact-message" name="message"
                                    placeholder="Ваш вопрос или комментарий*"
                                    required>{{ old('message') }}</textarea>
                            </fieldset>
                            <div class="checkbox-wrap">
                                <input class="tf-check flex-shrink-0" type="checkbox"
                                    id="agree-contact" required>
                                <label for="agree-contact">
                                    Я согласен с
                                    <a href="/privacy" class="link text-decoration-underline">политикой конфиденциальности</a>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="tf-btn animate-btn">
                            Отправить сообщение
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </section>

</main>
@endsection