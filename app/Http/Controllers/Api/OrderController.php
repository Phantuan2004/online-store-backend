<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->orders()
            ->with(['items.variant.product', 'payment', 'addresses']);

        // Lọc theo trạng thái (status)
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                // Đang hoạt động: Chờ xử lý, Đã thanh toán, Đang giao
                $query->whereIn('status', ['pending', 'paid', 'shipped']);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->latest()->paginate(10);
            
        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load(['items.variant.product', 'payment', 'addresses']);
        return new OrderResource($order);
    }

    public function store(CreateOrderRequest $request, \App\Services\VNPayService $vnpayService)
    {
        // ... (existing code remains or is refined)
        $cart = Cart::where('user_id', $request->user()->id)->with('items.variant.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng của bạn đang trống.'], 400);
        }

        try {
            return DB::transaction(function () use ($request, $cart, $vnpayService) {
                // 1. Tính tổng tiền
                $totalPrice = $cart->items->sum(function ($item) {
                    return $item->variant->price * $item->quantity;
                });

                // 2. Tạo Đơn hàng
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                ]);

                // 3. Order Items & Trừ kho
                foreach ($cart->items as $item) {
                    if ($item->quantity > $item->variant->stock) {
                        throw new \Exception("Sản phẩm '" . $item->variant->product->name . "' đã hết hàng.");
                    }

                    $order->items()->create([
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'price' => $item->variant->price,
                    ]);

                    $item->variant->decrement('stock', $item->quantity);
                }

                // 4. Địa chỉ & Thanh toán
                $order->addresses()->attach($request->validated('address_id'));

                $paymentMethod = $request->validated('payment_method');
                $order->payment()->create([
                    'method' => $paymentMethod,
                    'status' => 'pending',
                ]);

                // 5. Cleanup
                $cart->items()->delete();

                $order->load(['items.variant.product', 'payment', 'addresses']);
                $resource = new OrderResource($order);

                if ($paymentMethod === 'vnpay') {
                    $paymentUrl = $vnpayService->createPaymentUrl($order);
                    return $resource->additional([
                        'message' => 'Khởi tạo đơn hàng thành công.',
                        'payment_url' => $paymentUrl
                    ]);
                }

                return $resource->additional(['message' => 'Đặt hàng thành công (COD).']);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi xử lý đơn hàng.',
                'errors' => ['checkout' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Hủy đơn hàng và hoàn lại tồn kho.
     */
    public function cancel(Request $request, Order $order)
    {
        // 1. Kiểm tra quyền sở hữu
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        // 2. Kiểm tra điều kiện hủy (Chỉ cho phép khi đang 'pending')
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Chờ xử lý (pending).'
            ], 422);
        }

        return DB::transaction(function () use ($order) {
            // 3. Cập nhật trạng thái Order sang 'cancelled'
            $order->update(['status' => 'cancelled']);

            // 4. Hoàn lại số lượng tồn kho cho từng biến thể sản phẩm
            foreach ($order->items as $item) {
                $item->variant->increment('stock', $item->quantity);
            }

            // 5. Cập nhật trạng thái thanh toán (nếu có)
            if ($order->payment) {
                $order->payment->update(['status' => 'failed']); // Hoặc giữ 'pending' nhưng mark là 'cancelled'
            }

            return response()->json([
                'message' => 'Hủy đơn hàng thành công. Sản phẩm đã được hoàn lại vào kho.'
            ]);
        });
    }
}
