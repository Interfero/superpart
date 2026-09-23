@extends('layouts.app')

@section('title', 'Новая заявка — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Список заявок', 'url' => route('orders.index')],
        ['label' => 'Новая заявка', 'url' => null],
    ]" />
@endsection

@php
    $oldOrderType = old('order_type');
    if ($oldOrderType === null && old('type') === 'first_time') {
        $oldOrderType = 'new';
    } elseif ($oldOrderType === null && in_array(old('type'), ['repeat', 'warranty'], true)) {
        $oldOrderType = old('type');
    }
    $oldOrderType = $oldOrderType ?? 'new';

    $defaultOrderDate = old('order_date', now()->format('d.m.Y'));
    $defaultMeetingTime = $defaultMeetingTime ?? old('meeting_time', now()->addHour()->format('H:i'));
    $minOrderDateIso = now()->format('Y-m-d');
    try {
        $parsedDefaultDate = \Illuminate\Support\Carbon::createFromFormat('d.m.Y', $defaultOrderDate);
        if ($parsedDefaultDate->format('Y-m-d') < $minOrderDateIso) {
            $defaultOrderDate = now()->format('d.m.Y');
        }
    } catch (\Throwable) {
        $defaultOrderDate = now()->format('d.m.Y');
    }
    $selectedCityId = old('city_id');
    $selectedCityName = $selectedCityId ? ($cities->firstWhere('id', (int) $selectedCityId)?->name ?? '') : '';
    $selectedServerType = old('server_type', request('server_type'));
    $allowedEquipmentCodes = $selectedServerType
        ? \App\Support\OrderEquipment::codesForServerType($selectedServerType)
        : [];
@endphp

@section('content')
    <form id="order-create-form" action="{{ route('orders.store') }}" method="POST" class="mt-4">
        @csrf

        <x-ui.card padding="md">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4 items-end">
                <x-ui.form-group label="Сервер" name="server_type" required class="!mb-0" tip="КП — компьютерная помощь, БТ — бытовая техника, МНЧ — мелкие работы. От сервера зависит список видов работ.">
                    <x-ui.select id="server_type" name="server_type" :error="$errors->has('server_type')">
                        <option value="">Выберите сервер</option>
                        @foreach (($serverTypes ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(old('server_type', request('server_type')) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui.select>
                    <p data-client-error="server_type" class="mt-1 text-xs text-destructive hidden"></p>
                </x-ui.form-group>

                <x-ui.form-group label="Город клиента" name="city_id" required class="!mb-0 min-w-0" hint="Начните вводить название и выберите город из подсказок." id="city_search">
                    @if ($cities->isEmpty())
                        <p class="mb-2 text-xs text-destructive">Нет доступных городов. Обратитесь к администратору.</p>
                    @endif
                    <div class="relative">
                        <input type="hidden" name="city_id" id="city_id" value="{{ $selectedCityId }}">
                        <input
                            type="text"
                            id="city_search"
                            class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground outline-none placeholder-muted-foreground/70 transition-colors focus:border-border focus:ring-0 focus-visible:border-muted-foreground/50 focus-visible:ring-0 @error('city_id') border-destructive @enderror"
                            value="{{ old('city_search', $selectedCityName) }}"
                            placeholder="Начните вводить город…"
                            autocomplete="off"
                            role="combobox"
                            aria-expanded="false"
                            aria-controls="city_suggestions"
                            @disabled($cities->isEmpty())
                        />
                        <ul
                            id="city_suggestions"
                            role="listbox"
                            class="absolute z-50 mt-1 hidden max-h-52 w-full overflow-auto rounded-md border border-border bg-card py-1 shadow-lg"
                        ></ul>
                    </div>
                    <p data-client-error="city_id" class="mt-1 text-xs text-destructive hidden"></p>
                </x-ui.form-group>

                <div class="min-w-0">
                    <x-input-phone-ru
                        name="client_phone"
                        id="client_phone"
                        label="Телефон клиента"
                        :required="true"
                        :error="$errors->has('client_phone')"
                        tip="Формат: +7 и 10 цифр, например +7 960 701 12 63"
                    />

                    @error('client_phone')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4 items-end">
                @if (!empty($useCrmReferenceSources) && $useCrmReferenceSources)
                    <x-ui.form-group label="Источник заказа" name="reference_source_id" class="!mb-0" tip="Необязательно. Ваш источник из раздела «Источники» (создаётся в SuperPart и передаётся в CRM).">
                        <x-ui.select id="reference_source_id" name="reference_source_id" :error="$errors->has('reference_source_id')">
                            <option value="">Выберите источник</option>
                            @foreach ($referenceSources ?? [] as $rs)
                                <option value="{{ $rs->id }}" @selected(old('reference_source_id') == $rs->id)>
                                    {{ $rs->name }}@if (!empty($rs->city_name)) ({{ $rs->city_name }})@endif
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-group>
                @else
                    <x-ui.form-group label="Источник заказа" name="source_id" class="!mb-0" tip="Необязательно. Ваш источник заявок из справочника.">
                        <x-ui.select id="source_id" name="source_id" :error="$errors->has('source_id')">
                            <option value="">Выберите источник</option>
                            @foreach ($sources as $sourceId => $sourceName)
                                <option value="{{ $sourceId }}" @selected(old('source_id') == $sourceId)>{{ $sourceName }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-group>
                @endif

                <x-ui.form-group label="Без звонка" name="without_call" required class="!mb-0">
                    <x-ui.select id="without_call" name="without_call">
                        <option value="0" @selected(old('without_call', '0') === '0')>Нет</option>
                        <option value="1" @selected(old('without_call') === '1')>Да</option>
                    </x-ui.select>
                </x-ui.form-group>

                <x-ui.form-group label="Вид работ" name="equipment_type" required class="!mb-0" tip="Сначала выберите сервер (КП, БТ или МНЧ). Список видов работ зависит от сервера — один и тот же тип техники может быть доступен на разных серверах.">
                    <x-ui.select
                        id="equipment_type"
                        name="equipment_type"
                        :disabled="! $selectedServerType"
                        :error="$errors->has('equipment_type')"
                    >
                        <option value="">
                            {{ $selectedServerType ? 'Выберите вид работ' : 'Сначала выберите сервер' }}
                        </option>
                        @foreach ($equipmentCatalog as $eq)
                            @if (in_array($eq['code'], $allowedEquipmentCodes, true))
                                <option value="{{ $eq['code'] }}" @selected(old('equipment_type') === $eq['code'])>
                                    {{ $eq['label'] }}
                                </option>
                            @endif
                        @endforeach
                    </x-ui.select>
                </x-ui.form-group>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4 items-end">
                <x-ui.form-group label="Дата визита по заявке" name="order_date" required class="!mb-0" tip="Введите дату вручную (дд.мм.гггг) или нажмите значок календаря. Прошлые даты недоступны." id="order_date_trigger">
                    <x-ui.datetime-field
                        type="date"
                        name="order_date"
                        id="order_date"
                        :value="$defaultOrderDate"
                        :min-date="$minOrderDateIso"
                        :error="$errors->has('order_date')"
                    />
                    <p id="order_date_hint" class="mt-1 hidden text-xs leading-snug text-muted-foreground" role="note">
                        Минимальная дата — сегодня (<span class="tabular-nums">{{ now()->format('d.m.Y') }}</span>).
                    </p>
                    <p data-client-error="order_date" class="mt-1 text-xs text-destructive hidden"></p>
                </x-ui.form-group>

                <x-ui.form-group label="Время визита" name="meeting_time" required class="!mb-0" tip="Указывается по местному времени города клиента. Шаг 5 минут." id="meeting_time_trigger">
                    <x-ui.datetime-field
                        type="time"
                        name="meeting_time"
                        id="meeting_time"
                        :value="$defaultMeetingTime"
                        linked-date="#order_date_picker"
                        :error="$errors->has('meeting_time')"
                    />
                    <p id="meeting_time_hint" class="mt-1 hidden text-xs leading-snug text-muted-foreground" role="note">
                        Шаг 5 минут. Для сегодня минимум:
                        <span id="meeting_time_min_label" class="tabular-nums font-medium text-foreground">—</span>.
                    </p>
                    <p data-client-error="meeting_time" class="mt-1 text-xs text-destructive hidden"></p>
                </x-ui.form-group>

                <div class="flex min-h-[4.25rem] min-w-0 flex-col justify-end !mb-0">
                    <x-ui.label>Сейчас в городе клиента</x-ui.label>
                    <p id="orderLocalTime" class="mt-1 text-sm text-muted-foreground tabular-nums">—</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mb-4 items-start">
                <x-ui.form-group label="Имя клиента" name="client_name" class="!mb-0" tip="Необязательно. Если пусто — в заявке будет «Клиент».">
                    <x-ui.input
                        type="text"
                        id="client_name"
                        name="client_name"
                        value="{{ old('client_name') }}"
                        placeholder="Иван"
                        maxlength="20"
                        pattern="[А-Яа-яЁёA-Za-z\s\-]+"
                        title="Только буквы, пробел и дефис"
                        :error="$errors->has('client_name')"
                        autocomplete="name"
                    />
                </x-ui.form-group>

                <div></div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4 items-start">
                <x-ui.form-group label="Улица" name="street" required class="!mb-0" tip="Сначала выберите город. Достаточно нескольких букв — подскажем ул. Ленина, пр. Ленина и другие варианты.">
                    <div class="relative">
                        <input
                            type="text"
                            id="street"
                            name="street"
                            value="{{ old('street') }}"
                            placeholder="Начните вводить улицу…"
                            maxlength="30"
                            autocomplete="off"
                            role="combobox"
                            aria-expanded="false"
                            aria-controls="street_suggestions"
                            data-address-safe
                            class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground outline-none placeholder-muted-foreground/70 transition-colors focus:border-border focus:ring-0 focus-visible:border-muted-foreground/50 focus-visible:ring-0 @error('street') border-destructive @enderror"
                        />
                        <ul
                            id="street_suggestions"
                            role="listbox"
                            class="absolute z-50 mt-1 hidden max-h-52 w-full overflow-auto rounded-md border border-border bg-card py-1 shadow-lg"
                        ></ul>
                    </div>
                </x-ui.form-group>

                <x-ui.form-group label="Дом" name="house" required class="!mb-0" tip="Номер дома, корпус — цифрами и буквами, например: 10 или 10А.">
                    <x-ui.input
                        type="text"
                        id="house"
                        name="house"
                        value="{{ old('house') }}"
                        placeholder="10"
                        maxlength="30"
                        data-address-safe
                        :error="$errors->has('house')"
                    />
                </x-ui.form-group>

                <x-ui.form-group label="Кв/офис" name="flat" required class="!mb-0" tip="Обязательно: 15, частный дом, встречу у подъезда, домофон 1234#5678.">
                    <x-ui.input
                        type="text"
                        id="flat"
                        name="flat"
                        value="{{ old('flat') }}"
                        placeholder="15"
                        maxlength="10"
                        data-address-safe
                        :error="$errors->has('flat')"
                    />
                </x-ui.form-group>
            </div>

            <x-ui.form-group label="Описание заказа" name="order_adds" required tip="Опишите проблему клиента: что сломалось, симптомы, пожелания по визиту.">
                <x-ui.textarea
                    id="order_adds"
                    name="order_adds"
                    rows="5"
                    placeholder="Опишите проблему клиента"
                    :error="$errors->has('order_adds')"
                >{{ old('order_adds') }}</x-ui.textarea>
            </x-ui.form-group>

            <div class="mb-4">
                <x-ui.checkbox
                    id="review_required"
                    name="review_required"
                    value="1"
                    :checked="(bool) old('review_required')"
                    label="Отзыв"
                />
            </div>

            <input type="hidden" name="order_type" value="{{ $oldOrderType }}">
        </x-ui.card>

        <div class="mt-6 flex flex-wrap justify-between gap-3 pt-4 border-t border-border">
            <x-ui.button type="submit" variant="primary" size="lg">
                Отправить на проверку
            </x-ui.button>

            <x-ui.button tag="a" :href="route('orders.index')" variant="secondary" size="lg">
                Отменить
            </x-ui.button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/datetime-picker.js') }}?v=20260609"></script>
    <script>
        (function () {
            const cityOptions = @json($cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values());
            const cityIdInput = document.getElementById('city_id');
            const citySearch = document.getElementById('city_search');
            const citySuggestions = document.getElementById('city_suggestions');
            const serverSelect = document.getElementById('server_type');
            const equipmentSelect = document.getElementById('equipment_type');
            const equipmentByServer = @json($equipmentByServer ?? []);
            const equipmentCatalog = @json($equipmentCatalog ?? []);
            const orderDateHidden = document.getElementById('order_date');
            const datePickerRoot = document.getElementById('order_date_picker');
            const timePickerRoot = document.getElementById('meeting_time_picker');
            const meetingTimeHidden = document.getElementById('meeting_time');
            const orderDateHint = document.getElementById('order_date_hint');
            const meetingTimeHint = document.getElementById('meeting_time_hint');
            const meetingTimeMinLabel = document.getElementById('meeting_time_min_label');
            const orderLocalTimeEl = document.getElementById('orderLocalTime');
            const cityTimeUrl = @json(url('/orders/city'));
            const orderForm = document.getElementById('order-create-form');
            const streetInput = document.getElementById('street');
            const streetSuggestions = document.getElementById('street_suggestions');
            let streetFetchTimer = null;
            let streetFetchAbort = null;
            const streetCache = new Map();
            let cityTimeContext = null;

            function toComparableDateTime(dateDmy, timeHm) {
                const dp = String(dateDmy || '').split('.').map(Number);
                const tp = String(timeHm || '').split(':').map(Number);

                if (dp.length !== 3 || tp.length !== 2) {
                    return null;
                }

                if ([dp[0], dp[1], dp[2], tp[0], tp[1]].some(Number.isNaN)) {
                    return null;
                }

                return dp[2] * 100000000 + dp[1] * 1000000 + dp[0] * 10000 + tp[0] * 100 + tp[1];
            }

            function applyCityPickerConstraints() {
                if (datePickerRoot) {
                    if (cityTimeContext?.date_iso) {
                        datePickerRoot.dataset.minDate = cityTimeContext.date_iso;
                    } else {
                        delete datePickerRoot.dataset.minDate;
                        datePickerRoot.dataset.minDate = @json($minOrderDateIso);
                    }
                }

                if (timePickerRoot) {
                    if (cityTimeContext?.date) {
                        timePickerRoot.dataset.cityToday = cityTimeContext.date;
                        timePickerRoot.dataset.cityMinTime = cityTimeContext.time || '';
                    } else {
                        delete timePickerRoot.dataset.cityToday;
                        delete timePickerRoot.dataset.cityMinTime;
                    }
                }
            }

            function pickerApi() {
                return window.superpartDateTimePicker;
            }

            function filterEquipmentByServer() {
                if (!equipmentSelect) return;

                const server = serverSelect?.value || '';
                const allowed = server ? (equipmentByServer[server] || []) : [];
                const previous = equipmentSelect.value;

                equipmentSelect.disabled = !server;
                equipmentSelect.innerHTML = '';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = server ? 'Выберите вид работ' : 'Сначала выберите сервер';
                equipmentSelect.appendChild(placeholder);

                if (!server) {
                    equipmentSelect.value = '';
                    return;
                }

                equipmentCatalog.forEach((item) => {
                    if (!item.code || !allowed.includes(item.code)) {
                        return;
                    }

                    const el = document.createElement('option');
                    el.value = item.code;
                    el.textContent = item.label;
                    if (item.code === previous) {
                        el.selected = true;
                    }
                    equipmentSelect.appendChild(el);
                });

                if (previous && !allowed.includes(previous)) {
                    equipmentSelect.value = '';
                }
            }

            function resolveCityByName(name) {
                const normalized = String(name || '').trim().toLowerCase();
                if (normalized === '') {
                    return null;
                }

                return cityOptions.find((city) => city.name.toLowerCase() === normalized) || null;
            }

            function syncCityIdFromSearch() {
                if (!cityIdInput || !citySearch) {
                    return;
                }

                const match = resolveCityByName(citySearch.value);
                cityIdInput.value = match ? String(match.id) : '';
            }

            function hideCitySuggestions() {
                citySuggestions?.classList.add('hidden');
                citySearch?.setAttribute('aria-expanded', 'false');
            }

            function showCitySuggestions() {
                if (!citySuggestions || !citySearch) {
                    return;
                }

                const query = citySearch.value.trim().toLowerCase();
                const matches = cityOptions
                    .filter((city) => query === '' || city.name.toLowerCase().includes(query))
                    .slice(0, 12);

                citySuggestions.replaceChildren();

                if (matches.length === 0) {
                    hideCitySuggestions();
                    return;
                }

                matches.forEach((city) => {
                    const item = document.createElement('li');
                    item.role = 'option';
                    item.dataset.id = String(city.id);
                    item.dataset.name = city.name;
                    item.className = 'cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-muted/60';
                    item.textContent = city.name;
                    citySuggestions.appendChild(item);
                });

                citySuggestions.classList.remove('hidden');
                citySearch.setAttribute('aria-expanded', 'true');
            }

            function pickCity(id, name) {
                if (!cityIdInput || !citySearch) {
                    return;
                }

                cityIdInput.value = String(id);
                citySearch.value = name;
                hideCitySuggestions();
                hideStreetSuggestions();
                streetCache.clear();
                updateStreetInputState();
                updateOrderTime();
            }

            function updateStreetInputState() {
                if (!streetInput) {
                    return;
                }

                const hasCity = Boolean(cityIdInput?.value);
                streetInput.disabled = !hasCity;
                streetInput.placeholder = hasCity ? 'Начните вводить улицу…' : 'Сначала выберите город';
            }

            function hideStreetSuggestions() {
                streetSuggestions?.classList.add('hidden');
                streetInput?.setAttribute('aria-expanded', 'false');
            }

            function showStreetSuggestionsLoading() {
                if (!streetSuggestions || !streetInput) {
                    return;
                }

                streetSuggestions.replaceChildren();
                const item = document.createElement('li');
                item.className = 'px-3 py-2 text-sm text-muted-foreground';
                item.textContent = 'Поиск…';
                streetSuggestions.appendChild(item);
                streetSuggestions.classList.remove('hidden');
                streetInput.setAttribute('aria-expanded', 'true');
            }

            function renderStreetSuggestions(streets) {
                if (!streetSuggestions || !streetInput) {
                    return;
                }

                streetSuggestions.replaceChildren();

                if (!streets.length) {
                    hideStreetSuggestions();
                    return;
                }

                streets.forEach((street) => {
                    const label = typeof street === 'string' ? street : (street.label || street.value || '');
                    const value = typeof street === 'string' ? street : (street.value || street.label || '');

                    if (!label) {
                        return;
                    }

                    const item = document.createElement('li');
                    item.role = 'option';
                    item.dataset.name = value;
                    item.className = 'cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-muted/60';
                    item.textContent = label;
                    streetSuggestions.appendChild(item);
                });

                if (!streetSuggestions.children.length) {
                    hideStreetSuggestions();
                    return;
                }

                streetSuggestions.classList.remove('hidden');
                streetInput.setAttribute('aria-expanded', 'true');
            }

            function streetCacheKey(cityId, query) {
                return String(cityId) + '|' + query.toLowerCase();
            }

            async function fetchStreetSuggestions() {
                if (!streetInput || !cityIdInput?.value) {
                    hideStreetSuggestions();
                    return;
                }

                const query = streetInput.value.trim();
                if (query.length < 2) {
                    hideStreetSuggestions();
                    return;
                }

                const cacheKey = streetCacheKey(cityIdInput.value, query);
                const cached = streetCache.get(cacheKey);
                if (cached) {
                    renderStreetSuggestions(cached);
                    return;
                }

                streetFetchAbort?.abort();
                streetFetchAbort = new AbortController();
                showStreetSuggestionsLoading();

                try {
                    const url = cityTimeUrl + '/' + encodeURIComponent(cityIdInput.value) + '/streets?q=' + encodeURIComponent(query);
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: streetFetchAbort.signal,
                    });
                    const data = await response.json();

                    if (data.success) {
                        const streets = Array.isArray(data.streets) ? data.streets : [];
                        streetCache.set(cacheKey, streets);
                        renderStreetSuggestions(streets);
                    } else {
                        hideStreetSuggestions();
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                        hideStreetSuggestions();
                    }
                }
            }

            function onStreetInput() {
                clearTimeout(streetFetchTimer);
                streetFetchTimer = window.setTimeout(fetchStreetSuggestions, 100);
            }

            function onStreetBlur() {
                window.setTimeout(() => {
                    hideStreetSuggestions();
                }, 150);
            }

            function pickStreet(name) {
                if (!streetInput) {
                    return;
                }

                streetInput.value = name;
                hideStreetSuggestions();
            }

            function onCitySearchInput() {
                syncCityIdFromSearch();
                showCitySuggestions();
                updateStreetInputState();

                if (!cityIdInput?.value) {
                    hideStreetSuggestions();
                }

                if (cityIdInput?.value) {
                    updateOrderTime();
                }
            }

            function onCitySearchBlur() {
                window.setTimeout(() => {
                    hideCitySuggestions();
                    syncCityIdFromSearch();
                }, 150);
            }

            function formatDateISO(date) {
                const d = String(date.getDate()).padStart(2, '0');
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const y = String(date.getFullYear()).padStart(4, '0');
                return y + '-' + m + '-' + d;
            }

            function formatTimeHHMM(date) {
                return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
            }

            function todayIsoDate() {
                return formatDateISO(new Date());
            }

            function updateMeetingTimeHintLabel() {
                if (!meetingTimeMinLabel) {
                    return;
                }

                const iso = pickerApi()?.getIsoDate(datePickerRoot);
                const cityIso = cityTimeContext?.date_iso;

                if (cityIso && iso !== cityIso) {
                    meetingTimeMinLabel.textContent = '00:00';
                    return;
                }

                if (!cityIso && iso !== todayIsoDate()) {
                    meetingTimeMinLabel.textContent = '00:00';
                    return;
                }

                if (cityTimeContext?.time) {
                    meetingTimeMinLabel.textContent = roundTimeUpToFiveMinutes(cityTimeContext.time);
                    return;
                }

                meetingTimeMinLabel.textContent = roundTimeUpToFiveMinutes(formatTimeHHMM(new Date()));
            }

            let clampingDateTime = false;

            function clampDateTimeFields() {
                if (clampingDateTime) {
                    return;
                }

                clampingDateTime = true;
                try {
                    applyCityPickerConstraints();
                    pickerApi()?.refresh(datePickerRoot);
                    pickerApi()?.refresh(timePickerRoot);
                    updateMeetingTimeHintLabel();
                } finally {
                    clampingDateTime = false;
                }
            }

            function showFieldHint(el) {
                el?.classList.remove('hidden');
            }

            function hideFieldHint(el) {
                el?.classList.add('hidden');
            }

            function parseOrderDateTime() {
                if (!orderDateHidden?.value || !meetingTimeHidden?.value) return null;

                const parts = orderDateHidden.value.split('.');
                if (parts.length !== 3) return null;

                const [day, month, year] = parts.map(Number);
                const [hours, minutes] = meetingTimeHidden.value.split(':').map(Number);

                if ([day, month, year, hours, minutes].some(Number.isNaN)) return null;

                return new Date(year, month - 1, day, hours, minutes, 0, 0);
            }

            function validateFutureDateTime() {
                if (!orderDateHidden?.value || !meetingTimeHidden?.value) {
                    return false;
                }

                const selected = toComparableDateTime(orderDateHidden.value, meetingTimeHidden.value);

                if (selected === null) {
                    return false;
                }

                if (cityTimeContext?.date && cityTimeContext?.time) {
                    const min = toComparableDateTime(
                        cityTimeContext.date,
                        roundTimeUpToFiveMinutes(cityTimeContext.time)
                    );

                    return min !== null && selected >= min;
                }

                const now = new Date();
                const min = toComparableDateTime(
                    formatDateForInput(now),
                    roundTimeUpToFiveMinutes(formatTimeHHMM(now))
                );

                return min !== null && selected >= min;
            }

            function formatDateForInput(date) {
                const d = String(date.getDate()).padStart(2, '0');
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const y = String(date.getFullYear()).padStart(4, '0');
                return d + '.' + m + '.' + y;
            }

            function roundTimeUpToFiveMinutes(value) {
                if (!value || !value.includes(':')) return value;

                let [hours, minutes] = value.split(':').map(Number);
                if (Number.isNaN(hours) || Number.isNaN(minutes)) return value;

                minutes = Math.ceil(minutes / 5) * 5;

                if (minutes >= 60) {
                    minutes = 0;
                    hours = (hours + 1) % 24;
                }

                return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            }

            function setLocalTimeDisplay(text) {
                if (!orderLocalTimeEl) return;
                orderLocalTimeEl.textContent = text === '' || text == null ? '—' : text;
            }

            function setLocalTimeWithCity(time, cityName) {
                if (!orderLocalTimeEl) return;
                if (!time || time === '') {
                    orderLocalTimeEl.textContent = '—';
                    return;
                }

                const name = cityName && String(cityName).trim() !== '' ? String(cityName).trim() : '';
                orderLocalTimeEl.textContent = name ? time + ' (' + name + ')' : time;
            }

            async function updateOrderTime() {
                if (!orderLocalTimeEl) return;

                const cityId = cityIdInput?.value;

                if (!cityId) {
                    cityTimeContext = null;
                    applyCityPickerConstraints();
                    setLocalTimeDisplay('');

                    if (datePickerRoot && timePickerRoot) {
                        const now = new Date();
                        pickerApi()?.setValue(datePickerRoot, formatDateForInput(now));

                        const t = new Date(now.getTime() + 3600000);
                        pickerApi()?.setValue(timePickerRoot, roundTimeUpToFiveMinutes(formatTimeHHMM(t)));
                        clampDateTimeFields();
                    }

                    return;
                }

                try {
                    const response = await fetch(cityTimeUrl + '/' + encodeURIComponent(cityId) + '/time', {
                        headers: { Accept: 'application/json' },
                    });

                    const data = await response.json();

                    if (data.success) {
                        cityTimeContext = {
                            date: data.date,
                            date_iso: data.date_iso,
                            time: data.time,
                            timezone: data.timezone,
                            city_name: data.city_name,
                        };

                        setLocalTimeWithCity(data.time, data.city_name);
                        applyCityPickerConstraints();

                        if (datePickerRoot && timePickerRoot && data.date && data.time) {
                            pickerApi()?.setValue(datePickerRoot, data.date);

                            const parts = String(data.time).split(':').map(Number);
                            let meetingHours = (parts[0] ?? 0) + 1;

                            if (meetingHours >= 24) {
                                meetingHours -= 24;
                            }

                            const m = parts[1] ?? 0;
                            const raw = String(meetingHours).padStart(2, '0') + ':' + String(m).padStart(2, '0');

                            pickerApi()?.setValue(timePickerRoot, roundTimeUpToFiveMinutes(raw));
                            clampDateTimeFields();
                        }
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            document.addEventListener('click', (event) => {
                if (!datePickerRoot?.contains(event.target)) {
                    hideFieldHint(orderDateHint);
                }
                if (!timePickerRoot?.contains(event.target)) {
                    hideFieldHint(meetingTimeHint);
                }
            });

            datePickerRoot?.querySelector('[data-datetime-toggle]')?.addEventListener('click', () => {
                showFieldHint(orderDateHint);
            });
            datePickerRoot?.addEventListener('datetime-field:change', clampDateTimeFields);

            timePickerRoot?.querySelector('[data-datetime-toggle]')?.addEventListener('click', () => {
                updateMeetingTimeHintLabel();
                showFieldHint(meetingTimeHint);
            });
            timePickerRoot?.addEventListener('datetime-field:change', clampDateTimeFields);

            citySearch?.addEventListener('input', onCitySearchInput);
            citySearch?.addEventListener('focus', showCitySuggestions);
            citySearch?.addEventListener('blur', onCitySearchBlur);
            citySuggestions?.addEventListener('mousedown', (event) => {
                const item = event.target.closest('[data-id][data-name]');
                if (!item) {
                    return;
                }

                event.preventDefault();
                pickCity(item.dataset.id, item.dataset.name);
            });

            streetInput?.addEventListener('input', onStreetInput);
            streetInput?.addEventListener('focus', () => {
                if (cityIdInput?.value) {
                    onStreetInput();
                }
            });
            streetInput?.addEventListener('blur', onStreetBlur);
            streetSuggestions?.addEventListener('mousedown', (event) => {
                const item = event.target.closest('[data-name]');
                if (!item) {
                    return;
                }

                event.preventDefault();
                pickStreet(item.dataset.name);
            });
            serverSelect?.addEventListener('change', filterEquipmentByServer);

            orderForm?.addEventListener('submit', function (event) {
                clampDateTimeFields();
                syncCityIdFromSearch();
                clearClientErrors();

                if (!serverSelect?.value) {
                    event.preventDefault();
                    showClientError('server_type', 'Сначала выберите сервер (КП, БТ или МНЧ).');
                    serverSelect?.focus();
                    return;
                }

                if (!cityIdInput?.value) {
                    event.preventDefault();
                    showClientError('city_id', 'Выберите город из подсказок.');
                    citySearch?.focus();
                    return;
                }

                if (!validateFutureDateTime()) {
                    event.preventDefault();
                    showClientError('order_date', 'Дата и время визита не могут быть в прошлом по местному времени города клиента.');
                    showClientError('meeting_time', 'Дата и время визита не могут быть в прошлом по местному времени города клиента.');
                }
            });

            function showClientError(fieldName, message) {
                const el = document.querySelector('[data-client-error="' + fieldName + '"]');
                if (!el) {
                    return;
                }

                el.textContent = message;
                el.classList.remove('hidden');
            }

            function clearClientErrors() {
                document.querySelectorAll('[data-client-error]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.add('hidden');
                });
            }

            function bootOrderForm() {
                if (!window.superpartDateTimePicker) {
                    window.setTimeout(bootOrderForm, 0);
                    return;
                }

                filterEquipmentByServer();
                syncCityIdFromSearch();
                updateStreetInputState();
                clampDateTimeFields();
                updateOrderTime();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootOrderForm);
            } else {
                bootOrderForm();
            }

            const submitBtn = document.querySelector('#order-create-form button[type="submit"]');
            submitBtn?.addEventListener('mousedown', clampDateTimeFields);
        })();

        document.getElementById('order-create-form')?.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            const btn = this.querySelector('button[type="submit"]');

            if (!btn || btn.disabled) {
                return;
            }

            btn.disabled = true;
            btn.dataset._orig = btn.textContent;
            btn.textContent = 'Сохранение…';
        });
    </script>
@endpush

