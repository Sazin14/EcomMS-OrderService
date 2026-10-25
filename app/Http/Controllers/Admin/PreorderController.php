<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Preorder;
use Illuminate\Http\Request;

class PreorderController extends Controller
{
    public function index(Request $request)
    {
        return Preorder::query()
            ->with('order:id,order_number,status,customer_name')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('variant_id'), fn ($q, $v) => $q->where('variant_id', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }
}
