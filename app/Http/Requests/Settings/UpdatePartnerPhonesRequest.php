<?php

namespace App\Http\Requests\Settings;

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerPhonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isManager();
    }

    public function rules(): array
    {
        /** @var User $auth */
        $auth = $this->user();
        $ownerId = $auth->effectiveOwnerId();
        $useCrm = app(\App\Services\LevelionApiService::class)->isConfigured();

        $base = [
            'phones' => ['required', 'array'],
            'phones.*.id' => [
                'required',
                'integer',
                Rule::exists('partner_phones', 'id')->where('user_id', $ownerId),
            ],
        ];

        if ($useCrm) {
            return $base + [
                'phones.*.reference_source_id' => [
                    'required',
                    'integer',
                    ReferenceSource::existsRuleVisibleToPartnerUser($ownerId),
                ],
                'phones.*.source_id' => ['prohibited'],
            ];
        }

        return $base + [
            'phones.*.source_id' => ['required', 'integer', Source::existsRuleForOwnerId($ownerId)],
            'phones.*.reference_source_id' => ['prohibited'],
        ];
    }
}
