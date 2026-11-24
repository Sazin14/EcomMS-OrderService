<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Promotion::query();

        if (in_array($type, ['coupon', 'promotion'], true)) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('promotions', 'code')],
            'type' => ['nullable', 'string', Rule::in(['coupon', 'promotion'])],
            'discount_type' => ['required', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $code = isset($validated['code']) && trim((string) $validated['code']) !== ''
            ? strtoupper(trim((string) $validated['code']))
            : null;

        $promotion = Promotion::create([
            'name' => $validated['name'],
            'code' => $code,
            'type' => $validated['type'] ?? 'coupon',
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'min_order_amount' => $validated['min_order_amount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_count' => 0,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Promotion created.',
            'promotion' => $promotion,
        ], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($promotion->id)],
            'type' => ['sometimes', 'string', Rule::in(['coupon', 'promotion'])],
            'discount_type' => ['sometimes', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['sometimes', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('code', $validated)) {
            $validated['code'] = $validated['code'] !== null && trim((string) $validated['code']) !== ''
                ? strtoupper(trim((string) $validated['code']))
                : null;
        }

        $promotion->update($validated);

        return response()->json([
            'message' => 'Promotion updated.',
            'promotion' => $promotion->fresh(),
        ]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json([
            'message' => 'Promotion deleted.',
        ]);
    }

    public function banners(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Banner::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('placement', 'like', $like);
            });
        }

        return response()->json($query->orderBy('sort_order')->latest()->paginate($perPage));
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'placement' => ['nullable', 'string', 'max:50'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'target_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $banner = Banner::create([
            'title' => $validated['title'],
            'placement' => $validated['placement'] ?? 'home_hero',
            'image_url' => $validated['image_url'] ?? null,
            'target_url' => $validated['target_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Banner created.',
            'banner' => $banner,
        ], 201);
    }

    public function updateBanner(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'placement' => ['sometimes', 'string', 'max:50'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'target_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $banner->update($validated);

        return response()->json([
            'message' => 'Banner updated.',
            'banner' => $banner->fresh(),
        ]);
    }

    public function destroyBanner(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted.',
        ]);
    }
}
