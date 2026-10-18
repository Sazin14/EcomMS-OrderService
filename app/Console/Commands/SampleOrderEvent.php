<?php

namespace App\Console\Commands;

use App\Support\Outbox;
use Illuminate\Console\Command;

/**
 * Development helper: puts a realistic order event into the outbox, so the whole path
 * (outbox -> RabbitMQ -> a consumer such as NotificationService) can be tested before the order logic exists.
 *   php artisan rabbitmq:sample-event order.placed
 *   php artisan outbox:publish --once
 */
class SampleOrderEvent extends Command
{
    private const TYPES = [
        'order.placed', 'order.confirmed', 'order.shipped', 'order.delivered',
        'order.cancelled', 'payment.paid', 'payment.failed',
    ];

    protected $signature = 'rabbitmq:sample-event {type=order.placed : order.placed | order.confirmed | order.shipped | order.delivered | order.cancelled | payment.paid | payment.failed}';

    protected $description = 'Save a sample order event in the outbox (for testing consumers)';

    public function handle(): int
    {
        $type = (string) $this->argument('type');

        if (! in_array($type, self::TYPES, true)) {
            $this->error('Unknown type. Use one of: '.implode(', ', self::TYPES));

            return self::FAILURE;
        }

        $extra = match ($type) {
            'order.shipped' => ['carrier' => 'Sample Courier', 'tracking_number' => 'TRK123456'],
            'order.cancelled' => ['reason' => 'Customer changed their mind'],
            'payment.paid' => ['reference' => 'PAY-SAMPLE'],
            'payment.failed' => ['reason' => 'Card declined'],
            default => [],
        };

        Outbox::record($type, $this->base() + $extra);

        $this->info("Saved {$type} in the outbox. Run  php artisan outbox:publish --once  to send it.");

        return self::SUCCESS;
    }

    private function base(): array
    {
        return [
            'order_number' => 'ORD-2026-000001',
            'type' => 'standard',
            'status' => 'pending_confirmation',
            'placed_at' => now()->toIso8601ZuluString(),
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'currency' => 'BDT',
            'subtotal' => '1250.00',
            'shipping_fee' => '60.00',
            'discount_total' => '0.00',
            'total' => '1310.00',
            'customer' => ['id' => 42, 'name' => 'Sample Customer', 'email' => 'customer@example.test', 'phone' => '+8801700000000', 'locale' => 'en'],
            'shipping' => ['name' => 'Sample Customer', 'phone' => '+8801700000000', 'address' => 'House 1, Road 2', 'city' => 'Dhaka', 'postcode' => '1207', 'country' => 'BD'],
            'items' => [
                ['sku' => 'NX1-8GB-128GB-SPACEGREY', 'name' => 'Nova X1', 'variant_label' => '8GB / 128GB / Space Grey', 'quantity' => 1, 'unit_price' => '1250.00', 'line_total' => '1250.00'],
            ],
        ];
    }
}
