<div class="space-y-3">
    <p>Позиций: <strong>{{ count($plan) }}</strong>. Свободный остаток проверяется повторно при принятии заказа; фактический резерв подтверждает 1С.</p>
    <div style="overflow-x:auto">
        <table style="width:100%;min-width:600px;text-align:left;border-collapse:collapse">
            <thead><tr><th style="padding:8px">Товар / артикул</th><th>Заказано</th><th>Доступно</th><th>Ожидается</th><th>Состояние</th></tr></thead>
            <tbody>
            @foreach($plan as $row)
                <tr style="border-top:1px solid #7775">
                    <td style="padding:10px 8px;max-width:350px">{{ $row['item']->product_name }}<br><small>{{ $row['item']->product_sku }}</small></td>
                    <td>{{ $row['item']->quantity }}</td><td>{{ $row['available'] ?? 'Уточняется' }}</td><td>{{ $row['missing'] ?? 'Уточняется' }}</td>
                    <td>{{ \App\Models\OrderItem::FULFILLMENT_AVAILABILITY_STATUSES[$row['status']] ?? 'Уточняется' }}<br><small>{{ $row['source'] }}</small></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p>Клиент получит состав заказа, согласованные цены и состояние каждой позиции. Недостающие позиции не удаляются, сроки без подтверждения не обещаются.</p>
</div>
