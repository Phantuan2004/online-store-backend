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
        $variantId = $request->validated('variant_id');
        $quantityToAdd = (int) $request->validated('quantity');

        $variant = \App\Models\ProductVariant::findOrFail($variantId);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $cartItem = $cart->items()->where('product_variant_id', $variantId)->first();
        $currentQuantity = $cartItem ? $cartItem->quantity : 0;
        $totalQuantity = $currentQuantity + $quantityToAdd;

        if ($totalQuantity > $variant->stock) {
            return response()->json([
                'message' => 'Rất tiếc, số lượng vượt quá tồn kho hiện có.',
                'errors' => [
                    'quantity' => ['Số lượng tồn kho chỉ còn: ' . $variant->stock]
                ]
            ], 422);
        }

        if ($cartItem) {
            $cartItem->update(['quantity' => $totalQuantity]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variantId,
                'quantity' => $quantityToAdd
            ]);
        }

        $cart->load(['items.variant.product', 'items.variant.attributeValues.attribute']);
        return new CartResource($cart);
    }

    public function updateQuantity(UpdateCartRequest $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        $newQuantity = (int) $request->validated('quantity');

        if ($newQuantity > $cartItem->variant->stock) {
            return response()->json([
                'message' => 'Rất tiếc, số lượng cập nhật vượt quá tồn kho.',
                'errors' => [
                    'quantity' => ['Số lượng tồn kho chỉ còn: ' . $cartItem->variant->stock]
                ]
            ], 422);
        }

        $cartItem->update(['quantity' => $newQuantity]);

        // Cập nhật lại thông tin giỏ hàng để hiển thị đúng tổng tiền
        $cart = $cartItem->cart->load(['items.variant.product', 'items.variant.attributeValues.attribute']);

        return (new CartResource($cart))->additional(['message' => 'Cập nhật số lượng thành công']);
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403);
        }

        $cartItem->delete();
        return response()->json(['message' => 'Đã xóa sản phẩm khỏi giỏ hàng'], Response::HTTP_OK);
    }

    public function clearCart(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Đã làm trống giỏ hàng']);
    }
}
