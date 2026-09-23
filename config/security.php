<?php

return [
    /*
    | Пока выключено — включить: TWO_FACTOR_ENFORCE=true в .env
    | после того как elevated-роли сами прошли /two-factor/setup.
    */
    'two_factor_enforce' => (bool) env('TWO_FACTOR_ENFORCE', false),
];
