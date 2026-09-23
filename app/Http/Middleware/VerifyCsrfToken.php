<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * CSRF через meta/form; без JS-readable XSRF-TOKEN cookie (ТЗ FR-PRIV-01).
 */
class VerifyCsrfToken extends Middleware
{
    protected $addHttpCookie = false;
}
