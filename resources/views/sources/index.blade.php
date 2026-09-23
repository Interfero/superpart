@extends('layouts.app')

@section('title', 'Источники — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Источники', 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

        <div>

            <h1 class="text-3xl font-bold text-foreground">
                Список источников
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                @if (!empty($canManageAll))
                    Все источники партнёров: создание, редактирование и передача в CRM.
                @else
                    Ваши источники заявок — создаются в SuperPart и передаются в CRM.
                @endif
            </p>

        </div>

        @can('manage-sources')

            <div>

                <x-ui.button
                    tag="a"
                    :href="route('sources.create')"
                    variant="primary"
                    size="lg"
                >
                    Создать
                </x-ui.button>

            </div>

        @endcan

    </div>

</div>

@if (session('success'))

    <x-ui.alert type="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>

@endif

@if (!empty($levelionConfigured) && $levelionConfigured)

    <x-ui.alert type="info" class="mb-6">
        Источники создаются в SuperPart и автоматически передаются в CRM. В форме заявки доступны только источники из этого списка.
    </x-ui.alert>

@endif

<div class="grid gap-4 mb-6 md:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Всего источников
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $sources->total() }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Рекламные площадки
        </div>

        <div class="mt-2 text-3xl font-bold text-primary">
            {{ $sources->where('source_type', \App\Models\Source::TYPE_ADVERTISING_PLATFORM)->count() }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Текущая страница
        </div>

        <div class="mt-2 text-3xl font-bold text-blue-500">
            {{ $sources->currentPage() }}
        </div>

    </x-ui.card>

</div>

<x-ui.card padding="none" class="overflow-hidden">

    <div class="overflow-x-auto">

        <table class="table-sticky w-full text-sm text-left">

            <thead class="sticky top-0 z-10 bg-card">

                <tr class="border-b border-border bg-card/95 backdrop-blur">

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        ID CRM
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Название
                    </th>

                    @if (!empty($canManageAll))
                        <th class="px-4 py-3 text-muted-foreground font-normal">
                            Партнёр
                        </th>
                    @endif

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Комментарий
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Тип
                    </th>

                    @if (!empty($canManageSources))
                        <th class="px-4 py-3 text-muted-foreground font-normal w-[120px]">
                            Действия
                        </th>
                    @endif

                </tr>

            </thead>

            <tbody>

                @forelse ($sources as $index => $source)

                    <tr
                        class="border-b border-border/60 cursor-pointer transition-all hover:bg-primary/5
                        {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                        tabindex="0"
                        role="link"
                        aria-label="Открыть источник {{ $source->name }}"
                        data-href="{{ route('sources.show', $source->id) }}"
                        onclick="window.location.href=this.dataset.href"
                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href=this.dataset.href;}">

                        <td class="px-4 py-3 text-foreground font-medium tabular-nums">
                            @php $crmId = $source->crmDisplayId(); @endphp
                            @if ($crmId)
                                #{{ $crmId }}
                            @else
                                <span class="text-muted-foreground" title="Ещё нет ID в CRM">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-foreground">
                            {{ $source->name }}
                        </td>

                        @if (!empty($canManageAll))
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ $source->user?->name ?? '—' }}
                            </td>
                        @endif

                        <td class="px-4 py-3 text-muted-foreground">
                            {{ $source->comment ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-foreground">
                            {{ $source->typeLabel() }}
                        </td>

                        @if (!empty($canManageSources))
                            <td class="px-4 py-3" onclick="event.stopPropagation()">
                                <form
                                    method="POST"
                                    action="{{ route('sources.destroy', $source->id) }}"
                                    onsubmit="return confirm('Удалить источник «{{ $source->name }}»?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="destructive" size="sm">
                                        Удалить
                                    </x-ui.button>
                                </form>
                            </td>
                        @endif

                    </tr>

                @empty

                    <tr>

                        <td colspan="{{ (!empty($canManageAll) ? 5 : 4) + (!empty($canManageSources) ? 1 : 0) }}"
                            class="px-4 py-10 text-center text-muted-foreground">

                            Нет источников

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="px-4 py-3 border-t border-border bg-muted/10">

        <div class="text-sm text-muted-foreground">
            Найдено источников: {{ $sources->total() }}
        </div>

    </div>

    @if ($sources->hasPages())

        <div class="px-4 py-3 border-t border-border">

            {{ $sources->links() }}

        </div>

    @endif

</x-ui.card>

@endsection