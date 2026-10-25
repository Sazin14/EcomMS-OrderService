<?php

namespace App\Services\Clients;

use App\Exceptions\CheckoutException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only two things OrderService asks Inventory over HTTP, because the customer is waiting for the answer:
 * "reserve this stock" and "put me on the pre-order list". Everything else (confirm, ship, cancel, return)
 * travels as RabbitMQ events (see OrderEventPublisher), and Inventory finds the stock hold by reference = order number.
 */
class InventoryServiceClient
{
    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-Internal-Key' => (string) config('services.inventory.internal_key')])
            ->connectTimeout(3)
            ->timeout(8);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.inventory.url'), '/').'/api/internal/'.ltrim($path, '/');
    }

    /**
     * Holds stock for an order. Safe to repeat with the same $idempotencyKey (returns the same reservation).
     *
     * @param  array<int, array{variant_id:int, quantity:int}>  $items
     * @return array the reservation: id, reference, status, expires_at, items[]
     *
     * @throws InsufficientStockException  409, with the list of variants that could not be covered
     */
    public function reserve(string $reference, string $idempotencyKey, int $ttlMinutes, array $items): array
    {
        return $this->send(fn () => $this->http()->post($this->url('reservations'), [
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'ttl_minutes' => $ttlMinutes,
            'items' => array_values($items),
        ]))->json();
    }

    /** Only used to undo a reservation when saving the order failed afterwards. Normal cancellation is the order.cancelled event. */
    public function release(int $reservationId): void
    {
        $this->send(fn () => $this->http()->post($this->url("reservations/{$reservationId}/release")));
    }

    public function createPreorder(string $reference, int $variantId, int $quantity): array
    {
        return $this->send(fn () => $this->http()->post($this->url('preorders'), [
            'reference' => $reference,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]))->json();
    }

    public function cancelPreorder(string $reference, int $variantId): void
    {
        $this->send(fn () => $this->http()->delete($this->url("preorders/{$reference}/{$variantId}")));
    }

    /** @return array<int, array> the pre-order rows Inventory has for this order number (status pending|allocated|cancelled ...) */
    public function preorders(string $reference): array
    {
        return $this->send(fn () => $this->http()->retry(2, 200, throw: false)->get($this->url('preorders'), ['reference' => $reference]))->json() ?? [];
    }

    private function send(callable $call): Response
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException) {
            throw new ServiceUnavailableException('Inventory service is not reachable.');
        }

        if ($response->successful()) {
            return $response;
        }

        if ($response->status() === 409) {
            throw new InsufficientStockException((array) $response->json('shortages', []));
        }

        if ($response->status() === 422) {
            throw new CheckoutException(
                (string) ($response->json('message') ?? 'Inventory rejected the request.'),
                'inventory_rejected',
                ['errors' => $response->json('errors')],
                422
            );
        }

        throw new ServiceUnavailableException('Inventory service answered with HTTP '.$response->status().'.');
    }
}
