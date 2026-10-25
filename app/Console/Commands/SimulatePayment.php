<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use App\Services\Payments\SandboxGateway;
use Illuminate\Console\Command;

/**
 * Development only: plays the part of the payment gateway.
 *   php artisan payments:simulate SBX-ABC123 --amount=1310.00            (payment succeeded)
 *   php artisan payments:simulate SBX-ABC123 --fail                      (payment failed)
 * It builds the same signed body a real gateway would POST to /api/payments/webhook.
 */
class SimulatePayment extends Command
{
    protected $signature = 'payments:simulate {provider_reference} {--amount=} {--fail}';

    protected $description = 'Send a signed sandbox webhook (development only)';

    public function handle(PaymentService $payments, SandboxGateway $gateway): int
    {
        if (app()->isProduction()) {
            $this->error('Not available in production.');

            return self::FAILURE;
        }

        $body = json_encode(array_filter([
            'provider_reference' => $this->argument('provider_reference'),
            'status' => $this->option('fail') ? 'failed' : 'succeeded',
            'amount' => $this->option('amount'),
            'failure_code' => $this->option('fail') ? 'declined' : null,
            'failure_message' => $this->option('fail') ? 'Card declined (simulated).' : null,
        ], fn ($v) => $v !== null));

        $this->info('Result: '.$payments->handleWebhook($body, $gateway->sign($body)));

        return self::SUCCESS;
    }
}
