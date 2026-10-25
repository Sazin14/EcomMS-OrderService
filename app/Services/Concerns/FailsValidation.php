<?php

namespace App\Services\Concerns;

use Illuminate\Validation\ValidationException;

/** "You cannot do that now" -> HTTP 422 with a readable message instead of a 500. */
trait FailsValidation
{
    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
