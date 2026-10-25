<?php

namespace App\Services;

use App\Support\Money;

/**
 * Totals are calculated here, from prices ProductService gave us. Nothing price-like comes from the browser.
 * Promotions / coupons would be added in this one class.
 */
class PricingService
{
    /**
     * @param  array<int, array{unit_price:string|float, quantity:int}>  $lines
     * @return array{lines: array<int, array{subtotal:string, tax:string, total:string}>, subtotal:string, discount_total:string, tax_total:string, shipping_total:string, grand_total:string}
     */
    public function calculate(array $lines): array
    {
        $rate = (float) config('orders.tax_rate', 0);
        $subtotalMinor = 0;
        $taxMinor = 0;
        $out = [];

        foreach ($lines as $i => $line) {
            $lineMinor = Money::toMinor($line['unit_price']) * (int) $line['quantity'];
            $lineTax = (int) round($lineMinor * $rate);

            $out[$i] = [
                'subtotal' => Money::fromMinor($lineMinor),
                'tax' => Money::fromMinor($lineTax),
                'total' => Money::fromMinor($lineMinor + $lineTax),
            ];

            $subtotalMinor += $lineMinor;
            $taxMinor += $lineTax;
        }

        $shippingMinor = Money::toMinor(config('orders.shipping_fee', 0));
        $freeOver = config('orders.free_shipping_over');

        if ($freeOver !== null && $freeOver !== '' && $subtotalMinor >= Money::toMinor($freeOver)) {
            $shippingMinor = 0;
        }

        return [
            'lines' => $out,
            'subtotal' => Money::fromMinor($subtotalMinor),
            'discount_total' => Money::fromMinor(0),
            'tax_total' => Money::fromMinor($taxMinor),
            'shipping_total' => Money::fromMinor($shippingMinor),
            'grand_total' => Money::fromMinor($subtotalMinor + $taxMinor + $shippingMinor),
        ];
    }
}
