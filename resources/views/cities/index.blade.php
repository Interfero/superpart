@extends('layouts.app')

@section('title', 'Актуальные города — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Города', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">
        <div class="px-6 py-7">
            <h1 class="text-3xl font-bold text-foreground">
                Актуальные города партнёров
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Список городов, доступность для партнёров и быстрый поиск по ID или названию.
            </p>
        </div>
    </div>

    <div class="grid gap-4 mb-6 md:grid-cols-3">
        <x-ui.card>
            <div class="text-sm text-muted-foreground">Всего найдено</div>
            <div class="mt-2 text-3xl font-bold text-foreground">{{ $cities->total() }}</div>
        </x-ui.card>

        <x-ui.card>
            <div class="text-sm text-muted-foreground">Показано на странице</div>
            <div class="mt-2 text-3xl font-bold text-blue-500">{{ $cities->count() }}</div>
        </x-ui.card>

        <x-ui.card>
            <div class="text-sm text-muted-foreground">Фильтр доступа</div>
            <div class="mt-2 text-3xl font-bold text-primary">
                {{ $available === '1' ? 'Да' : ($available === '0' ? 'Нет' : 'Все') }}
            </div>
        </x-ui.card>
    </div>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-sticky w-full text-sm text-left">
                <thead class="sticky top-0 z-10 bg-card">
                    <tr class="border-b border-border bg-card/95 backdrop-blur">
                        <th class="px-4 py-3 text-muted-foreground font-normal">Город</th>
                        <th class="px-4 py-3 text-muted-foreground font-normal">ID</th>
                        <th class="px-4 py-3 text-muted-foreground font-normal">Доступ партнера</th>
                    </tr>

                    <tr class="border-b border-border bg-muted/30">
                        <td class="px-4 py-2">
                            <x-ui.filter-input
                                type="text"
                                id="filter-name"
                                value="{{ request('name') }}"
                                size="sm"
                                placeholder=""
                            />
                        </td>

                        <td class="px-4 py-2">
                            <x-ui.filter-input
                                type="text"
                                id="filter-city-id"
                                value="{{ request('city_id') }}"
                                size="sm"
                                placeholder=""
                            />
                        </td>

                        <td class="px-4 py-2">
                            <select id="filter-available"
                                    class="w-full bg-input border border-border text-foreground text-xs rounded px-2 py-1.5 outline-none focus:border-ring">
                                <option value="1" {{ $available === '1' ? 'selected' : '' }}>Да</option>
                                <option value="0" {{ $available === '0' ? 'selected' : '' }}>Нет</option>
                                <option value="all" {{ $available === 'all' ? 'selected' : '' }}>Все</option>
                            </select>
                        </td>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($cities as $index => $city)
                        <tr class="border-b border-border/60 transition-all hover:bg-primary/5 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">
                            <td class="px-4 py-3 text-foreground font-medium">{{ $city->name }}</td>
                            <td class="px-4 py-3 text-foreground">{{ $city->id }}</td>
                            <td class="px-4 py-3">
                                @if ($city->is_available)
                                    <span class="inline-flex rounded-full bg-green-500/10 px-3 py-1 text-xs font-semibold text-green-500">
                                        Да
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-500/10 px-3 py-1 text-xs font-semibold text-red-500">
                                        Нет
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-muted-foreground">
                                Нет городов
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-border bg-muted/10">
            <div class="text-sm text-muted-foreground">
                Найдено городов: {{ $cities->total() }}
            </div>
        </div>

        @if ($cities->hasPages())
            <div class="px-4 py-3 border-t border-border">
                {{ $cities->links() }}
            </div>
        @endif
    </x-ui.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterName = document.getElementById('filter-name');
    const filterCityId = document.getElementById('filter-city-id');
    const filterAvailable = document.getElementById('filter-available');

    function applyFilters() {
        const params = new URLSearchParams();

        if (filterName.value.trim()) {
            params.set('name', filterName.value.trim());
        }

        if (filterCityId.value.trim()) {
            params.set('city_id', filterCityId.value.trim());
        }

        params.set('available', filterAvailable.value);

        window.location.href = '{{ route("cities.index") }}?' + params.toString();
    }

    let nameTimeout;
    let cityIdTimeout;
    const debounceMs = 350;

    filterName.addEventListener('input', function () {
        clearTimeout(nameTimeout);
        nameTimeout = setTimeout(applyFilters, debounceMs);
    });

    filterCityId.addEventListener('input', function () {
        clearTimeout(cityIdTimeout);
        cityIdTimeout = setTimeout(applyFilters, debounceMs);
    });

    filterName.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(nameTimeout);
            applyFilters();
        }
    });

    filterCityId.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(cityIdTimeout);
            applyFilters();
        }
    });

    filterAvailable.addEventListener('change', applyFilters);
});
</script>
@endpush