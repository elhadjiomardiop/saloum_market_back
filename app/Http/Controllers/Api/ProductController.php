<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('store', fn ($q) => $q->where('is_active', true))
            ->with('store')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product));

        return response()->json([
            'data' => $products,
            'meta' => [
                'count' => $products->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with('store')->find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Produit introuvable',
            ], 404);
        }

        return response()->json([
            'data' => $this->transformProduct($product),
        ]);
    }

    public function storeProducts(Request $request, string $username): JsonResponse
    {
        $store = Store::query()->where('username', $username)->first();

        if (! $store) {
            return response()->json([
                'message' => 'Boutique introuvable',
            ], 404);
        }

        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product, $store));

        return response()->json([
            'data' => $products,
            'meta' => [
                'count' => $products->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product, ?Store $store = null): array
    {
        $store = $store ?: $product->store;

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'mrp' => (float) $product->mrp,
            'price' => (float) $product->price,
            'images' => $this->formatImages($product->images ?? []),
            'inStock' => (bool) $product->is_active,
            'rating' => [],
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'username' => $store->username,
                'logo' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
            ] : null,
            'createdAt' => $product->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<int, string> $images
     * @return array<int, string>
     */
    private function formatImages(array $images): array
    {
        return array_values(array_filter(array_map(function ($path) {
            if (! $path) {
                return null;
            }
            return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
        }, $images)));
    }
}
