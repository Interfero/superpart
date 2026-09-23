@extends('layouts.app')

@section('title', 'Создать сотрудника — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Справочники', 'url' => null],
        ['label' => 'Список сотрудников', 'url' => route('management.users.index')],
        ['label' => 'Создать', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
        <div class="px-6 py-7">
            <h1 class="text-3xl font-bold text-foreground">Создать сотрудника</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Создание нового сотрудника, партнёра или менеджера системы.
            </p>
        </div>
    </div>

    <x-ui.card padding="lg" class="w-full shadow-sm">
            @if (auth()->user()->isPartner())
                <form method="POST" action="{{ route('management.users.store') }}" class="space-y-6" autocomplete="off">
                    @csrf
                    <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_MANAGER }}">
                    <input type="hidden" name="parent_user_id" value="{{ auth()->id() }}">

                    <p class="text-sm text-muted-foreground">
                        Создаётся менеджер партнёра с доступом к выбранным городам и источникам. Пароль — {{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }} символов (можно сгенерировать); если оставить пустым — будет создан автоматически.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 *:min-w-0">
                        <x-ui.form-group label="Фамилия" name="last_name" required class="!mb-0">
                            <x-ui.input id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('last_name')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Имя" name="first_name" required class="!mb-0">
                            <x-ui.input id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="20" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('first_name')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Отчество" name="middle_name" class="!mb-0">
                            <x-ui.input id="middle_name" name="middle_name" value="{{ old('middle_name') }}" maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('middle_name')" />
                        </x-ui.form-group>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                        <x-ui.form-group label="Роль" class="!mb-0">
                            <x-ui.input type="text" value="Менеджер партнёра" disabled class="bg-muted/30" />
                        </x-ui.form-group>
                        <div></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                        <x-ui.form-group label="Электронная почта" name="email" required class="!mb-0">
                            <x-ui.input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="50" autocomplete="off" data-lpignore="true" :error="$errors->has('email')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Пароль" name="password" class="!mb-0">
                            <div class="flex flex-wrap items-stretch gap-2 min-w-0">
                                <x-ui.input id="password_partner" name="password" type="password" value="{{ old('password') }}" maxlength="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }}" autocomplete="new-password" data-lpignore="true" class="flex-1 min-w-0" :error="$errors->has('password')" placeholder="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }} символов или пусто" />
                                <x-ui.button type="button" variant="outline" size="md" class="shrink-0" data-gen-password="password_partner">Сгенерировать</x-ui.button>
                            </div>
                            @error('password')
                                <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                            @enderror
                        </x-ui.form-group>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                        <x-multiselect-checkboxes class="!mb-0" name="city_ids" label="Города" :options="$cities" :selected="old('city_ids', [])" placeholder="Необязательно" :required="false" />
                        <x-multiselect-checkboxes class="!mb-0" name="source_ids" label="Источники" :options="$sourcesForMultiselect" :selected="old('source_ids', [])" placeholder="Необязательно" :required="false" />
                    </div>

                    <x-direction-checkboxes
                        class="!mb-0"
                        :selected="old('direction_codes', [])"
                        :required="true"
                    />

                    <x-ui.form-group label="Комментарий" name="comment" class="!mb-0">
                        <x-ui.textarea name="comment" id="user_comment" rows="4" :error="$errors->has('comment')" placeholder="Необязательно">{{ old('comment') }}</x-ui.textarea>
                    </x-ui.form-group>

                    <div class="flex items-center gap-3 pt-2">
                        <x-ui.button type="submit" variant="primary" size="lg">Сохранить</x-ui.button>
                        <x-ui.button tag="a" :href="route('management.users.index')" variant="secondary" size="lg">Закрыть</x-ui.button>
                    </div>
                </form>
            @else
                <form id="management-user-create-elevated-form" method="POST" action="{{ route('management.users.store') }}" class="space-y-6" autocomplete="off">
                    @csrf

                    <p class="text-sm text-muted-foreground">
                        Пароль — {{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }} символов (можно сгенерировать); если оставить пустым — будет создан автоматически и показан один раз после сохранения.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 *:min-w-0">
                        <x-ui.form-group label="Фамилия" name="last_name" required class="!mb-0">
                            <x-ui.input id="last_name_e" name="last_name" value="{{ old('last_name') }}" required maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('last_name')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Имя" name="first_name" required class="!mb-0">
                            <x-ui.input id="first_name_e" name="first_name" value="{{ old('first_name') }}" required maxlength="20" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('first_name')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Отчество" name="middle_name" class="!mb-0">
                            <x-ui.input id="middle_name_e" name="middle_name" value="{{ old('middle_name') }}" maxlength="30" autocomplete="off" data-lpignore="true" data-letters-only="true" :error="$errors->has('middle_name')" />
                        </x-ui.form-group>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                        <x-ui.form-group label="Электронная почта" name="email" required class="!mb-0">
                            <x-ui.input type="email" id="email_elevated" name="email" value="{{ old('email') }}" required maxlength="50" autocomplete="off" data-lpignore="true" :error="$errors->has('email')" />
                        </x-ui.form-group>
                        <x-ui.form-group label="Пароль" name="password" class="!mb-0">
                            <div class="flex flex-wrap items-stretch gap-2 min-w-0">
                                <x-ui.input id="password_elevated" name="password" type="password" value="{{ old('password') }}" maxlength="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }}" autocomplete="new-password" data-lpignore="true" class="flex-1 min-w-0" :error="$errors->has('password')" placeholder="{{ \App\Support\PortalUserProvisioning::PASSWORD_MAX_LENGTH }} символов или пусто" />
                                <x-ui.button type="button" variant="outline" size="md" class="shrink-0" data-gen-password="password_elevated">Сгенерировать</x-ui.button>
                            </div>
                            @error('password')
                                <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                            @enderror
                        </x-ui.form-group>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:items-end *:min-w-0">
                        <x-ui.form-group label="Роль" name="role" required class="!mb-0">
                            <select name="role" id="role" required autocomplete="off" class="w-full min-h-10 rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground">
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_PARTNER) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                            @enderror
                        </x-ui.form-group>
                    </div>

                    <div id="partner-extra" class="space-y-2 {{ old('role', \App\Models\User::ROLE_PARTNER) === \App\Models\User::ROLE_PARTNER ? '' : 'hidden' }}">
                        <x-multiselect-checkboxes class="!mb-0" name="source_ids" label="Источники партнёра" :options="$sourcesForMultiselect" :selected="old('source_ids', [])" placeholder="Необязательно — можно назначить позже" :required="false" />
                        <p class="text-xs text-muted-foreground">
                            Города назначаются автоматически (все города SuperPart). Источники можно указать позже.
                        </p>
                    </div>

                    <div id="manager-extra" class="space-y-4 {{ old('role', \App\Models\User::ROLE_PARTNER) === \App\Models\User::ROLE_MANAGER ? '' : 'hidden' }}" data-role-block="manager">
                        <x-ui.form-group label="Партнёр-владелец" name="parent_user_id" class="!mb-0">
                            <select name="parent_user_id" autocomplete="off" class="w-full min-h-10 rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground">
                                <option value="">—</option>
                                @foreach ($partnerParents as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('parent_user_id') === (string) $p->id)>{{ $p->name }} ({{ $p->email }})</option>
                                @endforeach
                            </select>
                            @error('parent_user_id')
                                <p class="text-sm text-destructive mt-1">{{ $message }}</p>
                            @enderror
                        </x-ui.form-group>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:min-w-0">
                            <x-multiselect-checkboxes class="!mb-0" name="city_ids" label="Города" :options="$cities" :selected="old('city_ids', [])" placeholder="Необязательно" :required="false" />
                            <x-multiselect-checkboxes class="!mb-0" name="source_ids" label="Источники" :options="$sourcesForMultiselect" :selected="old('source_ids', [])" placeholder="Необязательно" :required="false" />
                        </div>

                        <x-direction-checkboxes
                            class="!mb-0"
                            :selected="old('direction_codes', [])"
                            :required="true"
                        />
                    </div>

                    <p id="non-manager-note" class="text-sm text-muted-foreground {{ in_array(old('role', \App\Models\User::ROLE_PARTNER), [\App\Models\User::ROLE_MANAGER, \App\Models\User::ROLE_PARTNER], true) ? 'hidden' : '' }}">
                        Для роли «генеральный директор» города и источники не задаются.
                    </p>

                    <x-ui.form-group label="Комментарий" name="comment" class="!mb-0">
                        <x-ui.textarea name="comment" id="user_comment_elevated" rows="4" :error="$errors->has('comment')" placeholder="Необязательно">{{ old('comment') }}</x-ui.textarea>
                    </x-ui.form-group>

                    <div class="flex items-center gap-3 pt-2">
                        <x-ui.button type="submit" variant="primary" size="lg">Сохранить</x-ui.button>
                        <x-ui.button tag="a" :href="route('management.users.index')" variant="secondary" size="lg">Закрыть</x-ui.button>
                    </div>
                </form>
            @endif
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        (function () {
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
                    if (el) el.value = genPassword();
                });
            });

            document.querySelectorAll('[data-letters-only]').forEach(function (input) {
                input.addEventListener('input', function () {
                    input.value = input.value.replace(/[^\p{L}]/gu, '');
                });
            });
        })();
    </script>

    @unless (auth()->user()->isPartner())
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

                const elevatedForm = document.getElementById('management-user-create-elevated-form');

                if (elevatedForm) {
                    elevatedForm.addEventListener('submit', function () {
                        document.querySelectorAll('#manager-extra.hidden input, #partner-extra.hidden input').forEach(function (el) {
                            el.disabled = true;
                        });
                    });
                }
            })();
        </script>
    @endunless
@endpush