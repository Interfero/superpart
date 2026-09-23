@php

    $formAction = $formAction ?? '/orders';

    $tabsUrl = $tabsUrl ?? $formAction;

    $showExport = $showExport ?? true;

    $showCreateButton = $showCreateButton ?? false;

    $filtersOpen = $filtersAlwaysVisible ?? ! request()->boolean('hide_filters');

@endphp



<x-ui.card padding="none" class="overflow-hidden" data-orders-list-panel>

    <div class="flex flex-wrap items-center gap-2 border-b border-border px-4 py-2">

        <div class="min-w-0 flex-1">

            <x-tabs :tabs="$tabs" :active="$activeStatus" :url="$tabsUrl" />

        </div>



        <div class="flex shrink-0 items-center gap-2">

            @if ($showCreateButton)

                <x-ui.button tag="a" :href="route('orders.create')" variant="primary" size="sm">

                    Создать

                </x-ui.button>

            @endif



            @unless ($filtersAlwaysVisible ?? false)

                <x-ui.button

                    type="button"

                    variant="outline"

                    size="sm"

                    data-orders-filter-toggle

                    aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}"

                    title="Фильтр"

                >

                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">

                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 2v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>

                    </svg>

                </x-ui.button>

            @endunless



            @if ($showExport)

                <div class="dropdown-menu relative" data-dropdown>

                    <button

                        type="button"

                        class="rounded border border-border p-2 text-muted-foreground transition-colors hover:text-foreground"

                        title="Экспорт"

                        data-dropdown-toggle

                    >

                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">

                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>

                        </svg>

                    </button>



                    <div

                        class="dropdown-panel invisible absolute right-0 top-full z-50 mt-1 min-w-[160px] rounded-md border border-border bg-card opacity-0 shadow-xl transition-all duration-200"

                        data-dropdown-panel

                    >

                        <a

                            href="{{ route('orders.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"

                            class="block rounded-t-md px-4 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"

                        >

                            Excel (XLSX)

                        </a>



                        <a

                            href="{{ route('orders.export', array_merge(request()->query(), ['format' => 'csv'])) }}"

                            class="block rounded-b-md px-4 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"

                        >

                            CSV

                        </a>

                    </div>

                </div>

            @endif

        </div>

    </div>



    <form method="GET" action="{{ $formAction }}" id="orders-filter-form">

        <input type="hidden" name="status" value="{{ $activeStatus }}">



        @if ($filtersOpen && ! ($filtersAlwaysVisible ?? false))

            <input type="hidden" name="filters" value="1">

        @endif



        @if (request('sort_by'))

            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">

        @endif



        @if (request('sort_dir'))

            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">

        @endif



        <div class="overflow-x-auto">

            <table class="table-sticky w-full text-left text-sm">

                <thead class="sticky top-0 z-10 bg-card">

                    <tr class="border-b border-border bg-card/95 backdrop-blur">

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">ID заявки</th>

                        <th class="w-8 whitespace-nowrap px-2 py-3 font-normal text-muted-foreground"></th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">НПр</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Город</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Статус</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Вид</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Послужный адрес</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Источник</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Время заявки</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Имя</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Телефон</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Создано (лок)</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Начисление</th>

                        <th class="whitespace-nowrap px-3 py-3 font-normal text-muted-foreground">Сотрудник</th>

                    </tr>



                    <tr

                        id="orders-filter-row"

                        class="border-b border-border bg-muted/30 {{ $filtersOpen ? '' : 'hidden' }}"

                    >

                        <td class="px-3 py-2">

                            <x-ui.filter-input type="text" name="filter_id" value="{{ request('filter_id') }}" size="sm" class="table-filter" data-filter-key="id" />

                        </td>

                        <td class="px-2 py-2"></td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_city" size="sm" class="table-filter" data-filter-key="city">

                                <option value="">—</option>

                                @foreach ($filterOptions['cities'] as $cityId => $cityName)

                                    <option value="{{ $cityId }}" @selected(request('filter_city') == $cityId)>{{ $cityName }}</option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_status" size="sm" class="table-filter" data-filter-key="status">

                                <option value="">—</option>

                                @foreach ($filterOptions['statuses'] as $statusKey => $statusLabel)

                                    <option value="{{ $statusKey }}" @selected(request('filter_status') == $statusKey)>{{ $statusLabel }}</option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_type" size="sm" class="table-filter" data-filter-key="type">

                                <option value="">—</option>

                                @foreach ($filterOptions['types'] as $typeKey => $typeLabel)

                                    <option value="{{ $typeKey }}" @selected(request('filter_type') == $typeKey)>{{ $typeLabel }}</option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_source" size="sm" class="table-filter" data-filter-key="source">

                                <option value="">—</option>

                                @foreach ($filterOptions['sources'] as $sourceId => $sourceName)

                                    <option value="{{ $sourceId }}" @selected(request('filter_source') == $sourceId)>{{ $sourceName }}</option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.filter-input type="text" name="filter_name" value="{{ request('filter_name') }}" size="sm" class="table-filter" data-filter-key="client_name" />

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.filter-input type="text" name="filter_phone" value="{{ request('filter_phone') }}" size="sm" class="table-filter" data-filter-key="client_phone" />

                        </td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2"></td>

                    </tr>

                </thead>



                <tbody>

                    @php

                        $typeLabels = \App\Support\PartnerOrdersList::TYPE_LABELS;

                        $typeLabels['first'] = 'Впервые';

                    @endphp



                    @forelse ($orders as $index => $order)

                        <tr

                            class="{{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }} cursor-pointer border-b border-border/60 transition-all hover:bg-primary/5"

                            data-row

                            data-href="{{ route('orders.show', $order->id) }}"

                        >

                            <td class="whitespace-nowrap px-3 py-3 font-medium text-foreground">

                                {{ $order->displayNumber() }}

                            </td>

                            <td class="whitespace-nowrap px-2 py-3">

                                <a href="{{ route('orders.show', $order->id) }}" target="_blank" class="text-primary transition-colors hover:text-primary/80" onclick="event.stopPropagation();" title="Открыть в новой вкладке">

                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">

                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>

                                    </svg>

                                </a>

                            </td>

                            <td class="whitespace-nowrap px-3 py-3 text-center">

                                @if ($order->is_non_profile)

                                    <span class="font-bold text-primary">✓</span>

                                @endif

                            </td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->city->name ?? '' }}</td>

                            <td class="whitespace-nowrap px-3 py-3"><x-status-badge :status="$order->status" type="order" /></td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $typeLabels[$order->type] ?? $order->type }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ implode(', ', array_filter([$order->settlement ?? '', $order->address ?? ''])) ?: '' }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->sourceDisplayName() }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->order_time ? $order->order_time->format('d.m.Y, H:i') : '' }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->client_name }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->client_phone }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->created_local ? $order->created_local->format('d.m.Y, H:i') : '' }}</td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">

                                @if ($order->charge_amount > 0)

                                    {{ number_format($order->charge_amount, 0, ',', ' ') }} ₽

                                @endif

                            </td>

                            <td class="whitespace-nowrap px-3 py-3 text-foreground">{{ $order->creatorDisplayName() }}</td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="14" class="px-3 py-8 text-center text-muted-foreground">Нет заявок</td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </form>



    <div class="border-t border-border bg-muted/10 px-4 py-3">

        <div class="text-sm text-muted-foreground">Найдено заявок: {{ $orders->total() }}</div>

    </div>



    @if ($orders->hasPages())

        <div class="border-t border-border px-4 py-3">

            {{ $orders->links() }}

        </div>

    @endif

</x-ui.card>



@once

    @push('scripts')

        <script>

            document.addEventListener('DOMContentLoaded', () => {

                document.querySelectorAll('[data-orders-list-panel]').forEach((panel) => {

                    const form = panel.querySelector('#orders-filter-form');

                    const filterRow = panel.querySelector('#orders-filter-row');

                    const toggle = panel.querySelector('[data-orders-filter-toggle]');



                    toggle?.addEventListener('click', () => {

                        if (!filterRow || !form) return;



                        const willOpen = filterRow.classList.contains('hidden');

                        filterRow.classList.toggle('hidden', !willOpen);

                        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');



                        let hidden = form.querySelector('input[name="filters"]');

                        if (willOpen) {

                            if (!hidden) {

                                hidden = document.createElement('input');

                                hidden.type = 'hidden';

                                hidden.name = 'filters';

                                hidden.value = '1';

                                form.appendChild(hidden);

                            }

                        } else if (hidden) {

                            hidden.remove();

                        }

                    });



                    form?.querySelectorAll('[data-row]').forEach((row) => {

                        row.addEventListener('click', () => {

                            const href = row.dataset.href;

                            if (href) window.location.href = href;

                        });

                    });



                    if (form && typeof window.superpartBindFilterForm === 'function') {

                        window.superpartBindFilterForm(form);

                    }

                });

            });

        </script>

    @endpush

@endonce

