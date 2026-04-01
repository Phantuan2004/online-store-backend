<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    use \App\Traits\PriceFormatter;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => (float) $this->variant->price,
            'price_formatted' => $this->formatPrice($this->variant->price),
            'subtotal' => (float) $this->subtotal,
            'subtotal_formatted' => $this->formatPrice($this->subtotal),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
