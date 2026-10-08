<?php

namespace App\Services;

class PricingService
{
    /**
     * Pricing is deliberately kept isolated so promotions, coupons,
     * tax and shipping rules can be added without bloating OrderService.
     */
    public function calculate(array $items, float $discount = 0, float $tax = 0, float $shipping = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += ((float) $item['unit_price']) * (int) $item['quantity'];
        }

        $discount = max(0, min($discount, $subtotal));
        $tax = max(0, $tax);
        $shipping = max(0, $shipping);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'shipping_total' => round($shipping, 2),
            'grand_total' => round($subtotal - $discount + $tax + $shipping, 2),
        ];
    }
}
