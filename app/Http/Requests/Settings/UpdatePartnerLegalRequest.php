<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerLegalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isManager();
    }

    public function rules(): array
    {
        $form = $this->input('legal_form');

        return [
            'legal_form' => ['nullable', Rule::in(['', 'ip', 'ooo'])],
            'legal_name' => [
                Rule::requiredIf(in_array($form, ['ip', 'ooo'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'inn' => [
                Rule::requiredIf(in_array($form, ['ip', 'ooo'], true)),
                'nullable',
                'string',
                'regex:/^\d{10}(\d{2})?$/',
            ],
            'ogrn' => [
                Rule::requiredIf(in_array($form, ['ip', 'ooo'], true)),
                'nullable',
                'string',
                'regex:/^\d{13}(\d{2})?$/',
            ],
            'legal_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'legal_name.required' => 'Укажите наименование ИП или ООО.',
            'inn.required' => 'Укажите ИНН.',
            'inn.regex' => 'ИНН: 10 цифр для ООО или 12 для ИП.',
            'ogrn.required' => 'Укажите ОГРН или ОГРНИП.',
            'ogrn.regex' => 'ОГРН: 13 цифр для ООО или 15 для ИП.',
        ];
    }
}
