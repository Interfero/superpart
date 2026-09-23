@extends('layouts.app')

@section('title', 'Виды работ — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Виды работ', 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="px-6 py-7">

        <h1 class="text-3xl font-bold text-foreground">
            Виды работ
        </h1>

        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
            Сводная таблица регламента КП: раздел «Что мы принимаем, как и с чем работаем» (вид работ, описание, профильность).
        </p>

    </div>

</div>

<div class="grid gap-4 mb-6 md:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Всего видов работ
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $workTypeStats['total'] }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Профильные
        </div>

        <div class="mt-2 text-3xl font-bold text-primary">
            {{ $workTypeStats['profile'] }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Непрофильные
        </div>

        <div class="mt-2 text-3xl font-bold text-amber-500">
            {{ $workTypeStats['non_profile'] }}
        </div>

    </x-ui.card>

</div>

<x-ui.card padding="none" class="overflow-hidden">

    <div class="overflow-x-auto">

        <table class="table-sticky w-full text-sm text-left">

            <thead class="sticky top-0 z-10 bg-card">

                <tr class="border-b border-border bg-card/95 backdrop-blur">

                    <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap"
                        style="width: 20%;">

                        Вид работ

                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal"
                        style="width: 65%;">

                        Описание

                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap"
                        style="width: 15%;">

                        Профильность

                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse ($workTypes as $index => $workType)

                    <tr class="border-b border-border/60 transition-all hover:bg-primary/5
                        {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">

                        <td class="px-4 py-3 text-foreground align-top font-medium">
                            {{ $workType->name }}
                        </td>

                        <td class="px-4 py-3 text-muted-foreground align-top"><div class="m-0 p-0 indent-0 whitespace-pre-line break-words">{{ $workType->displayDescription() }}</div></td>

                        <td class="px-4 py-3 align-top whitespace-nowrap">

                            @if ($workType->is_profile)

                                <span class="inline-flex rounded-full bg-green-500/10 px-3 py-1 text-xs font-semibold text-green-500">
                                    Профильная
                                </span>

                            @else

                                <span class="inline-flex rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-500">
                                    Непрофильная
                                </span>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="3"
                            class="px-4 py-10 text-center text-muted-foreground">

                            Нет видов работ

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="px-4 py-3 border-t border-border bg-muted/10">

        <div class="text-sm text-muted-foreground">
            Найдено видов работ: {{ $workTypes->total() }}
        </div>

    </div>

    @if ($workTypes->hasPages())

        <div class="px-4 py-3 border-t border-border">

            {{ $workTypes->links() }}

        </div>

    @endif

</x-ui.card>

@endsection