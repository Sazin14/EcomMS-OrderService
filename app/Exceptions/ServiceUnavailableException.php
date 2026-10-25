<?php

namespace App\Exceptions;

use RuntimeException;

/** Another service (Inventory, ProductService) could not be reached or answered with an error: HTTP 503. */
class ServiceUnavailableException extends RuntimeException
{
    public function render()
    {
        return response()->json(['message' => $this->getMessage() ?: 'A required service is unavailable. Try again shortly.'], 503);
    }
}
