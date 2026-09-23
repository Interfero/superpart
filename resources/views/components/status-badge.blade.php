@props(['status', 'type' => 'order'])

@php
    // Цвета заказов — inline style: произвольные bg-[#...] не попадают в собранный Tailwind без rebuild.
    $orderStyles = [
        'waiting'              => ['background' => '#477F56', 'color' => '#ffffff'],
        'waiting_payment'      => ['background' => '#41758E', 'color' => '#ffffff'],
        'in_work'              => ['background' => '#A35F2D', 'color' => '#ffffff'],
        'in_work_sd'           => ['background' => '#945039', 'color' => '#ffffff'],
        'on_way'               => ['background' => '#3F7954', 'color' => '#ffffff'],
        'ready'                => ['background' => '#47779C', 'color' => '#ffffff'],
        'clarification'        => ['background' => '#AA4750', 'color' => '#ffffff'],
        'not_processed'        => ['background' => '#79547E', 'color' => '#ffffff'],
        'refusal'              => ['background' => '#A44249', 'color' => '#ffffff'],
        'refusal_non_profile'  => ['background' => '#9A454D', 'color' => '#ffffff'],
        'cancelled'            => ['background' => '#4C7482', 'color' => '#ffffff'],
        'warranty'             => ['background' => '#8D661F', 'color' => '#ffffff'],
        'callback'             => ['background' => '#AA4750', 'color' => '#ffffff'],
        'pending'              => ['background' => '#477F56', 'color' => '#ffffff'],
        'cancelled_cc'         => ['background' => '#4C7482', 'color' => '#ffffff'],
        'cancelled_city'       => ['background' => '#4C7482', 'color' => '#ffffff'],
    ];

    $semanticStyles = [
        'transaction' => [
            'charge' => ['background' => '#477F56', 'color' => '#ffffff'],
            'withdrawal' => ['background' => '#A44249', 'color' => '#ffffff'],
            'correction' => ['background' => '#A35F2D', 'color' => '#ffffff'],
        ],
        'withdrawal' => [
            'in_work'   => ['background' => '#8D661F', 'color' => '#ffffff'],
            'completed' => ['background' => '#477F56', 'color' => '#ffffff'],
            'rejected'  => ['background' => '#A44249', 'color' => '#ffffff'],
        ],
        'review' => [
            'new'          => ['background' => '#47779C', 'color' => '#ffffff'],
            'resolved'     => ['background' => '#477F56', 'color' => '#ffffff'],
            'not_resolved' => ['background' => '#A44249', 'color' => '#ffffff'],
        ],
        'employee' => [
            'active'   => ['background' => '#477F56', 'color' => '#ffffff'],
            'inactive' => ['background' => '#526B78', 'color' => '#ffffff'],
        ],
    ];

    $labelMap = [
        'order' => [
            'waiting'              => 'Ожидает',
            'waiting_payment'      => 'Ожидает выплаты',
            'in_work'              => 'В работе',
            'in_work_sd'           => 'В работе СД',
            'on_way'               => 'В пути',
            'ready'                => 'Готов',
            'clarification'        => 'На уточнении',
            'not_processed'        => 'Не оформлена',
            'refusal'              => 'Отказ',
            'refusal_non_profile'  => 'Отказ Непрофиль',
            'cancelled'            => 'Отмена',
            'warranty'             => 'Гарантия',
            'callback'             => 'Прозвон',
            'pending'              => 'Ожидает',
            'cancelled_cc'         => 'Отмена КЦ',
            'cancelled_city'       => 'Отмена Город',
        ],
        'transaction' => [
            'charge' => 'Оплата за заказ',
            'withdrawal' => 'Списание',
            'correction' => 'Корректировка',
        ],
        'withdrawal' => [
            'in_work'   => 'На рассмотрении',
            'completed' => 'Выполнена',
            'rejected'  => 'Отклонена',
        ],
        'review' => [
            'new'          => 'Новая',
            'resolved'     => 'Решена',
            'not_resolved' => 'Не решена',
        ],
        'employee' => [
            'active'   => 'Активный',
            'inactive' => 'Неактивный',
        ],
    ];

    $label = $labelMap[$type][$status] ?? $status;

    $isReview = $type === 'review';
    $isEmployee = $type === 'employee';
    $isOrder = $type === 'order';

    $baseShape = $isReview || $isEmployee
        ? 'inline-block px-2.5 py-1 text-xs font-medium rounded-full border whitespace-nowrap'
        : 'inline-block px-2.5 py-1 text-xs font-medium rounded whitespace-nowrap';

    $classes = $baseShape;

    $palette = $isOrder
        ? ($orderStyles[$status] ?? ['background' => '#526B78', 'color' => '#ffffff'])
        : ($semanticStyles[$type][$status] ?? ['background' => '#526B78', 'color' => '#ffffff']);

    $style = 'background-color: '.$palette['background'].'; color: '.$palette['color'].';';

    if ($isReview || $isEmployee) {
        $style .= ' border-color: rgba(255, 255, 255, 0.24);';
    }
@endphp

<span
    @if ($style) style="{{ $style }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $label }}
</span>
