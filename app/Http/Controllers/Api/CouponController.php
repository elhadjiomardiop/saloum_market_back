<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        $coupons = Coupon::query()->orderByDesc('id')->get();

        return response()->json([
            'data' => $coupons,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons', 'code')],
            'description' => ['required', 'string', 'max:255'],
            'discount' => ['required', 'integer', 'min:1', 'max:100'],
            'forNewUser' => ['required', 'boolean'],
            'forMember' => ['required', 'boolean'],
            'isPublic' => ['nullable', 'boolean'],
            'expiresAt' => ['nullable', 'date'],
        ]);

        $coupon = Coupon::create([
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'],
            'discount' => $validated['discount'],
            'for_new_user' => $validated['forNewUser'],
            'for_member' => $validated['forMember'],
            'is_public' => $validated['isPublic'] ?? false,
            'expires_at' => $validated['expiresAt'] ?? null,
        ]);

        return response()->json([
            'message' => 'Coupon cree avec succes.',
            'data' => $coupon,
        ], 201);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon supprime avec succes.',
        ]);
    }
}
