<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::where('name', 'Ốp iPhone')->first();

        // Khởi tạo các thuộc tính Attribute (model, color)
        $attrModel = Attribute::firstOrCreate(['name' => 'model']);
        $attrColor = Attribute::firstOrCreate(['name' => 'color']);

        // Khởi tạo các giá trị trị cho Model
        $modelIp13 = $attrModel->values()->firstOrCreate(['value' => 'iPhone 13']);
        $modelIp14 = $attrModel->values()->firstOrCreate(['value' => 'iPhone 14']);

        // Khởi tạo các màu sắc
        $colorBlack = $attrColor->values()->firstOrCreate(['value' => 'đen']);
        $colorRed = $attrColor->values()->firstOrCreate(['value' => 'đỏ']);

        $products = [
            ['name' => 'Ốp chống sốc iPhone', 'price' => 150000, 'category_id' => $category->id],
            ['name' => 'Ốp trong suốt',       'price' => 80000,  'category_id' => $category->id],
            ['name' => 'Ốp lưng hoạt hình',   'price' => 120000, 'category_id' => $category->id],
        ];

        foreach ($products as $pData) {
            $product = Product::updateOrCreate(
                ['name' => $pData['name']],
                ['price' => $pData['price'], 'category_id' => $pData['category_id'], 'description' => 'Sản phẩm ' . $pData['name']]
            );

            // 1. Attach Media to Product (1 primary, 2 phụ)
            $product->images()->delete(); // reset
            $product->images()->create(['url' => 'https://via.placeholder.com/300?text=Primary', 'is_primary' => true]);
            $product->images()->create(['url' => 'https://via.placeholder.com/300?text=Sub1', 'is_primary' => false]);
            $product->images()->create(['url' => 'https://via.placeholder.com/300?text=Sub2', 'is_primary' => false]);

            // 2. Tạo 2 Variants cho Product này
            $variantsInput = [
                [
                    'sku' => strtoupper(Str::slug($product->name)) . '-IP13-BLK',
                    'price' => $product->price,
                    'stock' => 50,
                    'attributes' => [$modelIp13->id, $colorBlack->id]
                ],
                [
                    'sku' => strtoupper(Str::slug($product->name)) . '-IP14-RED',
                    'price' => $product->price + 20000, // Thường ip đời mới mắc hơn
                    'stock' => 100,
                    'attributes' => [$modelIp14->id, $colorRed->id]
                ],
            ];

            foreach ($variantsInput as $vData) {
                $variant = $product->variants()->updateOrCreate(
                    ['sku' => $vData['sku']],
                    ['price' => $vData['price'], 'stock' => $vData['stock']]
                );

                // Gán thuộc tính (sync attributes)
                $variant->attributeValues()->sync($vData['attributes']);

                // Gán Media riêng cho Variant (1 ảnh riêng)
                $variant->images()->delete();
                $variant->images()->create(['url' => 'https://via.placeholder.com/300?text=' . $vData['sku'], 'is_primary' => true]);
            }
        }
    }
}
