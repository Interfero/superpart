<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasElevatedAccess();
    }

    public function rules(): array
    {
        return [
            'api_login' => ['required', 'string', 'max:255'],
            'api_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
