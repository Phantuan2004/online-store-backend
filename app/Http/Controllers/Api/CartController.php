<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Http\Resources\CartResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function getCart(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load(['items.variant.product', 'items.variant.attributeValues.attribute']);

        return new CartResource($cart);
    }

    public function addToCart(AddToCartRequest $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $cartItem = $cart->items()->updateOrCreate(
            ['product_variant_id' => $request->validated('variant_id')],
            ['quantity' => \DB::raw('quantity + ' . $request->validated('quantity'))]
        );

        $cart->load(['items.variant.product', 'items.variant.attributeValues.attribute']);
        return new CartResource($cart);
    }

    public function updateQuantity(UpdateCartRequest $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        $cartItem->update(['quantity' => $request->validated('quantity')]);

        return response()->json(['message' => 'Cart updated']);
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        $cartItem->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
