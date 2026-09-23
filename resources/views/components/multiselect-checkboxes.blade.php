@props([
    'name',
    'label',
    'options',
    'selected' => [],
    'placeholder' => 'Выберите…',
    'required' => false,
    'error' => false,
])

@php
    $selectedSet = collect(old($name, $selected))->map(fn ($v) => (string) $v)->all();
    $showErrorStyle = $error || $errors->has($name);
@endphp

<div {{ $attributes->class('mb-4 relative isolate') }} data-multiselect-root data-placeholder="{{ e($placeholder) }}">
    <label class="block text-sm font-medium text-foreground mb-1.5">
        {{ $label }}
        @if ($required)
            <span class="text-destructive">*</span>
        @endif
    </label>
    <div class="relative">
        <button
            type="button"
            class="relative z-10 flex w-full min-h-10 items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm transition-colors
                {{ $showErrorStyle ? 'border-destructive ring-1 ring-destructive' : 'border-border bg-input text-foreground hover:bg-muted/30' }}"
            data-ms-trigger
            aria-expanded="false"
            aria-haspopup="listbox"
        >
            <span data-ms-text class="truncate {{ count($selectedSet) ? 'text-foreground' : 'text-muted-foreground' }}">{{ $placeholder }}</span>
            <span class="shrink-0 text-muted-foreground text-xs">▼</span>
        </button>
        <div
            data-ms-dropdown
            class="hidden absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-auto rounded-md border border-border bg-card shadow-xl"
        >
            <div class="sticky top-0 z-[1] flex gap-2 border-b border-border bg-card px-2 py-1.5">
                <button type="button" class="text-xs text-primary hover:underline" data-ms-all>выбрать все</button>
                <button type="button" class="text-xs text-primary hover:underline" data-ms-none>снять все</button>
            </div>
            <div class="py-1 px-2 space-y-1">
                @foreach ($options as $opt)
                    @php
                        $oid = is_array($opt) ? ($opt['id'] ?? null) : data_get($opt, 'id');
                        $olabel = is_array($opt)
                            ? ($opt['label'] ?? $opt['name'] ?? '')
                            : (data_get($opt, 'name') ?? data_get($opt, 'label', ''));
                        $isChecked = in_array((string) $oid, $selectedSet, true);
                    @endphp
                    <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-sm hover:bg-muted/40">
                        <input
                            type="checkbox"
                            name="{{ $name }}[]"
                            value="{{ $oid }}"
                            class="rounded border-border"
                            data-ms-cb
                            @checked($isChecked)
                        />
                        <span class="text-foreground">{{ $olabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
    @error($name)
        <p class="text-sm text-destructive mt-1">{{ $message }}</p>
    @enderror
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function updateText(root) {
                    const textEl = root.querySelector('[data-ms-text]');
                    const cbs = root.querySelectorAll('[data-ms-cb]:checked');
                    const dropdown = root.querySelector('[data-ms-dropdown]');
                    const labels = [];
                    cbs.forEach(function (cb) {
                        const lab = cb.closest('label');
                        if (lab) {
                            const span = lab.querySelector('span:last-child');
                            if (span) labels.push(span.textContent.trim());
                        }
                    });
                    if (cbs.length === 0) {
                        textEl.textContent = root.getAttribute('data-placeholder') || 'Выберите…';
                        textEl.classList.add('text-muted-foreground');
                        textEl.classList.remove('text-foreground');
                    } else if (cbs.length === 1) {
                        textEl.textContent = labels[0] || '1';
                        textEl.classList.remove('text-muted-foreground');
                        textEl.classList.add('text-foreground');
                    } else {
                        textEl.textContent = 'Выбрано: ' + cbs.length;
                        textEl.classList.remove('text-muted-foreground');
                        textEl.classList.add('text-foreground');
                    }
                }
                var MS_OPEN_Z = 1000;

                function clearMultiselectStacking() {
                    document.querySelectorAll('[data-multiselect-root]').forEach(function (r) {
                        r.style.zIndex = '';
                    });
                }

                function closeAll() {
                    clearMultiselectStacking();
                    document.querySelectorAll('[data-ms-dropdown].open').forEach(function (el) {
                        el.classList.add('hidden');
                        el.classList.remove('open');
                        const tr = el.closest('[data-multiselect-root]')?.querySelector('[data-ms-trigger]');
                        if (tr) tr.setAttribute('aria-expanded', 'false');
                    });
                }
                document.addEventListener('click', function (e) {
                    const trigger = e.target.closest('[data-ms-trigger]');
                    if (trigger) {
                        e.preventDefault();
                        const root = trigger.closest('[data-multiselect-root]');
                        const dd = root.querySelector('[data-ms-dropdown]');
                        const open = dd.classList.contains('open');
                        closeAll();
                        if (!open) {
                            root.style.zIndex = String(MS_OPEN_Z);
                            dd.classList.remove('hidden');
                            dd.classList.add('open');
                            trigger.setAttribute('aria-expanded', 'true');
                        }
                        return;
                    }
                    if (!e.target.closest('[data-multiselect-root]')) closeAll();
                });
                document.querySelectorAll('[data-multiselect-root]').forEach(function (root) {
                    root.querySelectorAll('[data-ms-cb]').forEach(function (cb) {
                        cb.addEventListener('change', function () { updateText(root); });
                    });
                    const allBtn = root.querySelector('[data-ms-all]');
                    const noneBtn = root.querySelector('[data-ms-none]');
                    if (allBtn) {
                        allBtn.addEventListener('click', function (ev) {
                            ev.preventDefault();
                            root.querySelectorAll('[data-ms-cb]').forEach(function (c) { c.checked = true; });
                            updateText(root);
                        });
                    }
                    if (noneBtn) {
                        noneBtn.addEventListener('click', function (ev) {
                            ev.preventDefault();
                            root.querySelectorAll('[data-ms-cb]').forEach(function (c) { c.checked = false; });
                            updateText(root);
                        });
                    }
                    updateText(root);
                });
            })();
        </script>
    @endpush
@endonce
