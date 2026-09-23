<?php

namespace App\Http\Requests\Settings;

class UpdateBankCardRequest extends StoreBankCardRequest
{
    protected function getRedirectUrl(): string
    {
        $card = $this->route('bankCard');

        if ($card) {
            return route('settings.index', ['edit_card' => $card->id]).'#payout-requisites-form';
        }

        return parent::getRedirectUrl();
    }
}
