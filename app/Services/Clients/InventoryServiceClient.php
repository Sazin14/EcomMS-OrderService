<?php

namespace App\Services\Clients;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class InventoryServiceClient
{
    private string $baseUrl;
    private ?string $internalKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.inventory.url'), '/');
        $this->internalKey = config('services.inventory.internal_key');
    }

    private function request()
    {
        return Http::acceptJson()
            ->withHeaders([
                'X-Internal-Service-Key' => $this->internalKey,
            ])
            ->timeout(10);
    }

    public function reserve(array $payload): array
    {
        $response = $this->request()->post(
            "{$this->baseUrl}/api/internal/reservations",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory reservation failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function confirm(int $reservationId): array
    {
        $response = $this->request()->post(
            "{$this->baseUrl}/api/internal/reservations/{$reservationId}/confirm"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory reservation confirmation failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function release(int $reservationId): array
    {
        $response = $this->request()->post(
            "{$this->baseUrl}/api/internal/reservations/{$reservationId}/release"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory reservation release failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function fulfill(int $reservationId): array
    {
        $response = $this->request()->post(
            "{$this->baseUrl}/api/internal/reservations/{$reservationId}/fulfil"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory fulfillment failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function getReservation(int $reservationId): array
    {
        $response = $this->request()->get(
            "{$this->baseUrl}/api/internal/reservations/{$reservationId}"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory reservation lookup failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function createStockReturn(array $payload): array
    {
        $response = $this->request()->post(
            "{$this->baseUrl}/api/internal/stock-returns",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Inventory stock return failed: ' . $response->body()
            );
        }

        return $response->json();
    }
}
