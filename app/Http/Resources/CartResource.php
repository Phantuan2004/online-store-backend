<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    use \App\Traits\PriceFormatter;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total_items' => $this->total_items,
            'total_price' => (float) $this->total_price,
            'total_price_formatted' => $this->formatPrice($this->total_price),
            'items' => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
