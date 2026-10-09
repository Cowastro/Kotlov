<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;

class IntegrationIssueAdvisor
{
    /** @return array{title:string,steps:array<int,string>,note:string} */
    public function advise(IntegrationIssue $issue): array
    {
        return match ($issue->type) {
            'integration_stale' => [
                'title' => 'Восстановить автоматический обмен',
                'steps' => [
                    'Откройте регламентное задание обмена на стороне 1С.',
                    'Проверьте адрес, пользователя и последний текст ошибки.',
                    'Запустите один цикл вручную и убедитесь, что он появился в журнале обмена.',
                ],
                'note' => 'Сайт не подключается к 1С сам: инициатором CommerceML-обмена является 1С.',
            ],
            'order_not_exported' => [
                'title' => 'Передать заказ в 1С',
                'steps' => [
                    'Запустите обмен заказами в 1С.',
                    'Дождитесь подтверждения успешного получения от 1С.',
                    'Проверьте номер заказа в журнале и его карточке.',
                ],
                'note' => 'Не отмечайте заказ переданным вручную: отметка ставится только после ответа success от 1С.',
            ],
            'order_no_1c_response' => [
                'title' => 'Получить статус заказа из 1С',
                'steps' => [
                    'Проверьте, что документ заказа создан в 1С.',
                    'Включите выгрузку статусов в регулярный цикл обмена.',
                    'После обмена проверьте временную линию заказа на сайте.',
                ],
                'note' => 'Статус сайта меняется только по распознанному статусу из 1С и сохраняется в истории.',
            ],
            'product_identity_collision' => [
                'title' => 'Проверить возможный дубль из 1С',
                'steps' => [
                    'Откройте связанную позицию и сравните перечисленные внешние ID, артикул или штрихкод.',
                    'Если это один товар после смены ID в 1С, оставьте одну актуальную связь с карточкой сайта.',
                    'Если это разные варианты товара, исправьте повторяющийся артикул или штрихкод в 1С и повторите обмен.',
                ],
                'note' => 'Система только предупреждает: она не удаляет и не объединяет товары автоматически.',
            ],
            'product_attention', 'product_unmatched', 'product_missing_category', 'product_missing_price' => $this->productAdvice($issue),
            default => [
                'title' => 'Проверить объект и журнал',
                'steps' => ['Откройте связанный объект.', 'Сверьте последнее событие в журнале обмена.', 'После устранения запустите повторную проверку.'],
                'note' => 'Проблема закроется автоматически, когда причина исчезнет.',
            ],
        };
    }

    /** @return array{title:string,steps:array<int,string>,note:string} */
    private function productAdvice(IntegrationIssue $issue): array
    {
        $missingPrice = (bool) data_get($issue->context, 'missing_price', $issue->type === 'product_missing_price');
        $unmatched = (bool) data_get($issue->context, 'unmatched', $issue->type === 'product_unmatched');
        $missingCategory = (bool) data_get($issue->context, 'missing_category', $issue->type === 'product_missing_category');
        $steps = [];

        if ($unmatched) {
            $steps[] = 'Откройте товар и подтвердите существующую карточку либо надёжную рекомендацию.';
        }
        if ($missingCategory) {
            $steps[] = 'Назначьте категорию сайта вручную или массовым действием до создания новой карточки.';
        }
        if ($missingPrice) {
            $steps[] = 'Проверьте в 1С вид цены и повторите выгрузку предложений: нулевая цена не публикуется.';
        }

        return [
            'title' => match (true) {
                $missingPrice && $unmatched => 'Сначала привязать товар и получить цену',
                $missingPrice => 'Получить корректную цену из 1С',
                $unmatched => 'Подтвердить привязку товара',
                default => 'Назначить категорию сайта',
            },
            'steps' => $steps ?: ['Откройте товар и проверьте текущую привязку, категорию и цену.'],
            'note' => 'Автоматическое применение отключено: решение подтверждает администратор.',
        ];
    }
}
