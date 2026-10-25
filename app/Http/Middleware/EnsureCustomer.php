<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Goes after auth.jwt: the token must belong to a customer (staff tokens are refused on storefront routes). */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('user_type') !== 'customer') {
            return response()->json(['message' => 'Customer authentication required.'], 403);
        }

        return $next($request);
    }
}
