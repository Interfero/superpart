<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use App\Models\PartnerSourceAccessLog;
use App\Support\OrderSourceOptions;
use App\Support\PartnerSourceAccess;
use App\Support\PersonName;
use App\Support\PortalCityOptions;
use App\Support\PortalUserProvisioning;
use App\Support\UserSessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementUserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $query = User::query()
            ->with('parent')
            ->orderByDesc('id');

        if ($auth->isPartner()) {
            $query->where('parent_user_id', $auth->id)
                ->where('role', User::ROLE_MANAGER);
        } else {
            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }

            if ($request->filled('partner_id')) {
                $partnerId = (int) $request->input('partner_id');

                $query->where(function ($q) use ($partnerId) {
                    $q->where('id', $partnerId)
                        ->orWhere('parent_user_id', $partnerId);
                });
            }
        }

        $users = $query->paginate(25)->withQueryString();

        $partnerFilterOptions = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('management.users.index', compact('users', 'partnerFilterOptions'));
    }

    public function create(Request $request)
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $roleOptions = $this->roleOptionsForCreate($auth);
        $cities = $this->citiesForManagementForm($auth);
        $sourcesForMultiselect = $this->sourcesForMultiselect($auth, null);

        $partnerParents = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $directionOptions = $this->directionOptionsForMultiselect();

        return view('management.users.create', compact(
            'roleOptions',
            'cities',
            'sourcesForMultiselect',
            'partnerParents',
            'directionOptions',
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        if ($auth->isPartner()) {
            return $this->storeManagerForPartner($request, $auth);
        }

        $this->authorize('access-management-users');

        return $this->storeElevated($request, $auth);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $this->ensureCanEdit($auth, $user);

        $user->load(['allowedCities', 'allowedSources', 'allowedReferenceSources', 'parent']);

        $roleOptions = $this->roleOptionsForEdit($auth, $user);
        $cities = $this->citiesForManagementForm($auth);
        $sourcesForMultiselect = $this->sourcesForMultiselect($auth, $user);

        $partnerParents = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $split = PersonName::splitLegacyDisplayName($user->name);

        $nameDefaults = [
            'last_name' => old('last_name', $user->last_name ?? $split['last_name']),
            'first_name' => old('first_name', $user->first_name ?? $split['first_name']),
            'middle_name' => old('middle_name', $user->middle_name ?? $split['middle_name']),
        ];

        $partnerOrders = collect();
        $recentChanges = collect();

        if ($user->isPartner()) {
            $partnerOrders = Order::query()
                ->forPortalUser($user)
                ->with(['city', 'source', 'referenceSource', 'employee'])
                ->orderByDesc('created_local')
                ->limit(50)
                ->get();

            $orderChanges = collect();

            Order::query()
                ->forPortalUser($user)
                ->orderByDesc('updated_at')
                ->limit(15)
                ->get()
                ->each(function (Order $order) use ($orderChanges) {
                    $orderChanges->push([
                        'date' => $order->updated_at,
                        'title' => 'Изменена заявка #' . $order->id,
                        'description' => 'Статус: ' . ($order->status ?? '—'),
                        'url' => route('orders.show', $order->id),
                    ]);
                });

            $managerChanges = collect();

            $user->managedUsers()
                ->orderByDesc('updated_at')
                ->limit(15)
                ->get()
                ->each(function (User $manager) use ($managerChanges) {
                    $managerChanges->push([
                        'date' => $manager->updated_at,
                        'title' => 'Изменена учётка сотрудника',
                        'description' => $manager->displayFullName() . ' — ' . $manager->email,
                        'url' => route('management.users.edit', $manager),
                    ]);
                });

            $sourceAccessChanges = PartnerSourceAccessLog::query()
                ->where('partner_user_id', $user->id)
                ->with('actor')
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(function (PartnerSourceAccessLog $log) {
                    return [
                        'date' => $log->created_at,
                        'title' => $log->action === 'detach' ? 'Отвязан источник' : 'Привязан источник',
                        'description' => $log->description(),
                        'url' => route('management.users.edit', $log->partner_user_id),
                    ];
                });

            $recentChanges = $orderChanges
                ->concat($managerChanges)
                ->concat($sourceAccessChanges)
                ->sortByDesc('date')
                ->take(15)
                ->values();
        }

        $directionOptions = $this->directionOptionsForMultiselect();

        return view('management.users.edit', compact(
            'user',
            'roleOptions',
            'cities',
            'sourcesForMultiselect',
            'partnerParents',
            'nameDefaults',
            'partnerOrders',
            'recentChanges',
            'directionOptions',
        ));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $this->ensureCanEdit($auth, $user);

        if ($auth->isPartner()) {
            return $this->updateManagerForPartner($request, $auth, $user);
        }

        return $this->updateElevated($request, $auth, $user);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $this->ensureCanEdit($auth, $user);

        $plain = PortalUserProvisioning::generatePassword();

        UserSessionRevoker::revokeAll($user);

        $user->password = $plain;
        $user->save();

        return redirect()
            ->route('management.users.edit', $user)
            ->with('success', 'Пароль сброшен, все входы пользователя завершены. Сохраните новый пароль — он больше не отобразится.')
            ->with('generated_password', $plain);
    }

    private function citiesForManagementForm(User $auth)
    {
        return PortalCityOptions::citiesForManagementForm(
            $auth,
            $this->crmReferenceSourcesActive()
        );
    }

    private function directionOptionsForMultiselect(): \Illuminate\Support\Collection
    {
        return collect(User::directionOptions())
            ->map(fn (string $name, string $id) => (object) ['id' => $id, 'name' => $name])
            ->values();
    }

    /** @return array<string, list<mixed>> */
    private function directionValidationRules(): array
    {
        return [
            'direction_codes' => ['required', 'array', 'min:1'],
            'direction_codes.*' => ['string', Rule::in(array_keys(User::directionOptions()))],
        ];
    }

    /** @return array<string, string> */
    private function directionValidationMessages(): array
    {
        return [
            'direction_codes.required' => 'Выберите хотя бы одно направление (КП, РБТ или МНЧ).',
            'direction_codes.min' => 'Выберите хотя бы одно направление (КП, РБТ или МНЧ).',
        ];
    }

    /** @return array<string, string> */
    private function roleOptionsForCreate(User $auth): array
    {
        if ($auth->isPartner()) {
            return [
                User::ROLE_MANAGER => 'Менеджер партнёра',
            ];
        }

        if ($auth->isDeveloper() || $auth->isGeneralDirector()) {
            return [
                User::ROLE_GENERAL_DIRECTOR => 'Генеральный директор',
                User::ROLE_PARTNER => 'Партнёр',
            ];
        }

        return [
            User::ROLE_PARTNER => 'Партнёр',
            User::ROLE_MANAGER => 'Менеджер партнёра',
        ];
    }

    /** @return array<string, string> */
    private function roleOptionsForEdit(User $auth, User $subject): array
    {
        if ($auth->isPartner()) {
            return [
                User::ROLE_MANAGER => 'Менеджер партнёра',
            ];
        }

        if ($auth->hasElevatedAccess()) {
            return [
                User::ROLE_DEVELOPER => 'Разработчик',
                User::ROLE_GENERAL_DIRECTOR => 'Генеральный директор',
                User::ROLE_PARTNER => 'Партнёр',
                User::ROLE_MANAGER => 'Менеджер партнёра',
            ];
        }

        return [
            User::ROLE_PARTNER => 'Партнёр',
            User::ROLE_MANAGER => 'Менеджер партнёра',
        ];
    }

    private function sourcesForMultiselect(User $auth, ?User $subject): \Illuminate\Support\Collection
    {
        $rows = $this->sourcesForForm($auth, $subject);
        $exceptPartnerId = ($subject && $subject->isPartner()) ? (int) $subject->id : null;
        $warnShared = $auth->hasElevatedAccess()
            && ($subject === null || $subject->isPartner() || ($subject && ! $subject->isManager()));

        return $rows->map(function ($s) use ($exceptPartnerId, $warnShared) {
            $crmId = isset($s->levelion_source_id) && (int) $s->levelion_source_id > 0
                ? (int) $s->levelion_source_id
                : (int) $s->id;
            $label = '#'.$crmId.' — '.$s->name;

            if (isset($s->city_name) && is_string($s->city_name) && $s->city_name !== '') {
                $label .= ' ('.$s->city_name.')';
            }

            if (isset($s->owner_name)) {
                $label .= ' — '.$s->owner_name.' (#'.$s->owner_id.')';
            }

            if ($warnShared && PartnerSourceAccess::usesCrmSources()) {
                $others = PartnerSourceAccess::otherPartnersUsingReferenceSource((int) $s->id, $exceptPartnerId);
                if ($others->isNotEmpty()) {
                    $label .= ' — уже у: '.$others->pluck('name')->implode(', ');
                }
            }

            return (object) [
                'id' => (int) $s->id,
                'name' => $label,
            ];
        });
    }

    private function sourcesForForm(User $auth, ?User $subject)
    {
        if ($this->crmReferenceSourcesActive()) {
            // Партнёр или менеджер партнёра — только источники, назначенные владельцу.
            if ($auth->isPartner()) {
                return OrderSourceOptions::referenceSourcesForPartnerOwner($auth->id);
            }

            if ($subject && $subject->isManager() && $subject->parent_user_id) {
                return OrderSourceOptions::referenceSourcesForPartnerOwner((int) $subject->parent_user_id);
            }

            // Elevated: полный каталог для назначения партнёру / выбора при создании.
            return ReferenceSource::query()
                ->availableForSuperpart()
                ->where(function ($sources) {
                    $sources->whereNotNull('local_source_id')
                        ->orWhere(function ($crm) {
                            $crm->whereNull('local_source_id')
                                ->whereNotNull('levelion_source_id')
                                ->where('levelion_source_id', '>', 0);
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'city_name', 'superpart_partner_id']);
        }

        if ($auth->isPartner()) {
            $ids = PartnerSourceAccess::accessibleSourceIdsFor($auth);
            if ($ids->isEmpty()) {
                return Source::where('user_id', $auth->id)->orderBy('name')->get(['id', 'name']);
            }

            return Source::query()->whereIn('id', $ids->all())->orderBy('name')->get(['id', 'name']);
        }

        if ($subject && $subject->isManager() && $subject->parent_user_id) {
            $ids = PartnerSourceAccess::accessibleSourceIdsFor(
                User::query()->find((int) $subject->parent_user_id) ?? new User
            );
            if ($ids->isNotEmpty()) {
                return Source::query()->whereIn('id', $ids->all())->orderBy('name')->get(['id', 'name']);
            }
        }

        return Source::query()
            ->join('users', 'users.id', '=', 'sources.user_id')
            ->whereNull('sources.deleted_at')
            ->orderBy('users.name')
            ->orderBy('sources.name')
            ->select(['sources.id', 'sources.name', 'users.name as owner_name', 'users.id as owner_id'])
            ->get();
    }

    private function crmReferenceSourcesActive(): bool
    {
        return app(LevelionApiService::class)->isConfigured();
    }

    private function emailFieldRules(?int $ignoreUserId): array
    {
        return [
            'required',
            'string',
            'max:50',
            'email',
            Rule::unique('users', 'email')->ignore($ignoreUserId),
        ];
    }

    private function passwordCreateRules(): array
    {
        return [
            'nullable',
            'string',
            'max:'.PortalUserProvisioning::PASSWORD_MAX_LENGTH,
            'regex:/^[A-Za-z0-9]+$/',
        ];
    }

    private function newPasswordOptionalRules(): array
    {
        return [
            'nullable',
            'string',
            'max:'.PortalUserProvisioning::PASSWORD_MAX_LENGTH,
            'regex:/^[A-Za-z0-9]+$/',
        ];
    }

    private function normalizePersonRequest(Request $request): void
    {
        if ($request->has('middle_name')) {
            $t = trim((string) $request->input('middle_name', ''));
            $request->merge(['middle_name' => $t === '' ? null : $t]);
        }
    }

    private function storeElevated(Request $request, User $auth): RedirectResponse
    {
        $this->normalizePersonRequest($request);

        $allowedRoles = array_keys($this->roleOptionsForCreate($auth));

        $srcExists = $this->crmReferenceSourcesActive()
            ? ['integer', 'exists:reference_sources,id']
            : ['integer', Source::existsRuleActive()];

        $rules = array_merge(
            PersonName::partRules(),
            [
                'email' => $this->emailFieldRules(null),
                'password' => $this->passwordCreateRules(),
                'comment' => ['nullable', 'string', 'max:5000'],
                'role' => ['required', Rule::in($allowedRoles)],
                'parent_user_id' => ['nullable', 'integer', 'exists:users,id'],
                'city_ids' => ['nullable', 'array'],
                'city_ids.*' => ['integer', 'exists:cities,id'],
                'source_ids' => ['nullable', 'array'],
                'source_ids.*' => $srcExists,
            ]
        );

        if ($request->input('role') === User::ROLE_MANAGER) {
            $rules['parent_user_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['city_ids'] = ['nullable', 'array'];
            $rules['source_ids'] = ['nullable', 'array'];
            $rules = array_merge($rules, $this->directionValidationRules());
        }

        // Партнёр: города назначаются автоматически, источники — по желанию.
        if ($request->input('role') === User::ROLE_PARTNER) {
            $rules['city_ids'] = ['nullable', 'array'];
            $rules['source_ids'] = ['nullable', 'array'];
        }

        $validated = $request->validate($rules, array_merge([
            'parent_user_id.required' => 'Для менеджера укажите партнёра-владельца.',
        ], $this->directionValidationMessages()));

        if ($validated['role'] === User::ROLE_MANAGER) {
            $parent = User::query()->findOrFail((int) $validated['parent_user_id']);

            if (! $parent->isPartner()) {
                throw ValidationException::withMessages([
                    'parent_user_id' => 'Родитель должен быть партнёром.',
                ]);
            }

            if (! empty($validated['source_ids'])) {
                $srcRule = $this->crmReferenceSourcesActive()
                    ? ['integer', ReferenceSource::existsRuleVisibleToPartnerUser($parent->id)]
                    : ['integer', Source::existsRuleForOwnerId($parent->id)];

                $request->validate([
                    'source_ids.*' => $srcRule,
                ]);
            }
        }

        $plain = ! empty($validated['password'])
            ? $validated['password']
            : PortalUserProvisioning::generatePassword();

        $composed = PersonName::composeName(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_name'] ?? null
        );

        $user = User::create([
            'name' => $composed,
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($plain),
            'role' => $validated['role'],
            'parent_user_id' => $validated['role'] === User::ROLE_MANAGER
                ? (int) $validated['parent_user_id']
                : null,
            'balance' => 0,
            'theme' => 'dark',
            'comment' => $validated['comment'] ?? null,
            'allowed_directions' => $validated['role'] === User::ROLE_MANAGER
                ? array_values($validated['direction_codes'] ?? [])
                : null,
        ]);

        if ($validated['role'] === User::ROLE_MANAGER) {
            $user->allowedCities()->sync($validated['city_ids'] ?? []);

            if ($this->crmReferenceSourcesActive()) {
                $user->allowedReferenceSources()->sync($validated['source_ids'] ?? []);
                $user->allowedSources()->detach();
            } else {
                $user->allowedSources()->sync($validated['source_ids'] ?? []);
                $user->allowedReferenceSources()->detach();
            }
        } elseif ($validated['role'] === User::ROLE_PARTNER) {
            $this->syncAllCitiesForPartner($user);
            PartnerSourceAccess::syncForPartner($user, $validated['source_ids'] ?? [], $auth);
        }

        return redirect()
            ->route('management.users.index')
            ->with('success', 'Сотрудник создан. Сохраните пароль — он больше не отобразится.')
            ->with('generated_password', $plain);
    }

    private function storeManagerForPartner(Request $request, User $auth): RedirectResponse
    {
        $this->normalizePersonRequest($request);

        $partnerCityIds = $auth->allowedCities()->pluck('id')->all();

        $srcItem = $this->crmReferenceSourcesActive()
            ? ['integer', ReferenceSource::existsRuleVisibleToPartnerUser($auth->id)]
            : ['integer', Source::existsRuleForOwnerId($auth->id)];

        $validated = $request->validate(array_merge(
            PersonName::partRules(),
            [
                'email' => $this->emailFieldRules(null),
                'password' => $this->passwordCreateRules(),
                'comment' => ['nullable', 'string', 'max:5000'],
                'city_ids' => ['nullable', 'array'],
                'city_ids.*' => empty($partnerCityIds)
                    ? ['integer', 'exists:cities,id']
                    : ['integer', Rule::in($partnerCityIds)],
                'source_ids' => ['nullable', 'array'],
                'source_ids.*' => $srcItem,
            ],
            $this->directionValidationRules()
        ), $this->directionValidationMessages());

        $plain = ! empty($validated['password'])
            ? $validated['password']
            : PortalUserProvisioning::generatePassword();

        $composed = PersonName::composeName(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_name'] ?? null
        );

        $user = User::create([
            'name' => $composed,
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($plain),
            'role' => User::ROLE_MANAGER,
            'parent_user_id' => $auth->id,
            'balance' => 0,
            'theme' => 'dark',
            'comment' => $validated['comment'] ?? null,
            'allowed_directions' => array_values($validated['direction_codes'] ?? []),
        ]);

        $user->allowedCities()->sync($validated['city_ids'] ?? []);

        if ($this->crmReferenceSourcesActive()) {
            $user->allowedReferenceSources()->sync($validated['source_ids'] ?? []);
            $user->allowedSources()->detach();
        } else {
            $user->allowedSources()->sync($validated['source_ids'] ?? []);
            $user->allowedReferenceSources()->detach();
        }

        return redirect()
            ->route('management.users.index')
            ->with('success', 'Менеджер создан. Сохраните пароль — он больше не отобразится.')
            ->with('generated_password', $plain);
    }

    private function updateManagerForPartner(Request $request, User $auth, User $user): RedirectResponse
    {
        if ($user->parent_user_id !== $auth->id || ! $user->isManager()) {
            abort(403);
        }

        $this->normalizePersonRequest($request);

        $partnerCityIds = $auth->allowedCities()->pluck('id')->all();

        $srcItem = $this->crmReferenceSourcesActive()
            ? ['integer', ReferenceSource::existsRuleVisibleToPartnerUser($auth->id)]
            : ['integer', Source::existsRuleForOwnerId($auth->id)];

        $validated = $request->validate(array_merge(
            PersonName::partRules(),
            [
                'email' => $this->emailFieldRules($user->id),
                'new_password' => $this->newPasswordOptionalRules(),
                'comment' => ['nullable', 'string', 'max:5000'],
                'city_ids' => ['nullable', 'array'],
                'city_ids.*' => empty($partnerCityIds)
                    ? ['integer', 'exists:cities,id']
                    : ['integer', Rule::in($partnerCityIds)],
                'source_ids' => ['nullable', 'array'],
                'source_ids.*' => $srcItem,
            ],
            $this->directionValidationRules()
        ), $this->directionValidationMessages());

        $composed = PersonName::composeName(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_name'] ?? null
        );

        $user->name = $composed;
        $user->last_name = $validated['last_name'];
        $user->first_name = $validated['first_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->email = $validated['email'];
        $user->comment = $validated['comment'] ?? null;
        $user->allowed_directions = array_values($validated['direction_codes'] ?? []);

        if (! empty($validated['new_password'])) {
            UserSessionRevoker::revokeAll($user);
            $user->password = $validated['new_password'];
        }

        $user->save();

        $user->allowedCities()->sync($validated['city_ids'] ?? []);

        if ($this->crmReferenceSourcesActive()) {
            $user->allowedReferenceSources()->sync($validated['source_ids'] ?? []);
            $user->allowedSources()->detach();
        } else {
            $user->allowedSources()->sync($validated['source_ids'] ?? []);
            $user->allowedReferenceSources()->detach();
        }

        return redirect()
            ->route('management.users.index')
            ->with('success', 'Данные обновлены.');
    }

    private function updateElevated(Request $request, User $auth, User $user): RedirectResponse
    {
        $this->normalizePersonRequest($request);

        $allowedRoles = array_keys($this->roleOptionsForEdit($auth, $user));

        $srcExistsElevated = $this->crmReferenceSourcesActive()
            ? ['integer', 'exists:reference_sources,id']
            : ['integer', Source::existsRuleActive()];

        $rules = array_merge(
            PersonName::partRules(),
            [
                'email' => $this->emailFieldRules($user->id),
                'new_password' => $this->newPasswordOptionalRules(),
                'comment' => ['nullable', 'string', 'max:5000'],
                'role' => ['required', Rule::in($allowedRoles)],
                'parent_user_id' => ['nullable', 'integer', 'exists:users,id'],
                'city_ids' => ['nullable', 'array'],
                'city_ids.*' => ['integer', 'exists:cities,id'],
                'source_ids' => ['nullable', 'array'],
                'source_ids.*' => $srcExistsElevated,
            ]
        );

        if ($request->input('role') === User::ROLE_MANAGER) {
            $rules['parent_user_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['city_ids'] = ['nullable', 'array'];
            $rules['source_ids'] = ['nullable', 'array'];
            $rules = array_merge($rules, $this->directionValidationRules());
        }

        if ($request->input('role') === User::ROLE_PARTNER) {
            $rules['city_ids'] = ['nullable', 'array'];
            $rules['source_ids'] = ['nullable', 'array'];
        }

        $validated = $request->validate($rules, array_merge([
            'parent_user_id.required' => 'Для менеджера укажите партнёра-владельца.',
        ], $this->directionValidationMessages()));

        if ($validated['role'] === User::ROLE_MANAGER) {
            $parent = User::query()->findOrFail((int) $validated['parent_user_id']);

            if (! $parent->isPartner()) {
                throw ValidationException::withMessages([
                    'parent_user_id' => 'Родитель должен быть партнёром.',
                ]);
            }

            if (! empty($validated['source_ids'])) {
                $srcRule = $this->crmReferenceSourcesActive()
                    ? ['integer', ReferenceSource::existsRuleVisibleToPartnerUser($parent->id)]
                    : ['integer', Source::existsRuleForOwnerId($parent->id)];

                $request->validate([
                    'source_ids.*' => $srcRule,
                ]);
            }
        }

        $composed = PersonName::composeName(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_name'] ?? null
        );

        $user->name = $composed;
        $user->last_name = $validated['last_name'];
        $user->first_name = $validated['first_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->parent_user_id = $validated['role'] === User::ROLE_MANAGER
            ? (int) $validated['parent_user_id']
            : null;
        $user->comment = $validated['comment'] ?? null;
        $user->allowed_directions = $validated['role'] === User::ROLE_MANAGER
            ? array_values($validated['direction_codes'] ?? [])
            : null;

        if (! empty($validated['new_password'])) {
            UserSessionRevoker::revokeAll($user);
            $user->password = $validated['new_password'];
        }

        $user->save();

        if ($validated['role'] === User::ROLE_MANAGER) {
            $user->allowedCities()->sync($validated['city_ids'] ?? []);

            if ($this->crmReferenceSourcesActive()) {
                $user->allowedReferenceSources()->sync($validated['source_ids'] ?? []);
                $user->allowedSources()->detach();
            } else {
                $user->allowedSources()->sync($validated['source_ids'] ?? []);
                $user->allowedReferenceSources()->detach();
            }
        } elseif ($validated['role'] === User::ROLE_PARTNER) {
            $this->syncAllCitiesForPartner($user);
            PartnerSourceAccess::syncForPartner($user, $validated['source_ids'] ?? [], $auth);
        } else {
            $user->allowedCities()->detach();
            $user->allowedSources()->detach();
            $user->allowedReferenceSources()->detach();
        }

        return redirect()
            ->route('management.users.index')
            ->with('success', 'Данные обновлены.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('access-employees-directory');

        /** @var User $auth */
        $auth = $request->user();

        $this->ensureCanEdit($auth, $user);

        if ($auth->id === $user->id) {
            return redirect()
                ->route('management.users.edit', $user)
                ->withErrors(['delete' => 'Нельзя удалить свою учётную запись.']);
        }

        if ($auth->isPartner()) {
            if (! $user->isManager() || (int) $user->parent_user_id !== (int) $auth->id) {
                abort(403);
            }
        }

        if ($user->orders()->exists()) {
            return redirect()
                ->route('management.users.edit', $user)
                ->withErrors(['delete' => 'Нельзя удалить: у сотрудника есть заявки.']);
        }

        if ($user->managedUsers()->exists()) {
            return redirect()
                ->route('management.users.edit', $user)
                ->withErrors(['delete' => 'Нельзя удалить: у сотрудника есть привязанные менеджеры.']);
        }

        if ($user->sources()->exists()) {
            return redirect()
                ->route('management.users.edit', $user)
                ->withErrors(['delete' => 'Нельзя удалить: у пользователя есть источники.']);
        }

        if (
            $user->transactions()->exists()
            || $user->reviews()->exists()
            || $user->withdrawalRequests()->exists()
            || $user->employees()->exists()
        ) {
            return redirect()
                ->route('management.users.edit', $user)
                ->withErrors(['delete' => 'Нельзя удалить: у пользователя есть связанные записи в системе.']);
        }

        $user->allowedCities()->detach();
        $user->allowedSources()->detach();
        $user->allowedReferenceSources()->detach();

        UserSessionRevoker::revokeAll($user);

        $user->delete();

        return redirect()
            ->route('management.users.index')
            ->with('success', 'Сотрудник удалён.');
    }

    private function syncAllCitiesForPartner(User $partner): void
    {
        $ids = PortalCityOptions::allAvailableCityIds($this->crmReferenceSourcesActive());
        $partner->allowedCities()->sync($ids);
    }

    private function ensureCanEdit(User $auth, User $user): void
    {
        if ($auth->hasElevatedAccess()) {
            return;
        }

        if (
            $auth->isPartner()
            && $user->isManager()
            && (int) $user->parent_user_id === (int) $auth->id
        ) {
            return;
        }

        abort(403);
    }
}