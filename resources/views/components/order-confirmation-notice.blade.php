@props(['availabilityConfirmed' => false])

@once
    @push('styles')
        <style>
            .order-confirmation-notice {
                padding: 18px 20px;
                border: 1px solid #e7e2dc;
                border-left: 3px solid var(--primary, #e94242);
                border-radius: 12px;
                background: #fbfaf8;
            }

            .order-confirmation-notice__title {
                margin: 0 0 5px;
                font-weight: 600;
                color: var(--main, #111);
            }

            .order-confirmation-notice__text {
                margin: 0;
                color: var(--text-2, #6f7177);
                line-height: 1.55;
            }

            .order-confirmation-notice__text + .order-confirmation-notice__text {
                margin-top: 5px;
            }

            @media (max-width: 575.98px) {
                .order-confirmation-notice {
                    padding: 15px 16px;
                }
            }
        </style>
    @endpush
@endonce

<div {{ $attributes->class(['order-confirmation-notice']) }} role="note" aria-label="Подтверждение заказа">
    <div>
        <p class="order-confirmation-notice__title">{{ $availabilityConfirmed ? 'Заказ и инженерная проверка' : 'Подтверждение заказа' }}</p>
        @if ($availabilityConfirmed)
            <p class="order-confirmation-notice__text">
                Товар находится на складе — подтверждать наличие не требуется. После оформления заказа менеджер
                свяжется с вами, чтобы проверить совместимость, комплектацию, способ и срок доставки.
            </p>
        @else
            <p class="order-confirmation-notice__text">
                После оформления заказа наш менеджер обязательно свяжется с вами, чтобы уточнить наличие товара,
                актуальную стоимость, комплектацию, способ и срок доставки. Окончательные условия заказа
                согласовываются с покупателем до его подтверждения.
            </p>
        @endif
        <p class="order-confirmation-notice__text">
            Стоимость монтажа, доставки и дополнительных комплектующих рассчитывается отдельно после уточнения
            параметров заказа или объекта.
        </p>
    </div>
</div>
