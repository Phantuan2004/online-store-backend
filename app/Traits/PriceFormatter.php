<?php

namespace App\Traits;

trait PriceFormatter
{
    /**
     * Format the given price as VND.
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', '.') . ' ₫';
    }
}
