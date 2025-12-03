<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // GET /api/orders — List orders for the authenticated user
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                $order->items_details = $this->getItemsDetails($order->items);
                return $order;
            });

        return response()->json($orders);
    }

    // GET /api/orders/{id} — Show a specific order
    public function show(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $order->items_details = $this->getItemsDetails($order->items);

        return response()->json($order);
    }

    // POST /api/orders — Create a new order
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'subtotal' => 'required|numeric',
            'delivery_fee' => 'required|numeric',
            'total' => 'required|numeric',
            'payment_method' => 'required|string',
            'delivery_first_name' => 'required|string',
            'delivery_last_name' => 'nullable|string',
            'delivery_email' => 'required|email',
            'delivery_phone' => 'nullable|string',
            'delivery_address' => 'required|string',
            'delivery_city' => 'required|string',
            'delivery_state' => 'nullable|string',
            'delivery_zip' => 'nullable|string',
            'delivery_country' => 'nullable|string',
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'items' => $validated['items'],
            'subtotal' => $validated['subtotal'],
            'delivery_fee' => $validated['delivery_fee'],
            'total' => $validated['total'],
            'payment_method' => $validated['payment_method'],
            'delivery_first_name' => $validated['delivery_first_name'],
            'delivery_last_name' => $validated['delivery_last_name'] ?? null,
            'delivery_email' => $validated['delivery_email'],
            'delivery_phone' => $validated['delivery_phone'] ?? null,
            'delivery_address' => $validated['delivery_address'],
            'delivery_city' => $validated['delivery_city'],
            'delivery_state' => $validated['delivery_state'] ?? null,
            'delivery_zip' => $validated['delivery_zip'] ?? null,
            'delivery_country' => $validated['delivery_country'] ?? null,
        ]);

        // Attach item details to the response
        $order->items_details = $this->getItemsDetails($order->items);

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order
        ], 201);
    }

    // Helper — Convert raw items into detailed product data
    private function getItemsDetails($items)
    {
        // Convert to an array if we received a JSON string
        $itemsArray = is_string($items) ? json_decode($items, true) : $items;
        $itemsDetails = [];

        if (!is_array($itemsArray)) {
            return $itemsDetails;
        }

        foreach ($itemsArray as $productId => $sizes) {
            $product = Product::find($productId);

            if (!is_array($sizes)) {
                continue;
            }

            foreach ($sizes as $size => $quantity) {
                $price = $product ? (float) $product->price : 0;
                $qty = (int) $quantity;

                $itemsDetails[] = [
                    'product_id' => (int) $productId,
                    'name' => $product ? $product->name : "Product #$productId",
                    'price' => $price,
                    'size' => $size,
                    'quantity' => $qty,
                    'subtotal' => $price * $qty,
                    'image' => $product && $product->image_urls ? ($product->image_urls[0] ?? null) : null,
                ];
            }
        }

        return $itemsDetails;
    }
}
