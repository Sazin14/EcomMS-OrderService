<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ReadsIdentity;

    public function __construct(private CartService $carts) {}

    public function show(Request $request)
    {
        return $this->carts->view($this->carts->getOrCreate($this->customerId($request)));
    }

    /** Only the variant and the quantity come from the browser; name and price are looked up in ProductService. */
    public function addItem(Request $request)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $cart = $this->carts->getOrCreate($this->customerId($request));

        return response()->json($this->carts->addItem($cart, $data['variant_id'], $data['quantity']), 201);
    }

    public function updateItem(Request $request, int $item)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);

        $cartItem = $this->carts->getOrCreate($this->customerId($request))->items()->findOrFail($item);

        return $this->carts->updateItem($cartItem, $data['quantity']);
    }

    public function removeItem(Request $request, int $item)
    {
        $this->carts->removeItem($this->carts->getOrCreate($this->customerId($request))->items()->findOrFail($item));

        return response()->json(['message' => 'Item removed.']);
    }

    public function clear(Request $request)
    {
        $this->carts->clear($this->carts->getOrCreate($this->customerId($request)));

        return response()->json(['message' => 'Cart cleared.']);
    }
}
