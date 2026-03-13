<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function clientOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product', 'store'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->transformOrder($order));

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'address' => ['nullable', 'array'],
            'payment_method' => ['nullable', 'string', 'max:30'],
        ]);

        $items = $validated['items'];
        $paymentMethod = $validated['payment_method'] ?? 'COD';
        $address = $validated['address'] ?? null;

        $products = Product::with('store')
            ->whereIn('id', collect($items)->pluck('product_id')->all())
            ->where('is_active', true)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'Aucun produit valide.',
            ], 422);
        }

        $productsByStore = [];
        foreach ($items as $item) {
            $product = $products->firstWhere('id', $item['product_id']);
            if (! $product || ! $product->store_id || ! $product->store || ! $product->store->is_active) {
                continue;
            }
            $productsByStore[$product->store_id][] = [
                'product' => $product,
                'quantity' => $item['quantity'],
            ];
        }

        $orders = [];

        DB::transaction(function () use ($productsByStore, $paymentMethod, $address, $request, &$orders) {
            foreach ($productsByStore as $storeId => $lineItems) {
                $total = 0;
                foreach ($lineItems as $line) {
                    $total += $line['product']->price * $line['quantity'];
                }

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'store_id' => $storeId,
                    'total' => $total,
                    'status' => 'ORDER_PLACED',
                    'payment_method' => $paymentMethod,
                    'is_paid' => false,
                    'address' => $address,
                ]);

                foreach ($lineItems as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'quantity' => $line['quantity'],
                        'price' => $line['product']->price,
                    ]);
                }

                $orders[] = $order;
            }
        });

        $orders = Order::with(['items.product', 'store'])
            ->whereIn('id', collect($orders)->pluck('id')->all())
            ->get()
            ->map(fn (Order $order) => $this->transformOrder($order));

        return response()->json([
            'message' => 'Commande enregistree.',
            'data' => $orders,
        ], 201);
    }

    public function vendorOrders(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store) {
            return response()->json([
                'message' => 'Aucune boutique associee.',
            ], 404);
        }

        $orders = Order::with(['items.product', 'user', 'store'])
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->transformOrder($order));

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store || $order->store_id !== $store->id) {
            return response()->json([
                'message' => 'Commande introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['ORDER_PLACED', 'PROCESSING', 'SHIPPED', 'DELIVERED'])],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Statut mis a jour.',
            'data' => $this->transformOrder($order->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformOrder(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'total' => (float) $order->total,
            'status' => $order->status,
            'paymentMethod' => $order->payment_method,
            'isPaid' => (bool) $order->is_paid,
            'createdAt' => $order->created_at?->toISOString(),
            'address' => $order->address,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->name,
                'username' => $order->store->username,
            ] : null,
            'user' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'orderItems' => $order->items->map(function (OrderItem $item) {
                $images = $item->product ? array_map(fn ($path) => Storage::disk('public')->url($path), $item->product->images ?? []) : [];
                return [
                    'productId' => (string) $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'product' => $item->product ? [
                        'id' => (string) $item->product->id,
                        'name' => $item->product->name,
                        'images' => $images,
                    ] : null,
                ];
            })->values(),
        ];
    }
}
