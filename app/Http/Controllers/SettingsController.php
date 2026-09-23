<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreBankCardRequest;
use App\Http\Requests\Settings\StorePartnerPhoneRequest;
use App\Http\Requests\Settings\UpdateApiCredentialsRequest;
use App\Http\Requests\Settings\UpdateBankCardRequest;
use App\Http\Requests\Settings\UpdatePartnerLegalRequest;
use App\Http\Requests\Settings\UpdatePartnerPhonesRequest;
use App\Models\PartnerBankCard;
use App\Models\PartnerPhone;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Support\OrderSourceOptions;
use App\Services\LevelionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index(Request $request, LevelionApiService $api)
    {
        /** @var User $auth */
        $auth = auth()->user();
        $ownerId = $auth->effectiveOwnerId();
        $owner = User::query()->findOrFail($ownerId);

        $ctx = $this->phoneSourceContext($auth, $api);

        $editingBankCard = null;
        if ($request->filled('edit_card')) {
            $editingBankCard = PartnerBankCard::query()
                ->where('user_id', $ownerId)
                ->whereKey((int) $request->query('edit_card'))
                ->first();
        }

        return view('settings.index', [
            'user' => $auth,
            'owner' => $owner,
            'bankCards' => PartnerBankCard::query()->where('user_id', $ownerId)->orderBy('id')->get(),
            'partnerPhones' => PartnerPhone::query()->where('user_id', $ownerId)->orderBy('id')->get(),
            'useCrmReferenceSources' => $ctx['use_crm'],
            'localSources' => $ctx['local_sources'],
            'referenceSources' => $ctx['reference_sources'],
            'editingBankCard' => $editingBankCard,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return back()->with('success', 'Профиль обновлён.');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Неверный текущий пароль.',
            ])->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->password = $validated['password'];
        $user->save();

        return back()->with('success', 'Пароль изменён.');
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:dark,light'],
        ]);

        $user = auth()->user();
        $user->theme = $validated['theme'];
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Тема обновлена.');
    }

    public function updateApiCredentials(UpdateApiCredentialsRequest $request)
    {
        abort_unless(auth()->user()?->hasElevatedAccess(), 403);
        $owner = $this->resolveOwnerUser();

        $data = $request->validated();

        $owner->api_login = $data['api_login'];

        if (! empty($data['api_password'])) {
            $owner->api_password = $data['api_password'];
        }

        if ($owner->partner_code === null || $owner->partner_code === '') {
            $owner->partner_code = Str::upper(Str::random(12));
        }

        $owner->save();

        return back()->with('success', 'Данные API сохранены.');
    }

    public function storeBankCard(StoreBankCardRequest $request)
    {
        abort_if(auth()->user()?->isManager(), 403);

        $ownerId = auth()->user()->effectiveOwnerId();
        $data = $request->validated();

        PartnerBankCard::query()->create([
            'user_id' => $ownerId,
            'type' => $data['type'],
            'card_number' => $data['card_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'bik' => $data['bik'] ?? null,
            'correspondent_account' => $data['correspondent_account'] ?? null,
            'inn' => $data['inn'] ?? null,
            'bank' => $data['bank'],
            'recipient' => $data['recipient'],
            'recipient_birth_date' => $data['recipient_birth_date'] ?? null,
        ]);

        $msg = ($data['type'] ?? '') === PartnerBankCard::TYPE_IP
            ? 'ИП-реквизиты сохранены.'
            : 'Карта сохранена.';

        return redirect()
            ->route('settings.index')
            ->with('success', $msg)
            ->withFragment('payout-requisites-form');
    }

    public function updateBankCard(UpdateBankCardRequest $request, PartnerBankCard $bankCard)
    {
        abort_if(auth()->user()?->isManager(), 403);
        $this->ensureBankCardOwned($bankCard);

        $data = $request->validated();
        $bankCard->fill([
            'type' => $data['type'],
            'card_number' => $data['card_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'bik' => $data['bik'] ?? null,
            'correspondent_account' => $data['correspondent_account'] ?? null,
            'inn' => $data['inn'] ?? null,
            'bank' => $data['bank'],
            'recipient' => $data['recipient'],
            'recipient_birth_date' => $data['recipient_birth_date'] ?? null,
        ]);
        $bankCard->save();

        $msg = $bankCard->isIp() ? 'ИП-реквизиты обновлены.' : 'Карта обновлена.';

        return redirect()
            ->route('settings.index')
            ->with('success', $msg)
            ->withFragment('payout-requisites-form');
    }

    public function destroyBankCard(PartnerBankCard $bankCard)
    {
        abort_if(auth()->user()?->isManager(), 403);
        $this->ensureBankCardOwned($bankCard);
        $bankCard->delete();

        return back()->with('success', 'Реквизиты удалены.');
    }

    public function updateLegal(UpdatePartnerLegalRequest $request)
    {
        $owner = $this->resolveOwnerUser();
        $validated = $request->validated();
        $form = $validated['legal_form'] ?? null;

        if ($form === null || $form === '') {
            $owner->legal_form = null;
            $owner->legal_name = null;
            $owner->inn = null;
            $owner->ogrn = null;
            $owner->legal_address = null;
        } else {
            $owner->legal_form = $form;
            $owner->legal_name = $validated['legal_name'] ?? null;
            $owner->inn = $validated['inn'] ?? null;
            $owner->ogrn = $validated['ogrn'] ?? null;
            $owner->legal_address = $validated['legal_address'] ?? null;
        }

        $owner->save();

        return back()->with('success', 'Данные ИП / ООО сохранены.');
    }

    public function updatePartnerPhones(UpdatePartnerPhonesRequest $request, LevelionApiService $api)
    {
        $ownerId = auth()->user()->effectiveOwnerId();
        $useCrm = $api->isConfigured();

        foreach ($request->validated('phones') as $row) {
            $phone = PartnerPhone::query()
                ->where('user_id', $ownerId)
                ->whereKey($row['id'])
                ->firstOrFail();

            if ($useCrm) {
                $phone->reference_source_id = (int) $row['reference_source_id'];
                $phone->source_id = null;
            } else {
                $phone->source_id = (int) $row['source_id'];
                $phone->reference_source_id = null;
            }
            $phone->save();
        }

        return back()->with('success', 'Телефоны обновлены.');
    }

    public function storePartnerPhone(StorePartnerPhoneRequest $request, LevelionApiService $api)
    {
        $ownerId = auth()->user()->effectiveOwnerId();
        $data = $request->validated();
        $useCrm = $api->isConfigured();

        PartnerPhone::query()->create([
            'user_id' => $ownerId,
            'phone' => $data['phone'],
            'source_id' => $useCrm ? null : (int) $data['source_id'],
            'reference_source_id' => $useCrm ? (int) $data['reference_source_id'] : null,
        ]);

        return back()->with('success', 'Телефон добавлен.');
    }

    private function resolveOwnerUser(): User
    {
        $ownerId = auth()->user()->effectiveOwnerId();

        return User::query()->findOrFail($ownerId);
    }

    private function ensureBankCardOwned(PartnerBankCard $card): void
    {
        $ownerId = auth()->user()->effectiveOwnerId();
        abort_if((int) $card->user_id !== $ownerId, 403);
    }

    /**
     * @return array{use_crm: bool, local_sources: \Illuminate\Support\Collection, reference_sources: \Illuminate\Support\Collection}
     */
    private function phoneSourceContext(User $user, LevelionApiService $api): array
    {
        $ownerId = $user->effectiveOwnerId();

        if ($api->isConfigured()) {
            return [
                'use_crm' => true,
                'local_sources' => collect(),
                'reference_sources' => OrderSourceOptions::referenceSourcesForUser($user)->map(
                    fn (ReferenceSource $source) => $source->only(['id', 'name'])
                ),
            ];
        }

        $sourcesQuery = Source::query()->where('user_id', $ownerId)->orderBy('name');
        if ($user->isManager()) {
            $sourcesQuery->whereIn('id', $user->allowedSources()->pluck('sources.id'));
        }

        return [
            'use_crm' => false,
            'local_sources' => $sourcesQuery->get(['id', 'name']),
            'reference_sources' => collect(),
        ];
    }
}
