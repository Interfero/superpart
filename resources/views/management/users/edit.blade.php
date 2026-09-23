@extends('layouts.app')

@section('title', 'Карточка сотрудника — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Справочники', 'url' => null],
        ['label' => 'Список сотрудников', 'url' => route('management.users.index')],
        ['label' => 'Редактирование', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
        <div class="px-6 py-7">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                <h1 class="text-3xl font-bold text-foreground">
                    {{ $user->displayFullName() }}
                </h1>

                <x-ui.button
                    tag="a"
                    :href="route('management.users.index')"
                    variant="secondary"
                    size="md"
                    class="shrink-0"
                >
                    Назад
                </x-ui.button>
            </div>

            <p class="mt-2 text-sm text-muted-foreground">
                Карточка сотрудника и управление доступами.
            </p>
        </div>
    </div>

    <div class="w-full space-y-6">
        @if (session('success'))
            <x-ui.alert type="success">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        @error('delete')
            <x-ui.alert type="error" class="border-destructive/40">
                {{ $message }}
            </x-ui.alert>
        @enderror

        @if (session('generated_password'))
            <x-ui.card padding="md" class="border-primary/40 bg-primary/5">
                <p class="text-sm font-medium text-foreground mb-2">
                    Сгенерированный пароль (скопируйте сейчас):
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <code id="edit-generated-password-value" class="rounded border border-border bg-muted/40 px-3 py-2 text-sm font-mono text-foreground select-all">{{ session('generated_password') }}</code>

                    <x-ui.button type="button" variant="outline" size="sm" id="edit-copy-generated-password">
                        Копировать
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card padding="lg" class="shadow-sm">
            <form id="management-user-edit-form" method="POST" action="{{ route('management.users.update', $user) }}" class="space-y-6" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 *:min-w-0">
                    <x-ui.form-group label="Фамилия" name="last_name" required class="!mb-0">
                        <x-ui.input id="last_name" name="last_name" value="{{ $nameDefaults['last_name'] }}" required maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('last_name')" />
                    </x-ui.form-group>

                    <x-ui.form-group label="Имя" name="first_name" required class="!mb-0">
                        <x-ui.input id="first_name" name="first_name" value="{{ $nameDefaults['first_name'] }}" required maxlength="20" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('first_name')" />
                    </x-ui.form-group>

                    <x-ui.form-group label="Отчество" name="middle_name" class="!mb-0">
                        <x-ui.input id="middle_name" name="middle_name" value="{{ $nameDefaults['middle_name'] }}" maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('middle_name')" />
                    </x-ui.form-group>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                    <x-ui.form-group label="Электронная почта" name="email" required class="!mb-0">
                        <x-ui.input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="50" autocomplete="off" data-lpignore="true" :error="$errors->has('email')" />
                    </x-ui.form-group>

                    <x-ui.form-group label="Новый пароль" name="new_password" class="!mb-0">
                        <div class="flex flex-wrap items-stretch gap-2 min-w-0">
                            <x-ui.input id="new_password" name="new_password" type="text" value="{{ old('new_password') }}" maxlength="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }}" autocomplete="off" data-lpignore="true" class="flex-1 min-w-0" :error="$errors->has('new_password')" placeholder="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }} символов или пусто" />

                            <x-ui.button type="button" variant="outline" size="md" class="shrink-0" data-gen-password="new_password">
                                Сгенерировать
                            </x-ui.button>
                        </div>

                        @error('new_password')
                            <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                        @enderror
                    </x-ui.form-group>
                </div>

                @if (auth()->user()->hasElevatedAccess())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:items-end *:min-w-0">
                        <x-ui.form-group label="Роль" name="role" required class="!mb-0">
                            <select
                                name="role"
                                id="role"
                                class="w-full min-h-10 rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground"
                                required
                                autocomplete="off"
                            >
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.form-group>
                    </div>

                    <div id="partner-extra" class="space-y-2 {{ old('role', $user->role) === \App\Models\User::ROLE_PARTNER ? '' : 'hidden' }}">
                        <x-multiselect-checkboxes
                            class="!mb-0"
                            name="source_ids"
                            label="Источники партнёра"
                            :options="$sourcesForMultiselect"
                            :selected="old('source_ids', (app(\App\Services\LevelionApiService::class)->isConfigured() ? $user->allowedReferenceSources : $user->allowedSources)->pluck('id')->all())"
                            placeholder="Необязательно — можно назначить позже"
                            :required="false"
                        />
                        <p class="text-xs text-muted-foreground">
                            Города назначаются автоматически (все города SuperPart). Источники можно не указывать при создании — назначьте позже здесь или в «Источники партнёров».
                        </p>
                    </div>
                @else
                    <x-ui.form-group label="Роль" name="role_display" class="!mb-0">
                        <div class="rounded-md border border-border bg-muted/20 px-3 py-2 text-sm text-foreground min-h-10 flex items-center">
                            Менеджер партнёра
                        </div>
                    </x-ui.form-group>
                @endif

                @if (auth()->user()->hasElevatedAccess())
                    <div id="manager-extra" class="space-y-4 {{ old('role', $user->role) === \App\Models\User::ROLE_MANAGER ? '' : 'hidden' }}" data-role-block="manager">
                        <x-ui.form-group label="Партнёр-владелец" name="parent_user_id" class="!mb-0">
                            <select name="parent_user_id" autocomplete="off" class="w-full min-h-10 rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground">
                                <option value="">—</option>

                                @foreach ($partnerParents as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('parent_user_id', $user->parent_user_id) === (string) $p->id)>
                                        {{ $p->name }} ({{ $p->email }})
                                    </option>
                                @endforeach
                            </select>

                            @error('parent_user_id')
                                <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                            @enderror
                        </x-ui.form-group>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                            <x-multiselect-checkboxes
                                class="!mb-0"
                                name="city_ids"
                                label="Города"
                                :options="$cities"
                                :selected="old('city_ids', $user->allowedCities->pluck('id')->all())"
                                placeholder="Необязательно"
                                :required="false"
                            />

                            <x-multiselect-checkboxes
                                class="!mb-0"
                                name="source_ids"
                                label="Источники"
                                :options="$sourcesForMultiselect"
                                :selected="old('source_ids', (app(\App\Services\LevelionApiService::class)->isConfigured() ? $user->allowedReferenceSources : $user->allowedSources)->pluck('id')->all())"
                                placeholder="Необязательно"
                                :required="false"
                            />
                        </div>

                        <x-direction-checkboxes
                            class="!mb-0"
                            :selected="old('direction_codes', $user->allowed_directions ?? [])"
                            :required="true"
                        />
                    </div>

                    <p id="non-manager-note" class="text-sm text-muted-foreground {{ in_array(old('role', $user->role), [\App\Models\User::ROLE_MANAGER, \App\Models\User::ROLE_PARTNER], true) ? 'hidden' : '' }}">
                        Для ролей «разработчик» и «генеральный директор» города и источники не задаются.
                    </p>
                @else
                    <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_MANAGER }}" />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                        <x-multiselect-checkboxes
                            class="!mb-0"
                            name="city_ids"
                            label="Города"
                            :options="$cities"
                            :selected="old('city_ids', $user->allowedCities->pluck('id')->all())"
                            placeholder="Необязательно"
                            :required="false"
                        />

                        <x-multiselect-checkboxes
                            class="!mb-0"
                            name="source_ids"
                            label="Источники"
                            :options="$sourcesForMultiselect"
                            :selected="old('source_ids', (app(\App\Services\LevelionApiService::class)->isConfigured() ? $user->allowedReferenceSources : $user->allowedSources)->pluck('id')->all())"
                            placeholder="Необязательно"
                            :required="false"
                        />
                    </div>

                    <x-direction-checkboxes
                        class="!mb-0"
                        :selected="old('direction_codes', $user->allowed_directions ?? [])"
                        :required="true"
                    />
                @endif

                <x-ui.form-group label="Комментарий" name="comment" class="!mb-0">
                    <x-ui.textarea name="comment" id="user_comment_edit" rows="4" :error="$errors->has('comment')" placeholder="Необязательно">{{ old('comment', $user->comment) }}</x-ui.textarea>
                </x-ui.form-group>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" form="management-user-edit-form" variant="primary" size="lg">
                        Сохранить
                    </x-ui.button>

                    <x-ui.button tag="a" :href="route('management.users.index')" variant="secondary" size="lg">
                        Закрыть
                    </x-ui.button>
                </div>

                <form
                    method="POST"
                    action="{{ route('management.users.reset-password', $user) }}"
                    class="inline-flex shrink-0"
                    onsubmit="return confirm('Сбросить пароль? Старый пароль перестанет действовать, все текущие сессии этого пользователя будут завершены.');"
                >
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="lg">
                        Сбросить пароль
                    </x-ui.button>
                </form>
            </div>

            @can('access-management-users')
                @if (auth()->user()->hasElevatedAccess() || (auth()->user()->isPartner() && $user->parent_user_id === auth()->id()))
                    <div class="border-t border-border pt-4 mt-4">
                        <form
                            method="POST"
                            action="{{ route('management.users.destroy', $user) }}"
                            class="inline-flex"
                            onsubmit="return confirm('Удалить этого сотрудника? Это действие необратимо.');"
                        >
                            @csrf
                            @method('DELETE')

                            <x-ui.button type="submit" variant="outline" size="lg" class="border-destructive text-destructive hover:bg-destructive/10">
                                Удалить сотрудника
                            </x-ui.button>
                        </form>
                    </div>
                @endif
            @endcan
        </x-ui.card>

        @if ($user->isPartner())
            <x-ui.card padding="none" class="mt-6 overflow-hidden">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="font-semibold text-foreground">Заказы партнёра</h2>
                    <p class="text-sm text-muted-foreground">
                        Последние 50 заказов по источникам партнёра.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-muted/30">
                            <tr>
                                <th class="px-4 py-3 font-medium text-muted-foreground">ID</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Город</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Статус</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Клиент</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Телефон</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Начисление</th>
                                <th class="px-4 py-3 font-medium text-muted-foreground">Создано</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse (($partnerOrders ?? collect()) as $order)
                                <tr class="border-b border-border/60 transition-all hover:bg-primary/5">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('orders.show', $order->id) }}" class="text-primary hover:underline">
                                            #{{ $order->id }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">{{ $order->city->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :status="$order->status" type="order" />
                                    </td>
                                    <td class="px-4 py-3">{{ $order->client_name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $order->client_phone ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        {{ number_format((float) ($order->charge_amount ?? 0), 0, ',', ' ') }} ₽
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $order->created_local ? $order->created_local->format('d.m.Y H:i') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                        Заказов по партнёру пока нет.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card padding="none" class="mt-6 overflow-hidden">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="font-semibold text-foreground">Последние изменения</h2>
                    <p class="text-sm text-muted-foreground">
                        Последние изменения по заказам и сотрудникам партнёра.
                    </p>
                </div>

                <div class="divide-y divide-border">
                    @forelse (($recentChanges ?? collect()) as $change)
                        <a href="{{ $change['url'] }}" class="block px-4 py-3 transition-all hover:bg-primary/5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-medium text-foreground">
                                    {{ $change['title'] }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ $change['date'] ? $change['date']->format('d.m.Y H:i') : '—' }}
                                </div>
                            </div>

                            <div class="mt-1 text-sm text-muted-foreground">
                                {{ $change['description'] }}
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-8 text-center text-muted-foreground">
                            Изменений пока нет.
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const copyBtn = document.getElementById('edit-copy-generated-password');
            const copyEl = document.getElementById('edit-generated-password-value');

            if (copyBtn && copyEl) {
                copyBtn.addEventListener('click', function () {
                    const text = copyEl.textContent || '';

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            copyBtn.textContent = 'Скопировано';
                            setTimeout(function () {
                                copyBtn.textContent = 'Копировать';
                            }, 2000);
                        });
                    }
                });
            }

            var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

            var generatedPasswordLength = {{ \App\Support\PortalUserProvisioning::PASSWORD_GENERATED_LENGTH }};

            function genPassword() {
                var s = '';
                for (var i = 0; i < generatedPasswordLength; i++) {
                    s += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return s;
            }

            document.querySelectorAll('[data-gen-password]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-gen-password');
                    var el = id && document.getElementById(id);

                    if (el) {
                        el.value = genPassword();
                    }
                });
            });

            document.querySelectorAll('[data-letters-only]').forEach(function (input) {
                input.addEventListener('input', function () {
                    input.value = input.value.replace(/[^\p{L}]/gu, '');
                });
            });
        })();
    </script>

    @if (auth()->user()->hasElevatedAccess())
        <script>
            (function () {
                const role = document.getElementById('role');
                const extra = document.getElementById('manager-extra');
                const partnerExtra = document.getElementById('partner-extra');
                const note = document.getElementById('non-manager-note');

                function sync() {
                    const v = role && role.value;

                    if (!note) return;

                    const isMgr = v === 'manager';
                    const isPartner = v === 'partner';
                    const isAdmin = v === 'general_director' || v === 'developer';

                    if (extra) extra.classList.toggle('hidden', !isMgr);
                    if (partnerExtra) partnerExtra.classList.toggle('hidden', !isPartner);

                    note.classList.toggle('hidden', isMgr || isPartner);
                }

                if (role) {
                    role.addEventListener('change', sync);
                    sync();
                }

                const editForm = document.getElementById('management-user-edit-form');

                if (editForm) {
                    editForm.addEventListener('submit', function () {
                        document.querySelectorAll('#manager-extra.hidden input, #partner-extra.hidden input').forEach(function (el) {
                            el.disabled = true;
                        });
                    });
                }
            })();
        </script>
    @endif
@endpush