<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store) {
            return response()->json([
                'message' => 'Aucune boutique associee.',
            ], 404);
        }

        $products = Product::where('store_id', $store->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product, $store));

        return response()->json([
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store) {
            return response()->json([
                'message' => 'Aucune boutique associee.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'mrp' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'images' => ['nullable'],
            'images.*' => ['image', 'max:4096'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $images = $this->storeImages($request);

        if (empty($images)) {
            return response()->json([
                'message' => 'Au moins une image est requise.',
            ], 422);
        }

        $product = Product::create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'mrp' => $validated['mrp'],
            'price' => $validated['price'],
            'images' => $images,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Produit ajoute.',
            'data' => $this->transformProduct($product, $store),
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store || $product->store_id !== $store->id) {
            return response()->json([
                'message' => 'Produit introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mrp' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['nullable'],
            'images.*' => ['image', 'max:4096'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $images = $this->storeImages($request);

        if (! empty($images)) {
            foreach (($product->images ?? []) as $oldImage) {
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            $product->images = $images;
        }

        unset($validated['images']);
        $product->fill($validated);
        $product->save();

        return response()->json([
            'message' => 'Produit mis a jour.',
            'data' => $this->transformProduct($product->fresh(), $store),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store || $product->store_id !== $store->id) {
            return response()->json([
                'message' => 'Produit introuvable.',
            ], 404);
        }

        foreach (($product->images ?? []) as $oldImage) {
            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Produit supprime.',
        ]);
    }

    public function toggleActive(Request $request, Product $product): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store || $product->store_id !== $store->id) {
            return response()->json([
                'message' => 'Produit introuvable.',
            ], 404);
        }

        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return response()->json([
            'message' => 'Statut du produit mis a jour.',
            'data' => $this->transformProduct($product->fresh(), $store),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function storeImages(Request $request): array
    {
        $images = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products/images', 'public');
            }
        }

        if ($request->hasFile('image')) {
            $images[] = $request->file('image')->store('products/images', 'public');
        }

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product, Store $store): array
    {
        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'mrp' => (float) $product->mrp,
            'price' => (float) $product->price,
            'images' => array_map(fn ($path) => Storage::disk('public')->url($path), $product->images ?? []),
            'inStock' => (bool) $product->is_active,
            'rating' => [],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'username' => $store->username,
                'logo' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
            ],
            'createdAt' => $product->created_at?->toISOString(),
        ];
    }
}
