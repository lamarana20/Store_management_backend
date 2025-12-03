<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    // GET /api/admin/orders
    public function index(Request $request)
    {
        $query = Order::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('status') && $request->status) {
            $query->where('order_status', $request->status);
        }

        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->paginate(20);

        // Ajouter les détails des items
        $orders->getCollection()->transform(function ($order) {
            $order->items_details = $this->getItemsDetails($order->items);
            return $order;
        });

        return response()->json($orders);
    }

    // GET /api/admin/orders/{id}
    public function show($id)
    {
        $order = Order::with('user:id,name,email')->findOrFail($id);
        $order->items_details = $this->getItemsDetails($order->items);

        return response()->json($order);
    }

    // PUT /api/admin/orders/{id}
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'order_status' => 'sometimes|in:pending,processing,shipped,completed,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,refunded,failed',
        ]);

        $order->update($validated);

        // Recharger avec les relations
        $order->load('user:id,name,email');
        $order->items_details = $this->getItemsDetails($order->items);

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
        ]);
    }

    // DELETE /api/admin/orders/{id}
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }

    // Helper — Convertir items en détails avec produits
    private function getItemsDetails($items)
    {
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
                $itemsDetails[] = [
                    'product_id' => (int) $productId,
                    'name' => $product ? $product->name : "Product #$productId",
                    'price' => $product ? (float) $product->price : 0,
                    'size' => $size,
                    'quantity' => (int) $quantity,
                    'subtotal' => $product ? (float) $product->price * (int) $quantity : 0,
                    'image' => $product && $product->image_urls ? ($product->image_urls[0] ?? null) : null,
                ];
            }
        }

        return $itemsDetails;
    }
}