<div id="callback" class="callback-mont popup">
    <form name="callback" class="popup-cont form-call" method="post" action="/callback">
        @csrf

        <div class="fc-content">
            <div class="callback-header">Перезвоним сами!</div>

            <div class="callback-text">
                Просто оставьте свой номер телефона и наш специалист перезвонит вам сам в ближайшие несколько минут!
            </div>

            <input name="name" type="text" class="input" placeholder="Андрей" required>
            <input name="phone" type="text" class="input" placeholder="+375 [__] ___-__-__" required>

            <div>
                <button type="submit" class="btn">Жду звонок!</button>
            </div>
        </div>

        <div class="success">
            <div class="succes_header">Спасибо за заказ звонка!</div>
            <p class="succes_text">Перезвоним вам в ближайшее время!</p>
        </div>

        <div class="close"></div>
    </form>

    <div class="mask"></div>
</div>