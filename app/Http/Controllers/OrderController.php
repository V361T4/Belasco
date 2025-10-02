<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // POST /api/checkout
    public function store()
    {
        $cart = Cart::where('user_id', Auth::id())
            ->with(['items.product'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        // Validate stock again & compute total
        $total = 0;
        foreach ($cart->items as $ci) {
            if ($ci->product->stock < $ci->quantity) {
                return response()->json([
                    'message' => "Insufficient stock for {$ci->product->name}"
                ], 422);
            }
            $total += ($ci->product->price * $ci->quantity);
        }

        $order = DB::transaction(function () use ($cart, $total) {
            $order = Order::create([
                'user_id' => Auth::id(),
                'status'  => 'pending',
                'total'   => $total,
            ]);

            foreach ($cart->items as $ci) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $ci->product_id,
                    'quantity'   => $ci->quantity,
                    'price'      => $ci->product->price, // snapshot
                ]);

                // decrement stock
                $ci->product()->decrement('stock', $ci->quantity);
            }

            // clear cart
            $cart->items()->delete();

            return $order;
        });

        return response()->json($order->load('items.product'), 201);
    }

    // GET /api/orders
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json($orders);
    }
}