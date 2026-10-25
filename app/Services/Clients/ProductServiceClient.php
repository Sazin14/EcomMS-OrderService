<?php

namespace App\Services\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * ProductService owns names and prices. The cart and checkout NEVER trust a price sent by the browser:
 * they ask ProductService (POST /api/internal/variants/lookup, see the ProductService patch).
 */
class ProductServiceClient
{
    /**
     * @param  array<int, int>  $variantIds
     * @return array<int, array{variant_id:int, product_id:int, sku:string, product_name:string, variant_label:?string, price:string, is_active:bool, image_url:?string}>
     *         keyed by variant_id; unknown ids are simply missing
     */
    public function variants(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));

        if ($variantIds === []) {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Internal-Key' => (string) config('services.product.internal_key')])
                ->connectTimeout(3)
                ->timeout(8)
                ->post(rtrim((string) config('services.product.url'), '/').'/api/internal/variants/lookup', ['variant_ids' => $variantIds]);
        } catch (ConnectionException) {
            throw new ServiceUnavailableException('Product service is not reachable.');
        }

        if ($response->failed()) {
            throw new ServiceUnavailableException('Product service answered with HTTP '.$response->status().'.');
        }

        $found = [];

        foreach ((array) $response->json('data', []) as $row) {
            $found[(int) $row['variant_id']] = $row;
        }

        return $found;
    }
}
