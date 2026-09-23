<?php

namespace App\Http\Requests\Settings;

use App\Models\PartnerBankCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBankCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isManager();
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type', PartnerBankCard::TYPE_CARD);

        $this->merge([
            'type' => $type === PartnerBankCard::TYPE_IP
                ? PartnerBankCard::TYPE_IP
                : PartnerBankCard::TYPE_CARD,
            'card_number' => preg_replace('/\D/', '', (string) $this->input('card_number', '')) ?: null,
            'account_number' => preg_replace('/\D/', '', (string) $this->input('account_number', '')) ?: null,
            'bik' => preg_replace('/\D/', '', (string) $this->input('bik', '')) ?: null,
            'correspondent_account' => preg_replace('/\D/', '', (string) $this->input('correspondent_account', '')) ?: null,
            'inn' => preg_replace('/\D/', '', (string) $this->input('inn', '')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $isIp = $this->input('type') === PartnerBankCard::TYPE_IP;

        return [
            'type' => ['required', Rule::in([PartnerBankCard::TYPE_CARD, PartnerBankCard::TYPE_IP])],
            'card_number' => [$isIp ? 'nullable' : 'required', 'regex:/^[0-9]{16}$/'],
            'account_number' => [$isIp ? 'required' : 'nullable', 'regex:/^[0-9]{20}$/'],
            'bik' => [$isIp ? 'required' : 'nullable', 'regex:/^[0-9]{9}$/'],
            'correspondent_account' => ['nullable', 'regex:/^[0-9]{20}$/'],
            'inn' => [$isIp ? 'required' : 'nullable', 'regex:/^[0-9]{10}$|^[0-9]{12}$/'],
            'bank' => ['required', 'string', 'max:100'],
            'recipient' => ['required', 'string', 'max:150'],
            'recipient_birth_date' => [$isIp ? 'nullable' : 'required', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.required' => 'Укажите номер карты.',
            'card_number.regex' => 'Номер карты: ровно 16 цифр.',
            'account_number.required' => 'Укажите расчётный счёт.',
            'account_number.regex' => 'Расчётный счёт: ровно 20 цифр.',
            'bik.required' => 'Укажите БИК.',
            'bik.regex' => 'БИК: ровно 9 цифр.',
            'correspondent_account.regex' => 'Корр. счёт: ровно 20 цифр.',
            'inn.required' => 'Укажите ИНН.',
            'inn.regex' => 'ИНН: 10 или 12 цифр.',
            'bank.required' => 'Укажите банк.',
            'recipient.required' => 'Укажите получателя.',
            'recipient_birth_date.required' => 'Укажите дату рождения получателя.',
            'recipient_birth_date.before' => 'Дата рождения должна быть в прошлом.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== PartnerBankCard::TYPE_CARD) {
                return;
            }

            $bank = (string) $this->input('bank', '');
            if ($bank !== '' && ! preg_match('/^[\p{L}]{1,20}$/u', $bank)) {
                $validator->errors()->add('bank', 'Банк (для карты): только буквы, не более 20 символов.');
            }

            $recipient = (string) $this->input('recipient', '');
            if ($recipient !== '' && ! preg_match('/^[\p{L}\-]+(?:\ [\p{L}\-]+)*$/u', $recipient)) {
                $validator->errors()->add('recipient', 'ФИО получателя: только буквы и дефис, между частями — один пробел.');
            }
        });
    }
}
