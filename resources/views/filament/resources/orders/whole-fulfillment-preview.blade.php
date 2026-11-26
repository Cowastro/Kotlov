<div class="space-y-3">
    <style>
        .whole-plan-mobile { display: none; }
        .whole-plan-table th { padding: 8px; white-space: nowrap; }
        .whole-plan-table td { padding: 10px 8px; }
        @media (max-width: 640px) {
            .whole-plan-desktop { display: none; }
            .whole-plan-mobile { display: grid; gap: 12px; }
            .whole-plan-card { border: 1px solid #8885; border-radius: 8px; padding: 12px; overflow-wrap: anywhere; font-size: 13px; }
            .whole-plan-quantities { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 10px 0; }
            .whole-plan-quantities dt, .whole-plan-source { font-size: 11px; opacity: .75; }
            .whole-plan-quantities dd { margin: 4px 0 0; font-weight: 600; }
        }
    </style>
    <p>Позиций: <strong>{{ count($plan) }}</strong>. Свободный остаток проверяется повторно при принятии заказа; фактический резерв подтверждает 1С.</p>
    <div class="whole-plan-mobile">
        @foreach($plan as $row)
            <article class="whole-plan-card">
                <strong>{{ $row['item']->product_name }}</strong>
                <div class="whole-plan-source">{{ $row['item']->product_sku }}</div>
                <dl class="whole-plan-quantities">
                    <div><dt>Заказано</dt><dd>{{ $row['item']->quantity }}</dd></div>
                    <div><dt>Доступно</dt><dd>{{ $row['available'] ?? 'Уточняется' }}</dd></div>
                    <div><dt>Ожидается</dt><dd>{{ $row['missing'] ?? 'Уточняется' }}</dd></div>
                </dl>
                <div>{{ \App\Models\OrderItem::FULFILLMENT_AVAILABILITY_STATUSES[$row['status']] ?? 'Уточняется' }}</div>
                <div class="whole-plan-source">{{ $row['source'] }}</div>
                @if($row['own_cost_label']) <div>{{ $row['own_cost_label'] }}</div> @endif
            </article>
        @endforeach
    </div>
    <div class="whole-plan-desktop" style="overflow-x:auto">
        <table class="whole-plan-table" style="width:100%;min-width:600px;text-align:left;border-collapse:collapse">
            <thead><tr><th style="padding:8px">Товар / артикул</th><th>Заказано</th><th>Доступно</th><th>Ожидается</th><th>Состояние</th></tr></thead>
            <tbody>
            @foreach($plan as $row)
                <tr style="border-top:1px solid #7775">
                    <td style="padding:10px 8px;max-width:350px">{{ $row['item']->product_name }}<br><small>{{ $row['item']->product_sku }}</small></td>
                    <td>{{ $row['item']->quantity }}</td><td>{{ $row['available'] ?? 'Уточняется' }}</td><td>{{ $row['missing'] ?? 'Уточняется' }}</td>
                    <td>{{ \App\Models\OrderItem::FULFILLMENT_AVAILABILITY_STATUSES[$row['status']] ?? 'Уточняется' }}<br><small>{{ $row['source'] }}</small>
                        @if($row['own_cost_label']) <div>{{ $row['own_cost_label'] }}</div> @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p>Клиент получит состав заказа, согласованные цены и состояние каждой позиции. Недостающие позиции не удаляются, сроки без подтверждения не обещаются.</p>
</div>
