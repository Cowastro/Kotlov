<div style="display:grid;gap:18px;justify-items:center;text-align:center">
    @if($qr)
        <img src="{{ $qr }}" alt="QR-код заказа {{ $order->number }}" style="width:280px;max-width:100%;height:auto;background:#fff;padding:12px;border-radius:14px">
    @endif
    <div>
        <strong>{{ $order->number }}</strong>
        <div style="margin-top:6px;color:#9ca3af">Одна ссылка открывает весь заказ со всеми позициями и текущими статусами.</div>
        <div style="margin-top:6px;color:#f59e0b">Действует до {{ $expiresAt->timezone('Europe/Minsk')->format('d.m.Y H:i') }} или до завершения/отмены заказа.</div>
    </div>
    <input type="text" value="{{ $url }}" readonly onclick="this.select()" style="width:100%;padding:10px 12px;border:1px solid #4b5563;border-radius:8px;background:#111827;color:#f9fafb">
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="color:#60a5fa">Открыть защищённую ссылку ↗</a>
</div>
