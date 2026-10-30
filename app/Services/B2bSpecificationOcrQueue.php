<?php

namespace App\Services;

use App\Models\B2bSpecification;
use App\Models\B2bSpecificationAttachment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class B2bSpecificationOcrQueue
{
    private const DOCUMENT_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    public function __construct(private readonly B2bSpecificationOcrService $ocr) {}

    /** @param array<int, int|string> $attachmentIds
     * @return array{batch_id:string,count:int}
     */
    public function enqueue(B2bSpecification $specification, array $attachmentIds, User $actor): array
    {
        $ids = collect($attachmentIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $batchId = (string) Str::uuid();
        $count = DB::transaction(function () use ($specification, $ids, $actor, $batchId): int {
            $locked = B2bSpecification::query()->lockForUpdate()->findOrFail($specification->id);
            if ($locked->attachments()->whereIn('ocr_status', ['queued', 'processing'])->exists()) {
                throw new RuntimeException('Для этой спецификации уже выполняется распознавание. Дождитесь его завершения.');
            }
            $attachments = $locked->attachments()->whereKey($ids)->get()->filter(
                fn (B2bSpecificationAttachment $attachment): bool => in_array($this->extension($attachment), self::DOCUMENT_EXTENSIONS, true),
            );
            if ($attachments->count() !== $ids->count() || $attachments->isEmpty()) {
                throw new RuntimeException('Выберите PDF или изображения только из этой спецификации.');
            }
            B2bSpecificationAttachment::query()
                ->whereIn('id', $attachments->modelKeys())
                ->update([
                    'ocr_batch_id' => $batchId,
                    'ocr_status' => 'queued',
                    'ocr_message' => 'Ожидает фонового распознавания DeepSeek.',
                    'ocr_requested_by' => $actor->id,
                    'ocr_requested_at' => now(),
                    'ocr_started_at' => null,
                    'ocr_completed_at' => null,
                ]);
            $locked->update([
                'parsing_status' => 'pending',
                'parse_message' => 'DeepSeek: файлы поставлены в очередь — '.$attachments->count().'.',
                'parsed_at' => null,
            ]);

            return $attachments->count();
        });

        return ['batch_id' => $batchId, 'count' => $count];
    }

    /** @return array{status:string,message:string,specification_id?:int,files?:int} */
    public function processBatch(string $batchId): array
    {
        $claimed = DB::transaction(function () use ($batchId): array {
            $attachments = B2bSpecificationAttachment::query()
                ->where('ocr_batch_id', $batchId)
                ->where('ocr_status', 'queued')
                ->lockForUpdate()
                ->get();
            if ($attachments->isEmpty()) {
                return [];
            }
            $specificationIds = $attachments->pluck('b2b_specification_id')->unique();
            if ($specificationIds->count() !== 1) {
                throw new RuntimeException('Пакет OCR содержит файлы разных спецификаций.');
            }
            $attachments->toQuery()->update([
                'ocr_status' => 'processing',
                'ocr_message' => 'DeepSeek распознаёт документ.',
                'ocr_started_at' => now(),
                'ocr_completed_at' => null,
                'ocr_attempts' => DB::raw('ocr_attempts + 1'),
            ]);
            B2bSpecification::query()->whereKey($specificationIds->first())->update([
                'parsing_status' => 'pending',
                'parse_message' => 'DeepSeek распознаёт файлы: '.$attachments->count().'.',
                'parsed_at' => null,
            ]);

            return [
                'specification_id' => (int) $specificationIds->first(),
                'attachment_ids' => $attachments->modelKeys(),
                'requested_by' => (int) ($attachments->first()->ocr_requested_by ?? 0),
            ];
        });
        if ($claimed === []) {
            return ['status' => 'skipped', 'message' => 'Пакет уже обработан или отменён.'];
        }

        $specification = B2bSpecification::query()->with(['attachments', 'currentVersion'])->findOrFail($claimed['specification_id']);
        $actor = $claimed['requested_by'] > 0 ? User::query()->find($claimed['requested_by']) : null;
        try {
            $result = $this->ocr->recognize($specification, $claimed['attachment_ids'], $actor);
            B2bSpecificationAttachment::query()->whereIn('id', $claimed['attachment_ids'])->update([
                'ocr_status' => 'completed',
                'ocr_message' => 'Распознавание завершено. Создана редакция №'.$result['version'].'.',
                'ocr_completed_at' => now(),
            ]);
            if ($actor) {
                Notification::make()
                    ->title('Спецификация распознана')
                    ->body($specification->reference.': '.$result['message'])
                    ->success()
                    ->persistent()
                    ->sendToDatabase($actor);
            }

            return [
                'status' => 'completed',
                'message' => $result['message'],
                'specification_id' => $specification->id,
                'files' => count($claimed['attachment_ids']),
            ];
        } catch (Throwable $exception) {
            report($exception);
            $message = Str::limit($exception->getMessage(), 700, '');
            B2bSpecificationAttachment::query()->whereIn('id', $claimed['attachment_ids'])->update([
                'ocr_status' => 'failed',
                'ocr_message' => $message,
                'ocr_completed_at' => now(),
            ]);
            $specification->update([
                'parsing_status' => 'failed',
                'parse_message' => 'DeepSeek не обработал файлы. Текущая проверенная редакция сохранена. '.$message,
                'parsed_at' => now(),
            ]);
            if ($actor) {
                Notification::make()
                    ->title('DeepSeek не распознал спецификацию')
                    ->body($specification->reference.': '.$message)
                    ->danger()
                    ->persistent()
                    ->sendToDatabase($actor);
            }

            return [
                'status' => 'failed',
                'message' => $message,
                'specification_id' => $specification->id,
                'files' => count($claimed['attachment_ids']),
            ];
        }
    }

    public function failStaleBatches(): int
    {
        $stale = B2bSpecificationAttachment::query()
            ->where('ocr_status', 'processing')
            ->where('ocr_started_at', '<', now()->subMinutes(20))
            ->get();
        if ($stale->isEmpty()) {
            return 0;
        }
        $stale->toQuery()->update([
            'ocr_status' => 'failed',
            'ocr_message' => 'Фоновая обработка прервалась по тайм-ауту. Запустите файл повторно.',
            'ocr_completed_at' => now(),
        ]);
        B2bSpecification::query()->whereIn('id', $stale->pluck('b2b_specification_id')->unique())->update([
            'parsing_status' => 'failed',
            'parse_message' => 'Фоновая обработка прервалась по тайм-ауту. Текущая редакция сохранена.',
            'parsed_at' => now(),
        ]);

        return $stale->count();
    }

    private function extension(B2bSpecificationAttachment $attachment): string
    {
        return mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
    }
}
