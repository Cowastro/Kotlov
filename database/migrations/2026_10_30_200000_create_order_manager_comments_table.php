<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_manager_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        DB::table('orders')
            ->whereNotNull('admin_comment')
            ->where('admin_comment', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    foreach ($this->legacyEntries((string) $order->admin_comment, $order) as $entry) {
                        DB::table('order_manager_comments')->insert($entry);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_manager_comments');
    }

    /** @return list<array<string, mixed>> */
    private function legacyEntries(string $comment, object $order): array
    {
        $comment = trim($comment);
        if ($comment === '') {
            return [];
        }

        // Старые записи могли визуально склеиться. Разделяем и по пустой
        // строке, и перед каждой сохранённой временной меткой.
        $comment = preg_replace(
            '/\s+(?=\[\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}\])/u',
            "\n\n",
            $comment,
        ) ?? $comment;
        $parts = preg_split('/\R{2,}/u', $comment, -1, PREG_SPLIT_NO_EMPTY) ?: [$comment];
        $fallbackTime = $order->created_at ?: $order->updated_at ?: now();

        return collect($parts)->map(function (string $part) use ($order, $fallbackTime): array {
            $part = trim($part);
            $author = null;
            $body = $part;
            $createdAt = $fallbackTime;

            if (preg_match('/^\[(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\]\s*(.*)$/us', $part, $dated)) {
                $createdAt = CarbonImmutable::createFromFormat(
                    'd.m.Y H:i',
                    $dated[1],
                    config('app.display_timezone', 'Europe/Minsk'),
                )->utc();
                $body = trim($dated[2]);
            }

            if (preg_match('/^(.+?)\s+—\s+Комментарий менеджера:\s*(.*)$/us', $body, $signed)) {
                $author = trim($signed[1]);
                $body = trim($signed[2]);
            } elseif (preg_match('/^Комментарий менеджера:\s*(.*)$/us', $body, $unsigned)) {
                $body = trim($unsigned[1]);
            }

            return [
                'order_id' => $order->id,
                'user_id' => null,
                'author_name' => $author ?: 'Автор не зафиксирован',
                'body' => $body !== '' ? $body : 'Комментарий без текста',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        })->values()->all();
    }
};
