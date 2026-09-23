<?php

namespace App\Http\Requests\Settings;

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerPhoneRequest extends FormRequest
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

        $phoneRule = ['required', 'string', 'regex:/^\+7[0-9]{10}$/'];

        if ($useCrm) {
            return [
                'phone' => $phoneRule,
                'reference_source_id' => ['required', 'integer', ReferenceSource::existsRuleVisibleToPartnerUser($ownerId)],
                'source_id' => ['prohibited'],
            ];
        }

        return [
            'phone' => $phoneRule,
            'source_id' => ['required', 'integer', Source::existsRuleForOwnerId($ownerId)],
            'reference_source_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Телефон в формате +7 и 10 цифр (например +79091234567).',
        ];
    }
}
