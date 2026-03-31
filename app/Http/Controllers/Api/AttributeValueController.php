<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Http\Requests\StoreAttributeValueRequest;
use Illuminate\Http\JsonResponse;

class AttributeValueController extends Controller
{
    /**
     * Store a newly created attribute value for a specific attribute.
     */
    public function store(StoreAttributeValueRequest $request, Attribute $attribute): JsonResponse
    {
        $value = $attribute->values()->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $value,
            'message' => 'Thêm giá trị mới thành công'
        ], 201);
    }

    /**
     * Remove the specified attribute value from storage.
     */
    public function destroy(AttributeValue $attributeValue): JsonResponse
    {
        // Business rule: Prevent deleting if in use by product variants
        if ($attributeValue->variants()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xoá giá trị này vì đang được sử dụng ở các Biến thể sản phẩm.'
            ], 422);
        }

        $attributeValue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá giá trị thành công'
        ], 200);
    }
}
