<?php

namespace Tests\Feature;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderManagerCommentsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_combined_comment_is_split_into_chronological_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00:00', 'Europe/Minsk'));

        $order = Order::query()->create([
            'number' => 'ORD-LEGACY-COMMENTS',
            'status' => 'confirmed',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
            'admin_comment' => "Телефон жены для связи\n\n"
                ."[30.10.2026 11:23] Денис Готто — Комментарий менеджера: МПУТ 685\n\n"
                ."[30.10.2026 12:05] Анна Офис — Комментарий менеджера: Доставка согласована",
        ]);

        $migration = require database_path('migrations/2026_10_30_200000_create_order_manager_comments_table.php');
        $migration->down();
        $migration->up();

        $comments = DB::table('order_manager_comments')
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $comments);
        $this->assertSame('Автор не зафиксирован', $comments[0]->author_name);
        $this->assertSame('Телефон жены для связи', $comments[0]->body);
        $this->assertSame('Денис Готто', $comments[1]->author_name);
        $this->assertSame('МПУТ 685', $comments[1]->body);
        $this->assertSame('Анна Офис', $comments[2]->author_name);
        $this->assertSame('Доставка согласована', $comments[2]->body);
        $this->assertStringStartsWith('2026-10-30 08:23:00', (string) $comments[1]->created_at);
    }
}
