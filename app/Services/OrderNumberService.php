<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrderNumberService
{
    public function generate(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function generateReturnReference(): string
    {
        return 'RET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function generatePreorderReference(): string
    {
        return 'PRE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
