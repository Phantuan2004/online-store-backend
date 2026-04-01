<?php

namespace App\Services;

class VNPayService
{
    protected $vnp_TmnCode;
    protected $vnp_HashSecret;
    protected $vnp_Url;
    protected $vnp_Returnurl;

    public function __construct()
    {
        $config = config('services.vnpay');

        $this->vnp_TmnCode = $config['tmn_code'] ?? '2QXG2YLS';
        $this->vnp_HashSecret = $config['hash_secret'] ?? '9B9LNS64I9E3WBSV24A587A1Y8Q53T8J';
        $this->vnp_Url = $config['url'] ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

        // Trong thực tế, URL này nên trỏ về Frontend route xử lý kết quả
        $this->vnp_Returnurl = url('/api/payments/callback');
    }

    /**
     * Tạo URL thanh toán VNPay
     *
     * @param \App\Models\Order $order
     * @return string
     */
    public function createPaymentUrl($order): string
    {
        $vnp_TxnRef = $order->id;
        $vnp_OrderInfo = "Thanh toan don hang #" . $order->id;
        $vnp_OrderType = "billpayment";
        $vnp_Amount = $order->total_price * 100; // VNPay tính theo đơn vị VNĐ * 100
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip();

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $this->vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $this->vnp_Url . "?" . $query;
        if (isset($this->vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return $vnp_Url;
    }
}
