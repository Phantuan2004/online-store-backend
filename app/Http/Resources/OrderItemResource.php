<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'product_name' => $this->variant->product->name ?? null,
            'sku' => $this->variant->sku ?? null,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
