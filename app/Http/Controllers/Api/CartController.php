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

        $validated = $request->validated();
        $targetVariant = $cartItem->variant;

        // 1. Xử lý đổi thuộc tính (Attribute) nếu có
        if (isset($validated['attribute_value_ids'])) {
            $newVariant = \App\Models\ProductVariant::findByAttributes(
                $cartItem->variant->product_id,
                $validated['attribute_value_ids']
            );

            if (!$newVariant) {
                return response()->json([
                    'message' => 'Rất tiếc, không tìm thấy phiên bản sản phẩm với các thuộc tính đã chọn.',
                ], 404);
            }

            // Chặn nếu biến thể mới đã có trong mục khác của giỏ hàng
            $alreadyInCart = $cartItem->cart->items()
                ->where('product_variant_id', $newVariant->id)
                ->where('id', '!=', $cartItem->id)
                ->exists();

            if ($alreadyInCart) {
                return response()->json([
                    'message' => 'Sản phẩm với bộ thuộc tính này đã tồn tại trong giỏ hàng của bạn.',
                ], 422);
            }

            $targetVariant = $newVariant;
            $cartItem->product_variant_id = $newVariant->id;
        }

        // 2. Xử lý cập nhật số lượng (mặc định lấy số lượng hiện tại nếu không truyền)
        $newQuantity = isset($validated['quantity']) ? (int) $validated['quantity'] : $cartItem->quantity;

        if ($newQuantity > $targetVariant->stock) {
            return response()->json([
                'message' => 'Rất tiếc, số lượng vượt quá tồn kho hiện có.',
                'errors' => [
                    'quantity' => ['Số lượng tồn kho chỉ còn: ' . $targetVariant->stock]
                ]
            ], 422);
        }

        $cartItem->quantity = $newQuantity;
        $cartItem->save();

        // Load lại quan hệ để trả về Resource đầy đủ thông tin nhất
        $cart = $cartItem->cart->load(['items.variant.product', 'items.variant.attributeValues.attribute']);

        return (new CartResource($cart))->additional(['message' => 'Cập nhật giỏ hàng thành công']);
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
