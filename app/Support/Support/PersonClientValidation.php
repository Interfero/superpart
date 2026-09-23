<?php

namespace App\Support;

/**
 * Правила валидации клиента и адреса (как в CRM persons/create).
 */
class PersonClientValidation
{
    public const PHONE_DIGITS_10 = 'regex:/^\d{10}$/';

    /** Улица, дом, квартира: буквы, цифры, пробел, дефис, точка, запятая, №, слэш */
    public const ADDRESS_FRAGMENT = 'regex:/^[\p{L}\p{N}\s\-\.,№\/]*$/u';

    public const ADDRESS_ADDS_MAX = 1000;
}
