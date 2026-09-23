<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class OrdersExport
{
    private const STATUS_LABELS = [
        'all' => 'Все заявки',
        'in_work' => 'В работе',
        'in_work_sd' => 'В работе СД',
        'on_way' => 'В пути',
        'ready' => 'Готов',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
        'warranty' => 'Гарантия',
    ];

    private const TYPE_LABELS = [
        'first_time' => 'Впервые',
        'first' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];

    public function __construct(
        private readonly Collection $orders,
        private readonly bool $includeCharges = true,
    ) {
    }

    public function download(string $format)
    {
        $fileName = 'orders_' . now()->format('Y-m-d_H-i') . '.' . $format;
        $tempPath = storage_path('app/temp/' . uniqid('orders_export_', true) . '.' . $format);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();
        $writer->openToFile($tempPath);

        $headers = [
            'ID',
            'Город',
            'Статус',
            'Вид',
            'Нас.пункт, адрес',
            'Источник',
            'Вид работ',
            'Время заявки',
            'Имя',
            'Телефон',
            'Создано (лок)',
            'Закрыто (лок)',
        ];

        if ($this->includeCharges) {
            $headers[] = 'Начисление';
        }

        $headers[] = 'Сотрудник';

        $writer->addRow(Row::fromValues($headers));

        foreach ($this->orders as $order) {
            $row = [
                $order->id,
                $order->city?->name ?? '',
                self::STATUS_LABELS[$order->status] ?? $order->status,
                self::TYPE_LABELS[$order->type] ?? $order->type,
                implode(', ', array_filter([$order->settlement ?? '', $order->address ?? ''])) ?: '—',
                $order->sourceDisplayName(),
                $order->workType?->name ?? '',
                $order->order_time?->format('d.m.Y, H:i') ?? '',
                $order->client_name,
                $order->client_phone,
                $order->created_local?->format('d.m.Y, H:i') ?? '',
                $order->closed_local?->format('d.m.Y, H:i') ?? '',
            ];

            if ($this->includeCharges) {
                $row[] = $order->charge_amount !== null ? (float) $order->charge_amount : '';
            }

            $row[] = $order->creatorDisplayName();

            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        $contentType = $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->download($tempPath, $fileName, [
            'Content-Type' => $contentType,
        ])->deleteFileAfterSend(true);
    }
}
