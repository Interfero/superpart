@extends('layouts.app')

@section('title', 'Источники партнёров — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Управление', 'url' => null],
        ['label' => 'Источники партнёров', 'url' => null],
    ]" />
@endsection

@section('content')
    <x-page-header title="Источники партнёров (партнёр → источники)" />

    @if (session('success'))
        <x-ui.alert type="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('warning'))
        <x-ui.alert type="warning" class="mb-4">{{ session('warning') }}</x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="error" class="mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div id="ref-sources-flash" class="mb-4 hidden"></div>

    @if (!($crmConfigured ?? false))
        <p class="mb-4 text-sm text-muted-foreground">
            Интеграция с CRM не настроена. Назначение источников будет только локальным.
        </p>
    @else
        <p class="mb-4 text-sm text-muted-foreground">
            Выберите партнёра и отметьте его источники — сохранение автоматическое.
            ID в каталоге ниже совпадают с CRM (для выплат). Общие источники видят все партнёры без явного назначения.
        </p>
    @endif

    {{-- Партнёр → источники --}}
    <x-ui.card padding="none" class="overflow-hidden mb-8">
        <div class="border-b border-border px-4 py-3">
            <h2 class="text-base font-semibold text-foreground">Партнёры</h2>
            <p class="text-xs text-muted-foreground mt-0.5">Приоритет: сначала партнёр, затем список источников.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/30 text-left text-muted-foreground">
                        <th class="px-4 py-3 font-normal w-[280px]">Партнёр</th>
                        <th class="px-4 py-3 font-normal">Источники</th>
                        <th class="whitespace-nowrap px-4 py-3 font-normal w-[100px]">Статус</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        @php
                            $selected = $sourcesByPartner[$partner->id] ?? [];
                        @endphp
                        <tr class="border-b border-border/80 align-top" data-partner-row="{{ $partner->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">{{ $partner->name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $partner->email }}</div>
                            </td>
                            <td class="px-4 py-3 min-w-[320px]">
                                <form
                                    method="post"
                                    action="{{ route('management.reference-sources.partner-sources', $partner) }}"
                                    data-partner-sources-form
                                    data-partner-id="{{ $partner->id }}"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <x-multiselect-checkboxes
                                        class="!mb-0"
                                        name="source_ids"
                                        label=""
                                        :options="$sourceOptions"
                                        :selected="$selected"
                                        placeholder="Выберите источники"
                                        :required="false"
                                    />
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <span data-save-status class="text-xs text-muted-foreground">—</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Нет партнёров</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    {{-- Каталог с ID CRM --}}
    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-border px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-foreground">Каталог источников (ID CRM)</h2>
                <p class="text-xs text-muted-foreground mt-0.5">Идентификаторы здесь = ID в CRM. «Общий» — автосохранение.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/30 text-left text-muted-foreground">
                        <th class="whitespace-nowrap px-4 py-3 font-normal">ID CRM</th>
                        <th class="px-4 py-3 font-normal">Название</th>
                        <th class="px-4 py-3 font-normal">Город</th>
                        <th class="px-4 py-3 font-normal">Общий</th>
                        <th class="whitespace-nowrap px-4 py-3 font-normal">Удалить</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allSources as $src)
                        <tr class="border-b border-border/80 hover:bg-muted/20">
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums font-medium text-foreground">
                                #{{ $src->crmId() }}
                            </td>
                            <td class="px-4 py-2.5 text-foreground">{{ $src->name }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground">{{ $src->city_name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @if ($crmConfigured ?? false)
                                    <form
                                        method="post"
                                        action="{{ route('management.reference-sources.update', $src) }}"
                                        data-shared-form
                                        class="inline"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                                            <input
                                                type="checkbox"
                                                name="shared_with_all"
                                                value="1"
                                                class="rounded border-border"
                                                data-shared-auto
                                                @checked($src->shared_with_all_partners)
                                            />
                                            <span>Общий</span>
                                        </label>
                                    </form>
                                @else
                                    {{ $src->shared_with_all_partners ? 'Да' : 'Нет' }}
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <form
                                    method="post"
                                    action="{{ route('management.reference-sources.destroy', $src) }}"
                                    onsubmit="return confirm('Удалить источник «{{ $src->name }}» (#{{ $src->crmId() }})?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="destructive" size="sm">Удалить</x-ui.button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">Нет источников в каталоге</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection

@push('scripts')
<script>
(function () {
    function flash(msg, type) {
        var el = document.getElementById('ref-sources-flash');
        if (!el) return;
        el.className = 'mb-4 rounded-lg border px-4 py-3 text-sm ' + (type === 'error'
            ? 'border-destructive/40 bg-destructive/10 text-destructive'
            : (type === 'warning'
                ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200'
                : 'border-green-500/40 bg-green-500/10 text-green-800 dark:text-green-200'));
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function savePartnerForm(form) {
        var status = form.closest('tr')?.querySelector('[data-save-status]');
        if (status) status.textContent = 'Сохранение…';

        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf()
            },
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error((data && data.message) || 'Ошибка сохранения');
                return data;
            });
        }).then(function (data) {
            if (status) status.textContent = 'Сохранено';
            if (data.warning) flash(data.warning, 'warning');
            else flash(data.message || 'Сохранено', 'ok');
            setTimeout(function () { if (status) status.textContent = '—'; }, 2500);
        }).catch(function (err) {
            if (status) status.textContent = 'Ошибка';
            flash(err.message || 'Не удалось сохранить', 'error');
        });
    }

    document.querySelectorAll('[data-partner-sources-form]').forEach(function (form) {
        var timer = null;
        form.addEventListener('change', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { savePartnerForm(form); }, 350);
        });
    });

    document.querySelectorAll('[data-shared-form]').forEach(function (form) {
        var cb = form.querySelector('[data-shared-auto]');
        if (!cb) return;
        cb.addEventListener('change', function () {
            var fd = new FormData(form);
            if (!cb.checked) {
                fd.delete('shared_with_all');
            }
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf()
                },
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  flash(data.warning || data.message || 'Общий доступ обновлён', data.warning ? 'warning' : 'ok');
              })
              .catch(function () { flash('Не удалось обновить «Общий»', 'error'); });
        });
    });
})();
</script>
@endpush
