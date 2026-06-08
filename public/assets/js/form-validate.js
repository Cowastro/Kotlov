/**
 * Клиентская валидация публичных форм KOTLOV.
 * Работает с любыми формами с классом .kv-form.
 * Обязательные поля помечаются: data-required="1" data-label="Название поля"
 * Необязательные с форматом: data-label="Email" (только format-check если заполнено)
 */
(function ($) {
    'use strict';

    // Достаём или создаём элемент для ошибки под полем
    function getErrEl($field) {
        var $err = $field.nextAll('.kv-field-error').first();
        if (!$err.length) {
            $err = $('<span class="kv-field-error"></span>').insertAfter($field);
        }
        return $err;
    }

    function setError($field, msg) {
        $field.addClass('kv-invalid').removeClass('kv-valid');
        getErrEl($field).text(msg).addClass('kv-field-error--visible');
        return false;
    }

    function clearError($field) {
        $field.removeClass('kv-invalid');
        getErrEl($field).text('').removeClass('kv-field-error--visible');
    }

    function setValid($field) {
        $field.addClass('kv-valid').removeClass('kv-invalid');
        getErrEl($field).text('').removeClass('kv-field-error--visible');
        return true;
    }

    function validateField($field) {
        var val    = $field.val() ? $field.val().trim() : '';
        var type   = ($field.attr('type') || 'text').toLowerCase();
        var label  = $field.data('label') || 'Поле';
        var req    = !!$field.data('required');

        // Обязательное поле пустое
        if (req && !val) {
            return setError($field, 'Заполните поле «' + label + '»');
        }

        // Формат email
        if (val && type === 'email') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                return setError($field, 'Введите корректный email');
            }
        }

        // Формат телефона — минимум 7 цифр
        if (val && type === 'tel') {
            if (val.replace(/\D/g, '').length < 7) {
                return setError($field, 'Введите корректный номер телефона');
            }
        }

        // URL
        if (val && type === 'url') {
            if (!/^https?:\/\/.+\..+/.test(val)) {
                return setError($field, 'Введите корректный URL (начиная с https://)');
            }
        }

        if (val) return setValid($field);
        clearError($field);
        return true;
    }

    // Blur — валидируем поле при потере фокуса
    $(document).on('blur', '.kv-form [data-required], .kv-form [data-label]', function () {
        validateField($(this));
    });

    // Input/change — убираем ошибку как только пользователь исправляет поле
    $(document).on('input change', '.kv-form [data-required], .kv-form [data-label]', function () {
        if ($(this).hasClass('kv-invalid')) {
            validateField($(this));
        }
    });

    // Submit — проверяем все обязательные поля
    $(document).on('submit', '.kv-form', function (e) {
        var $form  = $(this);
        var valid  = true;
        var $first = null;

        $form.find('[data-required]').each(function () {
            var ok = validateField($(this));
            if (!ok) {
                valid = false;
                if (!$first) $first = $(this);
            }
        });

        if (!valid) {
            e.preventDefault();

            // Показываем/обновляем summary
            var $summary = $form.find('.kv-summary');
            $summary.addClass('kv-summary--visible');

            // Скролл к первой ошибке
            if ($first) {
                var top = $first.closest('fieldset, .col-12, .tf-field, div').offset().top - 120;
                $('html, body').animate({ scrollTop: top }, 300);
            }
        }
    });

    // Скрываем summary когда пользователь начинает исправлять
    $(document).on('input change', '.kv-form [data-required]', function () {
        var $form    = $($(this).closest('.kv-form'));
        var hasError = $form.find('.kv-invalid').length > 0;
        if (!hasError) $form.find('.kv-summary').removeClass('kv-summary--visible');
    });

})(jQuery);
