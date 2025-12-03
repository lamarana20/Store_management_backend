<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /api/dashboard/user — Stats pour le client
    public function userStats(Request $request)
    {
        $user = $request->user();
        
        $orders = $user->orders();
        
        return response()->json([
            'total_orders' => $orders->count(),
            'total_spent' => $orders->sum('total'),
            'pending_orders' => $orders->where('order_status', 'pending')->count(),
            'completed_orders' => $orders->where('order_status', 'completed')->count(),
            'recent_orders' => $user->orders()
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
        ]);
    }

    // GET /api/dashboard/admin — Stats pour l'admin
    public function adminStats(Request $request)
    {
        return response()->json([
            // Users
            'total_users' => User::where('role', 'user')->count(),
            'new_users_this_month' => User::where('role', 'user')
                ->whereMonth('created_at', now()->month)
                ->count(),

            // Orders
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('order_status', 'pending')->count(),
            'completed_orders' => Order::where('order_status', 'completed')->count(),
            'orders_this_month' => Order::whereMonth('created_at', now()->month)->count(),

            // Revenue
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'revenue_this_month' => Order::where('payment_status', 'paid')
                ->whereMonth('created_at', now()->month)
                ->sum('total'),

            // Products
            'total_products' => Product::count(),
            'low_stock_products' => Product::where('stock_quantity', '<', 10)->count(),

            // Recent
            'recent_orders' => Order::with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
        ]);
    }
}