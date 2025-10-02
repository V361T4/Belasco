<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // GET /api/cart
    public function index()
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $cart->load(['items.product']);
        return response()->json($cart);
    }

    // POST /api/cart { product_id, quantity }
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        // Check stock now to give earlier feedback
        $product = Product::findOrFail($data['product_id']);
        if ($product->stock < $data['quantity']) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        // Upsert item (increase quantity if exists)
        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($item) {
            $newQty = $item->quantity + $data['quantity'];
            if ($product->stock < $newQty) {
                return response()->json(['message' => 'Insufficient stock'], 422);
            }
            $item->update(['quantity' => $newQty]);
        } else {
            $item = CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $data['product_id'],
                'quantity'   => $data['quantity'],
            ]);
        }

        return response()->json($item->load('product'), 201);
    }

    // PUT /api/cart/{id} { quantity }
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = CartItem::where('id', $id)
            ->whereHas('cart', fn($q) => $q->where('user_id', Auth::id()))
            ->with('product')
            ->firstOrFail();

        if ($item->product->stock < $data['quantity']) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        $item->update(['quantity' => $data['quantity']]);

        return response()->json($item->load('product'));
    }

    // DELETE /api/cart/{id}
    public function destroy($id)
    {
        $item = CartItem::where('id', $id)
            ->whereHas('cart', fn($q) => $q->where('user_id', Auth::id()))
            ->firstOrFail();

        $item->delete();

        return response()->json(['message' => 'Item removed']);
    }
}