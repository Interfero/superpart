<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use App\Support\LocalSourceReferenceMirror;
use App\Support\PartnerSourceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SourceController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $sourcesQuery = Source::query()->with(['user:id,name,email,role', 'referenceMirror:id,local_source_id,levelion_source_id,name']);

        if ($user->hasElevatedAccess()) {
            $sourcesQuery->orderByDesc('id');
        } else {
            $ownerId = $user->effectiveOwnerId();
            $sourcesQuery->where('user_id', $ownerId)->orderByDesc('id');

            if (app(LevelionApiService::class)->isConfigured()) {
                LocalSourceReferenceMirror::syncAllForOwner($ownerId);
            }
        }

        $sources = $sourcesQuery->paginate(100);
        $levelionConfigured = app(LevelionApiService::class)->isConfigured();
        $canManageAll = $user->hasElevatedAccess();
        $canManageSources = Gate::allows('manage-sources');

        return view('sources.index', compact('sources', 'levelionConfigured', 'canManageAll', 'canManageSources'));
    }

    public function show(int $id)
    {
        $source = $this->sourceForCurrentUserOrAbort($id);
        $source->loadMissing('referenceMirror');
        $assignedPartners = PartnerSourceAccess::partnersForLocalSource($source->id);

        $mirroredRefId = null;
        if (app(LevelionApiService::class)->isConfigured()) {
            $mirroredRefId = \App\Models\ReferenceSource::query()
                ->where('local_source_id', $source->id)
                ->value('id');
            if ($mirroredRefId) {
                $assignedPartners = PartnerSourceAccess::partnersForReferenceSource((int) $mirroredRefId);
            }
        }

        return view('sources.show', [
            'source' => $source,
            'canManageAll' => auth()->user()->hasElevatedAccess(),
            'partners' => $this->partnersForSourceForm(),
            'assignedPartners' => $assignedPartners,
        ]);
    }

    public function create()
    {
        Gate::authorize('manage-sources');

        return view('sources.create', [
            'partners' => $this->partnersForSourceForm(),
            'canAssignPartner' => auth()->user()->hasElevatedAccess(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-sources');

        $ownerId = $this->resolveOwnerUserId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'source_type' => ['required', Rule::in(array_keys(Source::typeLabels()))],
            'review_url' => 'nullable|url|max:2000',
            'owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_PARTNER)),
            ],
        ], [
            'owner_user_id.exists' => 'Выберите партнёра из списка.',
            'source_type.required' => 'Выберите тип источника.',
            'source_type.in' => 'Выберите тип источника из списка.',
        ]);

        $source = Source::create([
            'user_id' => $ownerId,
            'name' => $validated['name'],
            'comment' => $validated['comment'] ?? null,
            'source_type' => $validated['source_type'],
            'review_url' => $validated['review_url'] ?? null,
        ]);

        $mirrored = LocalSourceReferenceMirror::sync($source);

        $owner = User::query()->find($ownerId);
        if ($owner && $owner->isPartner()) {
            PartnerSourceAccess::attachOneToPartner(
                $owner,
                $mirrored?->id,
                $source->id,
                auth()->user()
            );
        }

        if ($mirrored && $mirrored->levelion_source_id) {
            $message = 'Источник создан в SuperPart, передан в CRM и доступен при регистрации заявки.';
        } elseif ($mirrored) {
            $message = 'Источник создан в SuperPart. Передача в CRM не выполнена — проверьте настройки интеграции.';
        } else {
            $message = 'Источник успешно создан';
        }

        return redirect()->route('sources.show', $source->id)
            ->with('success', $message);
    }

    public function update(Request $request, int $id)
    {
        Gate::authorize('manage-sources');
        $source = $this->sourceForCurrentUserOrAbort($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'source_type' => ['required', Rule::in(array_keys(Source::typeLabels()))],
            'review_url' => 'nullable|url|max:2000',
            'owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_PARTNER)),
            ],
        ], [
            'owner_user_id.exists' => 'Выберите партнёра из списка.',
            'source_type.required' => 'Выберите тип источника.',
            'source_type.in' => 'Выберите тип источника из списка.',
        ]);

        $payload = [
            'name' => $validated['name'],
            'comment' => $validated['comment'] ?? null,
            'source_type' => $validated['source_type'],
            'review_url' => $validated['review_url'] ?? null,
        ];

        if (auth()->user()->hasElevatedAccess() && ! empty($validated['owner_user_id'])) {
            $payload['user_id'] = (int) $validated['owner_user_id'];
        }

        $source->update($payload);

        $mirrored = LocalSourceReferenceMirror::sync($source->fresh());

        if (auth()->user()->hasElevatedAccess() && ! empty($payload['user_id'])) {
            $owner = User::query()->find($payload['user_id']);
            if ($owner && $owner->isPartner()) {
                PartnerSourceAccess::attachOneToPartner(
                    $owner,
                    $mirrored?->id,
                    $source->id,
                    auth()->user()
                );
            }
        }

        return redirect()->route('sources.show', $source->id)
            ->with('success', 'Источник сохранён в SuperPart и обновлён в CRM.');
    }

    public function destroy(int $id)
    {
        Gate::authorize('manage-sources');
        $source = $this->sourceForCurrentUserOrAbort($id);

        try {
            LocalSourceReferenceMirror::deleteLocalSource($source);
        } catch (\Throwable $e) {
            Log::error('SourceController::destroy', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('sources.show', $source->id)
                ->withErrors(['delete' => 'Не удалось удалить источник. Попробуйте ещё раз через минуту.']);
        }

        return redirect()->route('sources.index')
            ->with('success', 'Источник удалён');
    }

    private function sourceForCurrentUserOrAbort(int $id): Source
    {
        $user = auth()->user();

        $query = Source::query()->with('user:id,name,email');

        if (! $user->hasElevatedAccess()) {
            $query->where('user_id', $user->effectiveOwnerId());
        }

        return $query->whereKey($id)->firstOrFail();
    }

    private function resolveOwnerUserId(Request $request): int
    {
        $user = auth()->user();

        if ($user->hasElevatedAccess()) {
            $ownerId = (int) $request->input('owner_user_id');

            return $ownerId > 0 ? $ownerId : (int) $user->id;
        }

        return $user->effectiveOwnerId();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function partnersForSourceForm()
    {
        if (! auth()->user()->hasElevatedAccess()) {
            return collect();
        }

        return User::query()
            ->where('role', User::ROLE_PARTNER)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
