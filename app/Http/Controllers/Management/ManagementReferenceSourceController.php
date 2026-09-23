<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use App\Support\LocalSourceReferenceMirror;
use App\Support\OrderSourceFilter;
use App\Support\PartnerSourceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ManagementReferenceSourceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('access-management-reference-sources');

        $api = app(LevelionApiService::class);
        $crmConfigured = $api->isConfigured();

        if ($crmConfigured) {
            $api->syncReferenceSourcesFromLevelionIfStale(900);
        }

        $partners = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allSources = ReferenceSource::query()
            ->availableForSuperpart()
            ->orderBy('name')
            ->get(['id', 'name', 'city_name', 'levelion_source_id', 'local_source_id', 'shared_with_all_partners', 'available_for_superpart']);

        $sourceOptions = $allSources->map(fn (ReferenceSource $s) => (object) [
            'id' => (int) $s->id,
            'name' => OrderSourceFilter::labelWithCrmId($s)
                .($s->city_name ? ' ('.$s->city_name.')' : '')
                .($s->shared_with_all_partners ? ' — общий' : ''),
        ]);

        $sourcesByPartner = [];
        foreach ($partners as $partner) {
            $sourcesByPartner[(int) $partner->id] = $partner->allowedReferenceSources()
                ->pluck('reference_sources.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $sharedSources = $allSources->where('shared_with_all_partners', true)->values();

        return view('management.reference-sources.index', [
            'partners' => $partners,
            'sourceOptions' => $sourceOptions,
            'sourcesByPartner' => $sourcesByPartner,
            'allSources' => $allSources,
            'sharedSources' => $sharedSources,
            'crmConfigured' => $crmConfigured,
        ]);
    }

    /** Автосохранение: партнёр → список источников. */
    public function updatePartnerSources(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('access-management-reference-sources');

        if ($user->role !== User::ROLE_PARTNER) {
            abort(404);
        }

        $validated = $request->validate([
            'source_ids' => ['nullable', 'array'],
            'source_ids.*' => ['integer', Rule::exists('reference_sources', 'id')],
        ]);

        $sourceIds = collect($validated['source_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $api = app(LevelionApiService::class);
        $crmWarning = null;

        PartnerSourceAccess::syncForPartner($user, $sourceIds, $request->user());

        // CRM: первый назначенный источник получает partner id (остальные — multi в SuperPart).
        if ($api->isConfigured()) {
            foreach ($sourceIds as $refId) {
                $ref = ReferenceSource::query()->find($refId);
                if (! $ref || $ref->shared_with_all_partners) {
                    continue;
                }
                $crmId = (int) ($ref->levelion_source_id ?? 0);
                if ($crmId < 1) {
                    continue;
                }
                $result = $api->patchReferenceSourcePartner($crmId, (int) $user->id);
                if (! ($result['ok'] ?? false)) {
                    $crmWarning = $result['error'] ?? 'CRM не приняла закрепление.';
                }
                $ref->superpart_partner_id = (int) $user->id;
                $ref->shared_with_all_partners = false;
                $ref->save();
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Источники партнёра сохранены.',
                'warning' => $crmWarning,
                'source_ids' => $sourceIds,
            ]);
        }

        $redirect = redirect()
            ->route('management.reference-sources.index')
            ->with('success', 'Источники партнёра «'.$user->name.'» обновлены.');

        if ($crmWarning) {
            $redirect->with('warning', 'В SuperPart сохранено, но CRM: '.$crmWarning);
        }

        return $redirect;
    }

    public function update(Request $request, ReferenceSource $referenceSource): RedirectResponse|JsonResponse
    {
        $this->authorize('access-management-reference-sources');

        $validated = $request->validate([
            'shared_with_all' => ['nullable', 'boolean'],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_PARTNER))],
        ]);

        $api = app(LevelionApiService::class);
        if (! $api->isConfigured()) {
            return redirect()
                ->route('management.reference-sources.index')
                ->withErrors(['crm' => 'Интеграция с CRM не настроена (LEVELION_* в .env).']);
        }

        $sharedWithAll = $request->boolean('shared_with_all');
        $partnerIds = collect($validated['partner_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($sharedWithAll) {
            $partnerIds = [];
        }

        $crmPartnerId = $sharedWithAll ? null : ($partnerIds[0] ?? null);
        $crmId = (int) ($referenceSource->levelion_source_id ?? 0);
        $crmWarning = null;

        if ($crmId > 0) {
            $result = $api->patchReferenceSourcePartner($crmId, $crmPartnerId);
            if (! ($result['ok'] ?? false)) {
                $crmWarning = $result['error'] ?? 'CRM не приняла закрепление.';
            }
        }

        DB::transaction(function () use ($referenceSource, $crmPartnerId, $sharedWithAll, $partnerIds, $request) {
            $referenceSource->superpart_partner_id = $crmPartnerId;
            $referenceSource->shared_with_all_partners = $sharedWithAll;
            $referenceSource->available_for_superpart = true;
            $referenceSource->save();

            PartnerSourceAccess::syncPartnersForReferenceSource(
                $referenceSource->id,
                $partnerIds,
                $request->user()
            );

            if ($referenceSource->local_source_id !== null && $crmPartnerId !== null) {
                $local = Source::query()->find($referenceSource->local_source_id);
                if ($local) {
                    $local->user_id = $crmPartnerId;
                    $local->save();
                }
            }
        });

        if ($referenceSource->local_source_id !== null && $crmPartnerId !== null) {
            $local = Source::query()->find($referenceSource->local_source_id);
            if ($local) {
                LocalSourceReferenceMirror::sync($local);
            }
        }

        $msg = $sharedWithAll
            ? 'Источник сделан общим — видят все партнёры.'
            : (count($partnerIds)
                ? 'Доступ обновлён: '.count($partnerIds).' партнёр(ов).'
                : 'Доступ сброшен — источник никому не назначен.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $msg,
                'warning' => $crmWarning,
            ]);
        }

        $redirect = redirect()
            ->route('management.reference-sources.index')
            ->with('success', $msg);

        if ($crmWarning) {
            $redirect->with('warning', 'В SuperPart сохранено, но CRM ответила: '.$crmWarning);
        }

        return $redirect;
    }

    public function destroy(Request $request, ReferenceSource $referenceSource): RedirectResponse
    {
        $this->authorize('access-management-reference-sources');

        try {
            LocalSourceReferenceMirror::deleteOrphanReference($referenceSource);
        } catch (\Throwable $e) {
            Log::error('reference-source destroy', ['id' => $referenceSource->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('management.reference-sources.index')
            ->with('success', 'Источник убран из SuperPart.');
    }
}
