<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function vendorDashboard(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        return response()->json([
            'totalProducts' => 0,
            'totalEarnings' => 0,
            'totalOrders' => 0,
            'ratings' => [],
            'store' => $store ? $this->transformStore($store) : null,
        ]);
    }

    public function myStore(Request $request): JsonResponse
    {
        $store = Store::with('user')->where('user_id', $request->user()->id)->first();

        return response()->json([
            'data' => $store ? $this->transformStore($store) : null,
        ]);
    }

    public function submitVendorStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = Store::where('user_id', $user->id)->first();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('stores', 'username')->ignore($existing?->id),
            ],
            'description' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'contact' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'logo' => ['required', 'image', 'max:4096'],
        ]);

        $logoPath = $request->file('logo')->store('stores/logos', 'public');

        if ($existing) {
            if ($existing->logo_path) {
                Storage::disk('public')->delete($existing->logo_path);
            }

            $existing->update([
                'name' => $validated['name'],
                'username' => strtolower($validated['username']),
                'description' => $validated['description'],
                'email' => $validated['email'],
                'contact' => $validated['contact'],
                'address' => $validated['address'],
                'logo_path' => $logoPath,
                'status' => 'pending',
                'is_active' => false,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return response()->json([
                'message' => 'Votre demande de mise a jour de boutique a ete envoyee.',
                'data' => $this->transformStore($existing->fresh()->load('user')),
            ]);
        }

        $store = Store::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'description' => $validated['description'],
            'email' => $validated['email'],
            'contact' => $validated['contact'],
            'address' => $validated['address'],
            'logo_path' => $logoPath,
            'status' => 'pending',
            'is_active' => false,
        ])->load('user');

        return response()->json([
            'message' => 'Votre demande de creation a ete envoyee.',
            'data' => $this->transformStore($store),
        ], 201);
    }

    public function adminDashboard(): JsonResponse
    {
        $allStores = Store::query()->get();
        $approvedStores = $allStores->where('status', 'approved');

        return response()->json([
            'orders' => 0,
            'stores' => $approvedStores->count(),
            'products' => 0,
            'revenue' => '0.00',
            'allOrders' => [],
            'pendingApplications' => $allStores->where('status', 'pending')->count(),
        ]);
    }

    public function adminStores(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = Store::with('user')->orderByDesc('id');
        if ($status) {
            $query->where('status', $status);
        }

        $stores = $query->get()->map(fn (Store $store) => $this->transformStore($store));

        return response()->json([
            'data' => $stores,
            'meta' => [
                'count' => $stores->count(),
            ],
        ]);
    }

    public function updateStoreStatus(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $isApproved = $validated['status'] === 'approved';

        $store->update([
            'status' => $validated['status'],
            'is_active' => $isApproved ? $store->is_active : false,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => $isApproved ? 'Boutique approuvee avec succes.' : 'Boutique rejetee avec succes.',
            'data' => $this->transformStore($store->fresh()->load('user')),
        ]);
    }

    public function toggleStoreActive(Store $store): JsonResponse
    {
        if ($store->status !== 'approved') {
            return response()->json([
                'message' => 'Seules les boutiques approuvees peuvent etre activees.',
            ], 422);
        }

        $store->update([
            'is_active' => ! $store->is_active,
        ]);

        return response()->json([
            'message' => 'Statut d\'activation de la boutique mis a jour.',
            'data' => $this->transformStore($store->fresh()->load('user')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'username' => $store->username,
            'description' => $store->description,
            'email' => $store->email,
            'contact' => $store->contact,
            'address' => $store->address,
            'status' => $store->status,
            'isActive' => (bool) $store->is_active,
            'logo' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
            'createdAt' => $store->created_at?->toISOString(),
            'user' => [
                'id' => $store->user?->id,
                'name' => $store->user?->name,
                'email' => $store->user?->email,
                'image' => null,
            ],
        ];
    }
}
