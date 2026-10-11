<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    public function __construct(private OrderEventService $events) {}

    public function create(
        Order $order,
        float $amount,
        string $method,
        string $reason,
        ?int $paymentId = null,
        ?int $returnRequestId = null
    ): Refund {
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $order,
            $amount,
            $method,
            $reason,
            $paymentId,
            $returnRequestId
        ) {
            $refund = $order->refunds()->create([
                'payment_id' => $paymentId,
                'return_request_id' => $returnRequestId,
                'amount' => $amount,
                'currency' => $order->currency,
                'method' => $method,
                'status' => Refund::PENDING,
                'reason' => $reason,
            ]);

            $this->events->record(
                $order,
                'refund.created',
                'Refund created.',
                null,
                null,
                ['refund_id' => $refund->id, 'amount' => $amount]
            );

            return $refund;
        });
    }

    public function complete(Refund $refund, ?string $providerReference = null): Refund
    {
        $refund->update([
            'status' => Refund::COMPLETED,
            'provider_reference' => $providerReference,
            'processed_at' => now(),
        ]);

        $order = $refund->order;

        $refunded = (float) $order->refunds()
            ->where('status', Refund::COMPLETED)
            ->sum('amount');

        $order->update([
            'payment_status' => $refunded >= (float) $order->grand_total
                ? Order::PAYMENT_REFUNDED
                : Order::PAYMENT_PARTIALLY_REFUNDED,
        ]);

        $this->events->record(
            $order,
            'refund.completed',
            'Refund completed.'
        );

        return $refund->refresh();
    }
}
