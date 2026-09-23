@extends('layouts.app')

@section('title', 'Новое обращение — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Обратная связь', 'url' => route('feedback.index')],
        ['label' => 'Новое обращение', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-foreground">Новое обращение</h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Выберите тему, опишите вопрос и приложите файлы при необходимости.
            </p>
        </div>

        <x-ui.card>
            <form method="POST"
                  action="{{ route('feedback.store') }}"
                  enctype="multipart/form-data"
                  class="space-y-5">

                @csrf

                <x-ui.form-group label="Тема" name="subject" required>
                    <x-ui.select name="subject"
                                 id="subject"
                                 :error="$errors->has('subject')">

                        <option value="">Выберите тему</option>

                        <option value="ЛК" @selected(old('subject', $prefillSubject ?? '') === 'ЛК')>
                            ЛК
                        </option>

                        <option value="КЦ" @selected(old('subject', $prefillSubject ?? '') === 'КЦ')>
                            КЦ
                        </option>

                        <option value="Выплаты" @selected(old('subject', $prefillSubject ?? '') === 'Выплаты')>
                            Выплаты
                        </option>

                        <option value="Другое" @selected(old('subject', $prefillSubject ?? '') === 'Другое')>
                            Другое
                        </option>
                    </x-ui.select>
                </x-ui.form-group>

                <x-ui.form-group label="Сообщение" name="message" required>
                    <x-ui.textarea
                        name="message"
                        id="message"
                        rows="5"
                        maxlength="1000"
                        placeholder="Опишите ситуацию подробно"
                        :error="$errors->has('message')"
                    >{{ old('message', $prefillMessage ?? '') }}</x-ui.textarea>

                    <div class="mt-1 flex justify-between gap-3 text-xs text-muted-foreground">
                        <span>Максимум 1000 символов</span>
                        <span>
                            <span id="message-counter">0</span>/1000
                        </span>
                    </div>
                </x-ui.form-group>

                <x-ui.form-group label="Файлы" name="attachments">

                    <input
                        type="file"
                        id="attachments"
                        multiple
                        accept=".png,.jpg,.jpeg,.gif,.doc,.txt,.docx,.xls,.xlsx,.pdf"
                        class="hidden"
                    >

                    <input
                        type="file"
                        name="attachments[]"
                        id="attachments-real"
                        multiple
                        accept=".png,.jpg,.jpeg,.gif,.doc,.txt,.docx,.xls,.xlsx,.pdf"
                        class="hidden"
                    >

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            id="attachments-trigger">

                            Выбрать файлы
                        </x-ui.button>

                        <span id="attachments-label"
                              class="text-sm text-muted-foreground">

                            Файлы не выбраны
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Можно выбрать до 10 файлов.
                        Форматы: png, jpg, jpeg, gif, doc, txt, docx, xls, xlsx, pdf.
                    </p>

                    <div id="attachments-preview"
                         class="mt-4 grid gap-2 sm:grid-cols-2"></div>

                    @error('attachments')
                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                    @enderror

                    @error('attachments.*')
                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </x-ui.form-group>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" variant="primary">
                        Отправить
                    </x-ui.button>

                    <x-ui.button tag="a"
                                 :href="route('feedback.index')"
                                 variant="secondary">

                        Назад
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection

@push('scripts')
<script>
(function () {

    /*
    |--------------------------------------------------------------------------
    | Counter
    |--------------------------------------------------------------------------
    */

    const message = document.getElementById('message');
    const counter = document.getElementById('message-counter');

    function syncCounter() {
        if (!message || !counter) return;
        counter.textContent = String(message.value.length);
    }

    message?.addEventListener('input', syncCounter);
    syncCounter();

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    const trigger = document.getElementById('attachments-trigger');
    const input = document.getElementById('attachments');
    const realInput = document.getElementById('attachments-real');
    const preview = document.getElementById('attachments-preview');
    const label = document.getElementById('attachments-label');

    let storedFiles = [];

    trigger?.addEventListener('click', function () {
        input.click();
    });

    input?.addEventListener('change', function () {

        const newFiles = Array.from(input.files || []);

        newFiles.forEach(function (file) {

            const exists = storedFiles.some(function (f) {
                return (
                    f.name === file.name &&
                    f.size === file.size &&
                    f.lastModified === file.lastModified
                );
            });

            if (!exists && storedFiles.length < 10) {
                storedFiles.push(file);
            }
        });

        syncFiles();
        input.value = '';
    });

    function syncFiles() {

        const dt = new DataTransfer();

        storedFiles.forEach(function (file) {
            dt.items.add(file);
        });

        realInput.files = dt.files;

        renderPreview();
    }

    function renderPreview() {

        preview.innerHTML = '';

        if (storedFiles.length === 0) {
            label.textContent = 'Файлы не выбраны';
            return;
        }

        label.textContent = 'Выбрано файлов: ' + storedFiles.length;

        storedFiles.forEach(function (file, index) {

            const item = document.createElement('div');

            item.className =
                'rounded-lg border border-border bg-muted/30 p-3';

            const top = document.createElement('div');

            top.className =
                'flex items-start justify-between gap-3';

            const left = document.createElement('div');

            left.className = 'min-w-0 flex-1';

            const name = document.createElement('div');

            name.className =
                'text-sm font-medium text-foreground break-all';

            name.textContent = file.name;

            const meta = document.createElement('div');

            meta.className =
                'mt-1 text-xs text-muted-foreground';

            meta.textContent =
                Math.ceil(file.size / 1024) + ' КБ';

            left.appendChild(name);
            left.appendChild(meta);

            const remove = document.createElement('button');

            remove.type = 'button';

            remove.className =
                'shrink-0 rounded-md border border-border px-2 py-1 text-xs hover:bg-muted';

            remove.textContent = 'Удалить';

            remove.addEventListener('click', function () {
                storedFiles.splice(index, 1);
                syncFiles();
            });

            top.appendChild(left);
            top.appendChild(remove);

            item.appendChild(top);

            preview.appendChild(item);
        });

        if (storedFiles.length >= 10) {

            const warn = document.createElement('div');

            warn.className =
                'rounded-md border border-yellow-500/40 bg-yellow-500/10 px-3 py-2 text-xs text-yellow-700 sm:col-span-2';

            warn.textContent =
                'Достигнут лимит: максимум 10 файлов.';

            preview.appendChild(warn);
        }
    }
})();
</script>
@endpush