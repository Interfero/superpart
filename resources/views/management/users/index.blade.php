@php
    use App\Models\User;

    $roleLabels = [
        User::ROLE_DEVELOPER => 'Разработчик',
        User::ROLE_GENERAL_DIRECTOR => 'Генеральный директор',
        User::ROLE_PARTNER => 'Партнёр',
        User::ROLE_MANAGER => 'Менеджер партнёра',
    ];
@endphp

@extends('layouts.app')

@section('title', 'Список сотрудников — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Справочники', 'url' => null],
        ['label' => 'Список сотрудников', 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="px-6 py-7">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <h1 class="text-3xl font-bold text-foreground">Список сотрудников</h1>

            <x-ui.button
                tag="a"
                :href="route('management.users.create')"
                variant="primary"
                size="md"
                class="shrink-0"
            >
                Создать сотрудника
            </x-ui.button>
        </div>

        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
            @if (auth()->user()->isPartner())
                Ваши менеджеры партнёра: просмотр и редактирование карточек.
            @else
                Управление сотрудниками, партнёрами и менеджерами системы.
            @endif
        </p>
    </div>

</div>

@if (session('success'))

    <x-ui.alert type="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>

@endif

@if (session('generated_password'))

    <x-ui.card padding="md" class="mb-6 border-primary/40 bg-primary/5">

        <p class="text-sm font-medium text-foreground mb-3">
            Сгенерированный пароль
        </p>

        <div class="flex flex-wrap items-center gap-2">

            <code id="generated-password-value"
                  class="rounded border border-border bg-muted/40 px-3 py-2 text-sm font-mono text-foreground select-all">
                {{ session('generated_password') }}
            </code>

            <x-ui.button
                type="button"
                variant="outline"
                size="sm"
                id="copy-generated-password"
            >
                Копировать
            </x-ui.button>

        </div>

    </x-ui.card>

@endif

<div class="grid gap-4 mb-6 md:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Всего сотрудников
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $users->total() }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            На странице
        </div>

        <div class="mt-2 text-3xl font-bold text-primary">
            {{ $users->count() }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Текущая страница
        </div>

        <div class="mt-2 text-3xl font-bold text-blue-500">
            {{ $users->currentPage() }}
        </div>

    </x-ui.card>

</div>

@if (! auth()->user()->isPartner())

    <x-ui.card padding="md" class="mb-6">

        <form method="GET"
              action="{{ route('management.users.index') }}"
              class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">

            <div class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-sm text-muted-foreground">Роль</label>
                <select name="role" class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm">
                    <option value="">Все</option>
                    @foreach ($roleLabels as $role => $label)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[16rem] flex-[2]">
                <label class="mb-1 block text-sm text-muted-foreground">Партнёр</label>
                <select name="partner_id" class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm">
                    <option value="">Все партнёры</option>
                    @foreach (($partnerFilterOptions ?? collect()) as $partner)
                        <option value="{{ $partner->id }}" @selected((string) request('partner_id') === (string) $partner->id)>
                            {{ $partner->name }} — {{ $partner->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">

                <x-ui.button
                    type="submit"
                    variant="primary"
                    size="md"
                >
                    Применить
                </x-ui.button>

                <x-ui.button
                    tag="a"
                    :href="route('management.users.index')"
                    variant="secondary"
                    size="md"
                >
                    Сбросить
                </x-ui.button>

            </div>

        </form>

    </x-ui.card>

@endif

<x-ui.card padding="none" class="overflow-hidden">

    <div class="overflow-x-auto">

        <table class="table-sticky w-full text-sm text-left">

            <thead class="sticky top-0 z-10 bg-card">

                <tr class="border-b border-border bg-card/95 backdrop-blur">

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        ID
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Эл. почта
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        ФИО
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Статус
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Партнёр
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal">
                        Последний визит
                    </th>

                    <th class="px-4 py-3 text-muted-foreground font-normal"></th>

                </tr>

            </thead>

            <tbody>

                @forelse ($users as $index => $u)

                    <tr class="border-b border-border/60 transition-all hover:bg-primary/5
                        {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">

                        <td class="px-4 py-3 text-foreground font-medium">
                            {{ $u->id }}
                        </td>

                        <td class="px-4 py-3 text-foreground whitespace-nowrap">
                            {{ $u->email }}
                        </td>

                        <td class="px-4 py-3 text-foreground">
                            {{ $u->displayFullName() }}
                        </td>

                        <td class="px-4 py-3">

                            <span class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                {{ $u->roleLabel() }}
                            </span>

                        </td>

                        <td class="px-4 py-3 text-muted-foreground">

                            @if ($u->parent)

                                {{ $u->parent->displayFullName() }}

                            @elseif ($u->role === User::ROLE_PARTNER)

                                сам партнёр

                            @else

                                —

                            @endif

                        </td>

                        <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">

                            @if ($u->last_login_at)

                                {{ $u->last_login_at->format('d.m.Y, H:i') }}

                            @else

                                —

                            @endif

                        </td>

                        <td class="px-4 py-3 text-right align-middle">

                            @if (auth()->user()->hasElevatedAccess() || (auth()->user()->isPartner() && (int) $u->parent_user_id === (int) auth()->id() && $u->isManager()))

                                <x-ui.button
                                    tag="a"
                                    :href="route('management.users.edit', $u)"
                                    variant="outline"
                                    size="sm"
                                >
                                    Карточка
                                </x-ui.button>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground">

                            Нет сотрудников

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="px-4 py-3 border-t border-border bg-muted/10">

        <div class="text-sm text-muted-foreground">
            Найдено сотрудников: {{ $users->total() }}
        </div>

    </div>

    @if ($users->hasPages())

        <div class="px-4 py-3 border-t border-border">

            {{ $users->links() }}

        </div>

    @endif

</x-ui.card>

@endsection

@push('scripts')

<script>
(function () {

    const btn = document.getElementById('copy-generated-password');
    const el = document.getElementById('generated-password-value');

    if (!btn || !el) return;

    btn.addEventListener('click', function () {

        const t = el.textContent || '';

        if (navigator.clipboard && navigator.clipboard.writeText) {

            navigator.clipboard.writeText(t).then(function () {

                btn.textContent = 'Скопировано';

                setTimeout(function () {
                    btn.textContent = 'Копировать';
                }, 2000);

            });

        }

    });

})();
</script>

@endpush