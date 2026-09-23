@extends('layouts.app')

@section('title', 'Настройки — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Настройки', 'url' => null],
    ]" />
@endsection

@php
    $formatCard = static function (string $digits): string {
        $digits = preg_replace('/\D/', '', $digits) ?? '';
        return trim(chunk_split($digits, 4, ' '));
    };
@endphp

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="px-6 py-7">

        <h1 class="text-3xl font-bold text-foreground">
            Настройки
        </h1>

        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
            Управление профилем, API-доступами и персональными параметрами системы.
        </p>

    </div>

</div>

@if (session('success'))

    <x-ui.alert type="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>

@endif

<div class="max-w-7xl space-y-6">

    @php
        $isManager = $user->isManager();
    @endphp

    <div class="grid gap-4 md:grid-cols-3">

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Пользователь
            </div>

            <div class="mt-2 text-xl font-bold text-foreground">
                {{ $user->name }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Эл. почта
            </div>

            <div class="mt-2 text-lg font-semibold text-primary break-all">
                {{ $user->email }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Роль
            </div>

            <div class="mt-2 text-lg font-semibold text-foreground">
                {{ $user->roleLabel() }}
            </div>

        </x-ui.card>

    </div>

    <x-ui.card padding="lg" class="shadow-sm">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-foreground">Смена пароля</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Смена пароля входа в личный кабинет.
            </p>
        </div>

        <form method="POST" action="{{ route('settings.password') }}" class="space-y-6 max-w-xl">
            @csrf
            @method('PATCH')

            <x-ui.form-group label="Текущий пароль" name="current_password" required class="!mb-0">
                <x-ui.input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    :error="$errors->has('current_password')"
                />
            </x-ui.form-group>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.form-group label="Новый пароль" name="password" required class="!mb-0">
                    <x-ui.input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        :error="$errors->has('password')"
                    />
                </x-ui.form-group>

                <x-ui.form-group label="Повтор пароля" name="password_confirmation" required class="!mb-0">
                    <x-ui.input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        :error="$errors->has('password_confirmation')"
                    />
                </x-ui.form-group>
            </div>

            <x-ui.button type="submit" variant="primary" size="lg">
                Сменить пароль
            </x-ui.button>
        </form>
    </x-ui.card>

    @if (! $isManager)

        <x-ui.card padding="lg" class="shadow-sm">

            <div class="mb-6">

                <h2 class="text-xl font-semibold text-foreground">
                    Профиль
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Изменение имени и электронной почты.
                </p>

            </div>

            <form method="POST"
                  action="{{ route('settings.profile') }}"
                  class="space-y-6">

                @csrf
                @method('PATCH')

                <div class="grid gap-4 md:grid-cols-2">

                    <x-ui.form-group
                        label="Имя"
                        name="name"
                        required
                        class="!mb-0">

                        <x-ui.input
                            id="name"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            :error="$errors->has('name')"
                        />

                    </x-ui.form-group>

                    <x-ui.form-group
                        label="Эл. почта"
                        name="email"
                        required
                        class="!mb-0">

                        <x-ui.input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            :error="$errors->has('email')"
                        />

                    </x-ui.form-group>

                </div>

                <div class="flex flex-wrap gap-3">

                    <x-ui.button
                        type="submit"
                        variant="primary"
                        size="lg">

                        Сохранить

                    </x-ui.button>

                </div>

            </form>

        </x-ui.card>

        @can('access-employees-directory')
            <x-ui.card padding="md" class="mb-6 border-border/60">
                <h2 class="text-lg font-semibold text-foreground">Сотрудники</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Создание и редактирование учётных записей менеджеров — в справочнике сотрудников.
                </p>
                <div class="mt-4">
                    <x-ui.button tag="a" :href="route('management.users.index')" variant="primary" size="sm">
                        Список сотрудников
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endcan

    @endif

    @if ($user->hasElevatedAccess())

    <x-ui.card padding="lg" class="shadow-sm">

        <div class="mb-6">

            <h2 class="text-xl font-semibold text-foreground">
                Данные API
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Логин и пароль для подключения внешних сервисов.
            </p>

        </div>

        <form
            method="POST"
            action="{{ route('settings.api-credentials') }}"
            id="settings-api-form"
            class="space-y-6">

            @csrf
            @method('PATCH')

            <div class="grid gap-6 md:grid-cols-2">

                <div>

                    <x-ui.label for="api_login">
                        Логин
                    </x-ui.label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-border bg-input">

                        <x-ui.input
                            id="api_login"
                            name="api_login"
                            value="{{ old('api_login', $owner->api_login) }}"
                            class="!rounded-none !border-0 !ring-0 focus:!ring-0"
                            :error="$errors->has('api_login')"
                        />

                        <button
                            type="button"
                            id="settings-api-gen-login"
                            class="border-l border-border px-4 text-sm text-primary hover:bg-primary/10">

                            Сгенерировать

                        </button>

                    </div>

                </div>

                <div>

                    <x-ui.label for="api_password">
                        Пароль
                    </x-ui.label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-border bg-input">

                        <input
                            id="api_password"
                            name="api_password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="{{ $owner->api_password ? '** пароль задан **' : '' }}"
                            class="w-full min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-foreground outline-none"
                        />

                        <button
                            type="button"
                            id="settings-api-toggle-password"
                            class="border-l border-border px-3 text-sm text-muted-foreground hover:bg-muted/50"
                            title="Показать пароль">

                            👁

                        </button>

                        <button
                            type="button"
                            id="settings-api-gen-password"
                            class="border-l border-border px-4 text-sm text-primary hover:bg-primary/10">

                            Сгенерировать

                        </button>

                    </div>

                </div>

            </div>

            <div class="flex flex-wrap gap-3">

                <x-ui.button
                    type="submit"
                    variant="primary"
                    size="lg">

                    Сохранить

                </x-ui.button>

                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="lg"
                    id="settings-api-cancel">

                    Отменить

                </x-ui.button>

            </div>

        </form>

    </x-ui.card>

    @endif

    @if (! $isManager)

        <x-ui.card padding="lg" class="shadow-sm">

            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">

                <div>

                    <h2 class="text-xl font-semibold text-foreground">
                        Реквизиты для вывода
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Банковские карты и ИП-реквизиты для заявок на вывод средств. Для карты укажите дату рождения получателя — директор проверит её при инкассе.
                    </p>

                </div>

            </div>

            @if ($bankCards->isNotEmpty())

                <div class="mb-6 overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead>
                            <tr class="border-b border-border text-left text-muted-foreground">
                                <th class="pb-2 pr-4 font-medium">Тип</th>
                                <th class="pb-2 pr-4 font-medium">Реквизиты</th>
                                <th class="pb-2 pr-4 font-medium">Банк</th>
                                <th class="pb-2 pr-4 font-medium">Получатель</th>
                                <th class="pb-2 pr-4 font-medium">Дата рождения</th>
                                <th class="pb-2 font-medium">Действия</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($bankCards as $card)

                                <tr class="border-b border-border/60">

                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $card->isIp() ? 'bg-sky-500/20 text-sky-300' : 'bg-primary/15 text-primary' }}">
                                            {{ $card->typeLabel() }}
                                        </span>
                                    </td>

                                    <td class="py-3 pr-4 tabular-nums">
                                        @if ($card->isIp())
                                            р/с {{ $card->formattedAccountNumber() }}
                                            @if ($card->bik)
                                                <span class="text-muted-foreground">· БИК {{ $card->bik }}</span>
                                            @endif
                                        @else
                                            {{ $formatCard($card->card_number) }}
                                        @endif
                                    </td>

                                    <td class="py-3 pr-4">
                                        {{ $card->bank }}
                                    </td>

                                    <td class="py-3 pr-4">
                                        {{ $card->recipient }}
                                        @if ($card->isIp() && $card->inn)
                                            <div class="text-xs text-muted-foreground">ИНН {{ $card->inn }}</div>
                                        @endif
                                    </td>

                                    <td class="py-3 pr-4 tabular-nums">
                                        {{ $card->recipient_birth_date?->format('d.m.Y') ?: '—' }}
                                    </td>

                                    <td class="py-3">

                                        <div class="flex flex-wrap gap-2">

                                            <x-ui.button
                                                tag="a"
                                                :href="route('settings.index', ['edit_card' => $card->id]).'#payout-requisites-form'"
                                                variant="secondary"
                                                size="sm">

                                                Изменить

                                            </x-ui.button>

                                            <form
                                                method="POST"
                                                action="{{ route('settings.bank-cards.destroy', $card) }}"
                                                class="inline"
                                                onsubmit="return confirm('Удалить реквизиты?');">

                                                @csrf
                                                @method('DELETE')

                                                <x-ui.button type="submit" variant="destructive" size="sm">
                                                    Удалить
                                                </x-ui.button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @else

                <p class="mb-6 text-sm text-muted-foreground">
                    Реквизитов пока нет — добавьте карту или ИП для вывода.
                </p>

            @endif

            @php
                $editingType = old('type', $editingBankCard?->type ?? 'card');
                $isEditingIp = $editingType === 'ip';
            @endphp

            <form
                method="POST"
                action="{{ $editingBankCard ? route('settings.bank-cards.update', $editingBankCard) : route('settings.bank-cards.store') }}"
                id="payout-requisites-form"
                class="space-y-4 rounded-xl border border-border bg-muted/10 p-5">

                @csrf

                @if ($editingBankCard)
                    @method('PATCH')
                @endif

                <h3 class="text-sm font-semibold text-foreground">
                    {{ $editingBankCard ? 'Редактировать реквизиты' : 'Добавить реквизиты' }}
                </h3>

                <x-ui.form-group label="Тип" name="type" required class="!mb-0 max-w-xs">
                    <x-ui.select name="type" id="payout-requisite-type">
                        <option value="card" @selected($editingType === 'card')>Банковская карта</option>
                        <option value="ip" @selected($editingType === 'ip')>ИП (расчётный счёт)</option>
                    </x-ui.select>
                </x-ui.form-group>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.form-group label="Банк" name="bank" required class="!mb-0">
                        <x-ui.input
                            name="bank"
                            value="{{ old('bank', $editingBankCard?->bank) }}"
                            maxlength="100"
                            placeholder="Т-Банк / ПАО Сбербанк"
                            :error="$errors->has('bank')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="Получатель" name="recipient" required class="!mb-0">
                        <x-ui.input
                            name="recipient"
                            value="{{ old('recipient', $editingBankCard?->recipient) }}"
                            maxlength="150"
                            placeholder="ФИО или ИП Иванов И. И."
                            :error="$errors->has('recipient')"
                        />
                    </x-ui.form-group>
                </div>

                <div id="payout-fields-card" class="grid gap-4 md:grid-cols-2 {{ $isEditingIp ? 'hidden' : '' }}">

                    <x-ui.form-group label="Номер карты" name="card_number" required class="!mb-0">
                        <x-ui.input
                            name="card_number"
                            inputmode="numeric"
                            maxlength="16"
                            value="{{ old('card_number', $editingBankCard?->card_number) }}"
                            placeholder="16 цифр"
                            :error="$errors->has('card_number')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="Дата рождения получателя" name="recipient_birth_date" required class="!mb-0" tip="Нужна для проверки личности при инкассе.">
                        <x-ui.input
                            type="date"
                            name="recipient_birth_date"
                            value="{{ old('recipient_birth_date', $editingBankCard?->recipient_birth_date?->format('Y-m-d')) }}"
                            :error="$errors->has('recipient_birth_date')"
                        />
                    </x-ui.form-group>

                </div>

                <div id="payout-fields-ip" class="grid gap-4 md:grid-cols-2 {{ $isEditingIp ? '' : 'hidden' }}">

                    <x-ui.form-group label="ИНН" name="inn" required class="!mb-0">
                        <input
                            type="text"
                            name="inn"
                            inputmode="numeric"
                            maxlength="12"
                            value="{{ old('inn', $editingBankCard?->inn ?? $owner->inn) }}"
                            placeholder="10 или 12 цифр"
                            class="w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 {{ $errors->has('inn') ? 'border-destructive' : '' }}"
                        />
                        @error('inn')
                            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                        @enderror
                    </x-ui.form-group>

                    <x-ui.form-group label="БИК" name="bik" required class="!mb-0">
                        <input
                            type="text"
                            name="bik"
                            inputmode="numeric"
                            maxlength="9"
                            value="{{ old('bik', $editingBankCard?->bik) }}"
                            placeholder="9 цифр"
                            class="w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 {{ $errors->has('bik') ? 'border-destructive' : '' }}"
                        />
                        @error('bik')
                            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                        @enderror
                    </x-ui.form-group>

                    <x-ui.form-group label="Расчётный счёт" name="account_number" required class="!mb-0">
                        <input
                            type="text"
                            name="account_number"
                            inputmode="numeric"
                            maxlength="20"
                            value="{{ old('account_number', $editingBankCard?->account_number) }}"
                            placeholder="20 цифр"
                            class="w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 {{ $errors->has('account_number') ? 'border-destructive' : '' }}"
                        />
                        @error('account_number')
                            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                        @enderror
                    </x-ui.form-group>

                    <x-ui.form-group label="Корр. счёт" name="correspondent_account" class="!mb-0">
                        <input
                            type="text"
                            name="correspondent_account"
                            inputmode="numeric"
                            maxlength="20"
                            value="{{ old('correspondent_account', $editingBankCard?->correspondent_account) }}"
                            placeholder="20 цифр (необязательно)"
                            class="w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 {{ $errors->has('correspondent_account') ? 'border-destructive' : '' }}"
                        />
                        @error('correspondent_account')
                            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                        @enderror
                    </x-ui.form-group>

                </div>

                <div class="flex flex-wrap gap-3">

                    <x-ui.button type="submit" variant="primary">
                        {{ $editingBankCard ? 'Сохранить' : 'Добавить' }}
                    </x-ui.button>

                    @if ($editingBankCard)

                        <x-ui.button tag="a" :href="route('settings.index')" variant="secondary">
                            Отмена
                        </x-ui.button>

                    @endif

                </div>

            </form>

        </x-ui.card>

        <x-ui.card padding="lg" class="shadow-sm">

            <div class="mb-6">
                <h2 class="text-xl font-semibold text-foreground">
                    Данные ИП / ООО
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Юридические реквизиты партнёра для выплат и документооборота.
                </p>
            </div>

            <form method="POST" action="{{ route('settings.legal') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-ui.form-group label="Форма" name="legal_form" class="!mb-0">
                    <x-ui.select name="legal_form" id="legal_form">
                        <option value="" @selected(old('legal_form', $owner->legal_form) === null || old('legal_form', $owner->legal_form) === '')>Не указано</option>
                        <option value="ip" @selected(old('legal_form', $owner->legal_form) === 'ip')>ИП</option>
                        <option value="ooo" @selected(old('legal_form', $owner->legal_form) === 'ooo')>ООО</option>
                    </x-ui.select>
                </x-ui.form-group>

                <div id="legal-fields" class="grid gap-4 md:grid-cols-2 {{ in_array(old('legal_form', $owner->legal_form), ['ip', 'ooo'], true) ? '' : 'hidden' }}">
                    <x-ui.form-group label="Наименование" name="legal_name" class="!mb-0 md:col-span-2">
                        <x-ui.input
                            name="legal_name"
                            value="{{ old('legal_name', $owner->legal_name) }}"
                            maxlength="255"
                            placeholder="ИП Иванов Иван Иванович или ООО «Пример»"
                            :error="$errors->has('legal_name')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="ИНН" name="inn" class="!mb-0">
                        <x-ui.input
                            name="inn"
                            inputmode="numeric"
                            maxlength="12"
                            value="{{ old('inn', $owner->inn) }}"
                            :error="$errors->has('inn')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="ОГРН / ОГРНИП" name="ogrn" class="!mb-0">
                        <x-ui.input
                            name="ogrn"
                            inputmode="numeric"
                            maxlength="15"
                            value="{{ old('ogrn', $owner->ogrn) }}"
                            :error="$errors->has('ogrn')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="Юридический адрес" name="legal_address" class="!mb-0 md:col-span-2">
                        <x-ui.textarea name="legal_address" rows="3" maxlength="500" :error="$errors->has('legal_address')">{{ old('legal_address', $owner->legal_address) }}</x-ui.textarea>
                    </x-ui.form-group>
                </div>

                <x-ui.button type="submit" variant="primary" size="lg">
                    Сохранить
                </x-ui.button>
            </form>

        </x-ui.card>

        <x-ui.card padding="lg" class="shadow-sm" id="partner-phones">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-foreground">Телефоны источников</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Привязка телефонов к источникам заявок (для маршрутизации и справочника).
                </p>
            </div>

            @if ($partnerPhones->isNotEmpty())
                <form method="POST" action="{{ route('settings.phones.update') }}" class="space-y-4 mb-8">
                    @csrf
                    @method('PATCH')

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted-foreground">
                                    <th class="py-2 pr-4 font-medium">Телефон</th>
                                    <th class="py-2 font-medium">Источник</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($partnerPhones as $i => $phone)
                                    <tr class="border-b border-border/60">
                                        <td class="py-3 pr-4 text-foreground whitespace-nowrap">
                                            {{ $phone->phone }}
                                            <input type="hidden" name="phones[{{ $i }}][id]" value="{{ $phone->id }}">
                                        </td>
                                        <td class="py-3 min-w-[16rem]">
                                            @if ($useCrmReferenceSources)
                                                <select
                                                    name="phones[{{ $i }}][reference_source_id]"
                                                    class="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                                    required
                                                >
                                                    @foreach ($referenceSources as $src)
                                                        <option value="{{ $src['id'] }}" @selected((int) old("phones.$i.reference_source_id", $phone->reference_source_id) === (int) $src['id'])>
                                                            {{ $src['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <select
                                                    name="phones[{{ $i }}][source_id]"
                                                    class="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                                    required
                                                >
                                                    @foreach ($localSources as $src)
                                                        <option value="{{ $src->id }}" @selected((int) old("phones.$i.source_id", $phone->source_id) === (int) $src->id)>
                                                            {{ $src->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-ui.button type="submit" variant="primary" size="md">
                        Сохранить привязки
                    </x-ui.button>
                </form>
            @else
                <p class="mb-6 text-sm text-muted-foreground">Пока нет сохранённых телефонов.</p>
            @endif

            <div class="border-t border-border pt-6">
                <h3 class="text-base font-semibold text-foreground mb-3">Добавить телефон</h3>
                <form method="POST" action="{{ route('settings.phones.store') }}" class="grid gap-4 md:grid-cols-3 md:items-end">
                    @csrf
                    <x-ui.form-group label="Телефон" name="phone" required class="!mb-0">
                        <x-ui.input
                            id="partner_phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="+79091234567"
                            :error="$errors->has('phone')"
                        />
                    </x-ui.form-group>

                    <x-ui.form-group label="Источник" name="{{ $useCrmReferenceSources ? 'reference_source_id' : 'source_id' }}" required class="!mb-0">
                        @if ($useCrmReferenceSources)
                            <select
                                name="reference_source_id"
                                class="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                required
                            >
                                <option value="">—</option>
                                @foreach ($referenceSources as $src)
                                    <option value="{{ $src['id'] }}" @selected((string) old('reference_source_id') === (string) $src['id'])>
                                        {{ $src['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <select
                                name="source_id"
                                class="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                required
                            >
                                <option value="">—</option>
                                @foreach ($localSources as $src)
                                    <option value="{{ $src->id }}" @selected((string) old('source_id') === (string) $src->id)>
                                        {{ $src->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </x-ui.form-group>

                    <x-ui.button type="submit" variant="secondary" size="md">
                        Добавить
                    </x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card padding="lg" class="shadow-sm">

            <div class="mb-6">

                <h2 class="text-xl font-semibold text-foreground">
                    Тема оформления
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Переключение между светлой и тёмной темой интерфейса.
                </p>

            </div>

            <div id="settings-theme-switcher"
                 class="flex flex-wrap gap-3">

                <button
                    type="button"
                    data-theme-value="light"
                    class="inline-flex items-center justify-center rounded-xl border border-border px-5 py-2.5 text-sm font-medium transition-all hover:bg-primary/5">

                    Светлая тема

                </button>

                <button
                    type="button"
                    data-theme-value="dark"
                    class="inline-flex items-center justify-center rounded-xl border border-border px-5 py-2.5 text-sm font-medium transition-all hover:bg-primary/5">

                    Тёмная тема

                </button>

            </div>

        </x-ui.card>

    @endif

</div>

@endsection

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', () => {

    const legalForm = document.getElementById('legal_form');
    const legalFields = document.getElementById('legal-fields');

    const syncLegalFields = () => {
        if (!legalForm || !legalFields) return;
        const show = legalForm.value === 'ip' || legalForm.value === 'ooo';
        legalFields.classList.toggle('hidden', !show);
    };

    legalForm?.addEventListener('change', syncLegalFields);
    syncLegalFields();

    const payoutType = document.getElementById('payout-requisite-type');
    const payoutCard = document.getElementById('payout-fields-card');
    const payoutIp = document.getElementById('payout-fields-ip');

    const setSectionEnabled = (section, enabled) => {
        if (!section) return;
        section.classList.toggle('hidden', !enabled);
        section.querySelectorAll('input, select, textarea').forEach((el) => {
            el.disabled = !enabled;
        });
    };

    const syncPayoutType = () => {
        if (!payoutType) return;
        const isIp = payoutType.value === 'ip';
        setSectionEnabled(payoutCard, !isIp);
        setSectionEnabled(payoutIp, isIp);
    };

    payoutType?.addEventListener('change', syncPayoutType);
    syncPayoutType();

    const payoutForm = document.getElementById('payout-requisites-form');
    @if ($editingBankCard)
    payoutForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const titleEl = payoutForm?.querySelector('h3');
    if (titleEl) {
        titleEl.classList.add('text-primary');
    }
    @endif

    const randAlnum = (len) => {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let s = '';

        for (let i = 0; i < len; i++) {
            s += chars[Math.floor(Math.random() * chars.length)];
        }

        return s;
    };

    const loginBtn = document.getElementById('settings-api-gen-login');
    const loginInput = document.getElementById('api_login');

    if (loginBtn && loginInput) {

        loginBtn.addEventListener('click', () => {

            loginInput.value =
                randAlnum(5) + '.' +
                randAlnum(14) + '.' +
                randAlnum(8);

        });

    }

    const passBtn = document.getElementById('settings-api-gen-password');
    const passInput = document.getElementById('api_password');
    const passToggle = document.getElementById('settings-api-toggle-password');

    if (passBtn && passInput) {

        passBtn.addEventListener('click', () => {

            passInput.value =
                randAlnum(10) +
                randAlnum(10) +
                randAlnum(8);

            passInput.placeholder = '';
            passInput.type = 'text';

        });

    }

    if (passToggle && passInput) {

        passToggle.addEventListener('click', () => {

            const show = passInput.type === 'password';
            passInput.type = show ? 'text' : 'password';
            passToggle.textContent = show ? '🙈' : '👁';

        });

    }

    const apiForm = document.getElementById('settings-api-form');
    const cancelApi = document.getElementById('settings-api-cancel');

    if (apiForm && cancelApi && loginInput && passInput) {

        cancelApi.addEventListener('click', () => {

            const initial = loginInput.dataset.initialLogin || '';

            loginInput.value = initial;
            passInput.value = '';

            const hadPassword =
                passInput.dataset.initialPasswordEmpty === '0';

            passInput.placeholder =
                hadPassword ? '** пароль задан **' : '';

        });

    }

    const switcher =
        document.getElementById('settings-theme-switcher');

    if (!switcher) return;

    const buttons =
        switcher.querySelectorAll('[data-theme-value]');

    const base =
        'inline-flex items-center justify-center px-4 py-2 text-sm rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

    const inactive =
        base +
        ' border border-border bg-transparent text-foreground hover:bg-muted';

    const active =
        base +
        ' bg-secondary text-secondary-foreground ring-2 ring-primary border border-transparent shadow-sm';

    const paintButtons = () => {

        const current =
            document.documentElement.classList.contains('dark')
                ? 'dark'
                : 'light';

        buttons.forEach((button) => {

            const isActive =
                button.dataset.themeValue === current;

            button.className =
                isActive ? active : inactive;

        });

    };

    paintButtons();

    const persistTheme = async (theme) => {

        const token =
            document.querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

        if (!token) return;

        try {

            await window.axios.patch(
                '{{ route('settings.theme') }}',
                { theme },
                {
                    headers: {
                        'X-CSRF-TOKEN': token,
                    },
                }
            );

        } catch (_) {}

    };

    const setTheme = async (theme) => {

        document.documentElement.classList.toggle(
            'dark',
            theme === 'dark'
        );

        try {
            localStorage.setItem('theme', theme);
        } catch (_) {}

        await persistTheme(theme);

    };

    buttons.forEach((button) => {

        button.addEventListener('click', async () => {

            const nextTheme =
                button.dataset.themeValue;

            if (!nextTheme) return;

            if (typeof window.superpartSetTheme === 'function') {

                await window.superpartSetTheme(nextTheme);

            } else {

                await setTheme(nextTheme);

            }

            paintButtons();

        });

    });

});
</script>

@endpush