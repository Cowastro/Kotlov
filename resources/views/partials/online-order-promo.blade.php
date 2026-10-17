<aside class="online-order-promo" aria-label="Скидка за онлайн-заказ">
    <div class="container online-order-promo__inner">
        <div class="online-order-promo__text">
            <strong>−3% за самостоятельный заказ онлайн</strong>
            <span>Оформляйте через корзину в любое время — без ожидания ответа менеджера.</span>
        </div>
        <button type="button" class="online-order-promo__code" data-copy-promo="ONLINE3"
            aria-label="Скопировать промокод ONLINE3">
            <span>Промокод</span> <strong>ONLINE3</strong>
        </button>
    </div>
</aside>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-copy-promo]');
                if (!button) return;

                var code = button.dataset.copyPromo;
                var copied = function () {
                    button.classList.add('is-copied');
                    button.innerHTML = '<strong>Скопировано</strong>';
                    setTimeout(function () {
                        button.classList.remove('is-copied');
                        button.innerHTML = '<span>Промокод</span> <strong>' + code + '</strong>';
                    }, 1600);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(code).then(copied);
                } else {
                    var input = document.createElement('textarea');
                    input.value = code;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                    copied();
                }
            });
        </script>
    @endpush
@endonce
