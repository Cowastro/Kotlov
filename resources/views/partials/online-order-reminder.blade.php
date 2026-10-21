@unless (auth()->user()?->isB2B())
<aside class="online-order-reminder" data-online-order-reminder hidden aria-live="polite">
    <button type="button" class="online-order-reminder__close" data-online-order-reminder-close aria-label="Закрыть">×</button>
    <span class="online-order-reminder__eyebrow">Выгоднее онлайн</span>
    <strong>Цена в корзине ниже на 3%</strong>
    <p>Оформите заказ самостоятельно — скидка применится автоматически. Заказы принимаются 24/7.</p>
    <a href="{{ session('cart', []) ? route('cart') : '/catalog' }}" class="tf-btn animate-btn" data-analytics-event="online_discount_reminder_click">
        {{ session('cart', []) ? 'Завершить оформление' : 'Выбрать товар' }}
    </a>
</aside>

@once
    @push('scripts')
        <script>
            (function () {
                var reminder = document.querySelector('[data-online-order-reminder]');
                if (!reminder || /\/(cart|checkout)(\/|$)/.test(window.location.pathname)) return;

                var storageKey = 'kotlov-online-order-reminder-seen';
                var day = 24 * 60 * 60 * 1000;
                var lastSeen = Number(localStorage.getItem(storageKey) || 0);
                if (Date.now() - lastSeen < day) return;

                var showTimer = window.setTimeout(function () {
                    reminder.hidden = false;
                    requestAnimationFrame(function () { reminder.classList.add('is-visible'); });
                    localStorage.setItem(storageKey, String(Date.now()));
                }, 40000);

                reminder.querySelector('[data-online-order-reminder-close]').addEventListener('click', function () {
                    clearTimeout(showTimer);
                    reminder.classList.remove('is-visible');
                    window.setTimeout(function () { reminder.hidden = true; }, 250);
                });
            })();
        </script>
    @endpush
@endonce
@endunless
