<?php

namespace App\Exceptions;

use RuntimeException;

/** A checkout the customer can fix (some items out of stock, an item no longer sold ...). Rendered as JSON. */
class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public string $errorCode, public array $details = [], public int $status = 409)
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            ...$this->details,
        ], $this->status);
    }
}
