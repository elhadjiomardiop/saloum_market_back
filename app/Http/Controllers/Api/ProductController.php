<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'id' => 'prod_1',
                'name' => 'Modern table lamp',
                'description' => 'Modern table lamp with a sleek design.',
                'category' => 'Decoration',
                'mrp' => 40,
                'price' => 29,
                'inStock' => true,
                'ratingCount' => 6,
            ],
            [
                'id' => 'prod_2',
                'name' => 'Smart speaker gray',
                'description' => 'Smart speaker with a sleek design.',
                'category' => 'Speakers',
                'mrp' => 50,
                'price' => 29,
                'inStock' => true,
                'ratingCount' => 6,
            ],
            [
                'id' => 'prod_3',
                'name' => 'Wireless headphones',
                'description' => 'Wireless headphones with a sleek design.',
                'category' => 'Headphones',
                'mrp' => 70,
                'price' => 29,
                'inStock' => true,
                'ratingCount' => 6,
            ],
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->products(),
            'meta' => [
                'count' => count($this->products()),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $product = collect($this->products())->firstWhere('id', $id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'data' => $product,
        ]);
    }
}
