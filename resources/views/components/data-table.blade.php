@props(['columns', 'rows', 'tableId' => 'data-table-' . uniqid(), 'paginator' => null])

<x-ui.card padding="none" class="shadow-sm" data-table="{{ $tableId }}">
    <table class="table-sticky w-full text-sm text-left">
        <thead>
            {{-- Column headers --}}
            <tr class="border-b border-border">
                @foreach ($columns as $col)
                    <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap
                               {{ !empty($col['sortable']) ? 'cursor-pointer select-none hover:text-foreground transition-colors' : '' }}"
                        @if (!empty($col['sortable']))
                            data-sort-key="{{ $col['key'] }}"
                        @endif
                    >
                        <span class="inline-flex items-center gap-1">
                            {{ $col['label'] }}
                            @if (!empty($col['sortable']))
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            @endif
                        </span>
                    </th>
                @endforeach
            </tr>

            {{-- Filter row --}}
            @if (collect($columns)->contains(fn ($col) => !empty($col['filterable'])))
                <tr class="border-b border-border bg-muted/10">
                    @foreach ($columns as $col)
                        <td class="px-3 py-2">
                            @if (!empty($col['filterable']))
                                @if (($col['filter_type'] ?? 'search') === 'select')
                                    <x-ui.select size="sm" class="table-filter" data-filter-key="{{ $col['key'] }}">
                                        <option value="">—</option>
                                        @foreach ($col['filter_options'] ?? [] as $optValue => $optLabel)
                                            <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                        @endforeach
                                    </x-ui.select>
                                @else
                                    <x-ui.filter-input
                                        type="text"
                                        size="sm"
                                        class="table-filter"
                                        data-filter-key="{{ $col['key'] }}"
                                        placeholder=""
                                    />
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </thead>

        <tbody>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                @forelse ($rows as $index => $row)
                    <tr class="border-b border-border/60 transition-colors hover:bg-muted/40
                               {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                        data-row
                        @foreach ($columns as $col)
                            data-col-{{ $col['key'] }}="{{ is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '') }}"
                        @endforeach
                    >
                        @foreach ($columns as $col)
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '') }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-3 py-8 text-center text-muted-foreground">
                            Нет данных
                        </td>
                    </tr>
                @endforelse
            @endif
        </tbody>
    </table>
</x-ui.card>

@if ($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
    <div class="pt-4">
        {{ $paginator->links() }}
    </div>
@endif
