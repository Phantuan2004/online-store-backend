<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['images', 'image', 'variants.image', 'variants.attributeValues.attribute', 'category'])->paginate(12);
        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load(['images', 'image', 'variants.image', 'variants.attributeValues.attribute', 'category']);
        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. Create base product
            $product = Product::create($request->only(['name', 'description', 'price', 'category_id']));

            // 2. Handle product images
            if ($request->has('images')) {
                foreach ($request->validated('images') as $index => $imageUrl) {
                    $product->images()->create([
                        'url' => $imageUrl,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            // 3. Pre-process attributes and values to get IDs
            $valueIdsMap = [];
            if ($request->has('attributes')) {
                foreach ($request->validated('attributes') as $attrData) {
                    $attribute = Attribute::firstOrCreate(['name' => $attrData['name']]);
                    foreach ($attrData['values'] as $valueName) {
                        $value = $attribute->values()->firstOrCreate(['value' => $valueName]);
                        $valueIdsMap[$attrData['name']][$valueName] = $value->id;
                    }
                }
            }

            // 4. Handle Variants
            $variants = $request->validated('variants');
            if (is_array($variants)) {
                foreach ($variants as $variantData) {
                    $variant = $product->variants()->create([
                        'sku' => $variantData['sku'],
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                    ]);

                    if (isset($variantData['image'])) {
                        $variant->images()->create([
                            'url' => $variantData['image'],
                            'is_primary' => true,
                        ]);
                    }

                    // Collect attribute value IDs for this variant
                    $variantValueIds = $variantData['attribute_value_ids'] ?? [];

                    // If string-based attributes are provided, map them to IDs
                    if (isset($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $name => $value) {
                            if (isset($valueIdsMap[$name][$value])) {
                                $variantValueIds[] = $valueIdsMap[$name][$value];
                            } else {
                                // Fallback lookup if not in top-level attributes map
                                $attr = Attribute::where('name', $name)->first();
                                if ($attr) {
                                    $val = $attr->values()->where('value', $value)->first();
                                    if ($val) $variantValueIds[] = $val->id;
                                }
                            }
                        }
                    }

                    if (!empty($variantValueIds)) {
                        $variant->attributeValues()->attach($variantValueIds);
                    }
                }

                // Force sync product price one last time after all variants are created
                $product->price = $product->variants()->min('price') ?? $product->price;
                $product->save();
            }

            $product->load(['images', 'image', 'variants.image', 'variants.attributeValues.attribute', 'category']);
            return new ProductResource($product);
        });
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $product->update($request->only(['name', 'description', 'price', 'category_id']));

            if ($request->has('images')) {
                $product->images()->delete(); // Reset images for simplicity
                foreach ($request->validated('images') as $index => $imageUrl) {
                    $product->images()->create([
                        'url' => $imageUrl,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            // Pre-process attributes and values to get IDs
            $valueIdsMap = [];
            if ($request->has('attributes')) {
                foreach ($request->validated('attributes') as $attrData) {
                    $attribute = Attribute::firstOrCreate(['name' => $attrData['name']]);
                    foreach ($attrData['values'] as $valueName) {
                        $value = $attribute->values()->firstOrCreate(['value' => $valueName]);
                        $valueIdsMap[$attrData['name']][$valueName] = $value->id;
                    }
                }
            }

            if ($request->has('variants')) {
                foreach ($request->validated('variants') as $variantData) {
                    // 1. Cố gắng tìm biến thể theo ID (nếu có) hoặc tìm theo SKU (vì SKU là duy nhất)
                    $variant = $product->variants()
                        ->where('id', $variantData['id'] ?? null)
                        ->orWhere('sku', $variantData['sku'])
                        ->first();
                    if ($variant) {
                        // 2. Nếu tìm thấy thì Cập nhật
                        $variant->update([
                            'sku' => $variantData['sku'],
                            'price' => $variantData['price'],
                            'stock' => $variantData['stock'],
                        ]);
                    } else {
                        // 3. Nếu thực sự không có ID lẫn SKU này thì mới Tạo mới
                        $variant = $product->variants()->create([
                            'sku' => $variantData['sku'],
                            'price' => $variantData['price'],
                            'stock' => $variantData['stock'],
                        ]);
                    }

                    if (isset($variantData['image'])) {
                        $variant->images()->delete();
                        $variant->images()->create([
                            'url' => $variantData['image'],
                            'is_primary' => true,
                        ]);
                    }

                    // Collect attribute value IDs for this variant
                    $variantValueIds = $variantData['attribute_value_ids'] ?? [];

                    // Map string-based attributes
                    if (isset($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $name => $value) {
                            if (isset($valueIdsMap[$name][$value])) {
                                $variantValueIds[] = $valueIdsMap[$name][$value];
                            } else {
                                $attr = Attribute::where('name', $name)->first();
                                if ($attr) {
                                    $val = $attr->values()->where('value', $value)->first();
                                    if ($val) $variantValueIds[] = $val->id;
                                }
                            }
                        }
                    }

                    if (!empty($variantValueIds)) {
                        $variant->attributeValues()->sync($variantValueIds);
                    }
                }
            }

            $product->load(['images', 'image', 'variants.image', 'variants.attributeValues.attribute', 'category']);
            return new ProductResource($product);
        });
    }

    public function destroy(Product $product)
    {
        $product->delete(); // Soft deletes if enabled. Morph relations might need manual deletion if needed or rely on cascading.
        return response()->json([
            'message' => 'Product deleted successfully',
        ], Response::HTTP_OK);
    }
}
