<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'sku', 'price', 'stock'];

    protected static function booted()
    {
        // Tự động cập nhật giá sản phẩm gốc khi biến thể thay đổi
        static::saved(function ($variant) {
            $variant->syncProductPrice();
        });

        static::deleted(function ($variant) {
            $variant->syncProductPrice();
        });
    }

    /**
     * Đồng bộ giá thấp nhất của biến thể vào giá của sản phẩm gốc
     */
    public function syncProductPrice()
    {
        if ($this->product) {
            $minPrice = $this->product->variants()->min('price');
            if ($minPrice !== null) {
                $this->product->update(['price' => $minPrice]);
            }
        }
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant', 'product_variant_id', 'attribute_value_id');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->where('is_primary', true);
    }

    public static function findByAttributes(int $productId, array $attributeValueIds): ?self
    {
        return self::where('product_id', $productId)
            ->whereHas('attributeValues', function ($query) use ($attributeValueIds) {
                $query->whereIn('attribute_value_id', $attributeValueIds);
            }, '=', count($attributeValueIds))
            ->first();
    }
}
