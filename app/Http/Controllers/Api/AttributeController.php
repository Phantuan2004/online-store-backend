<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\UpdateAttributeRequest;
use Illuminate\Http\JsonResponse;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $attributes = Attribute::with('values')->get();

        return response()->json([
            'success' => true,
            'data' => $attributes,
            'message' => 'Danh sách thuộc tính retrieved successfully'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        try {
            $attribute = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                $attribute = Attribute::create(['name' => $request->name]);

                if ($request->has('values') && is_array($request->values)) {
                    foreach ($request->values as $value) {
                        $attribute->values()->create(['value' => $value]);
                    }
                }

                return $attribute->load('values');
            });

            return response()->json([
                'success' => true,
                'data' => $attribute,
                'message' => 'Tạo thuộc tính thành công'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo thuộc tính: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute): JsonResponse
    {
        $attribute->load('values');

        return response()->json([
            'success' => true,
            'data' => $attribute,
            'message' => 'Chi tiết thuộc tính retrieved successfully'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $attribute->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $attribute,
            'message' => 'Cập nhật thuộc tính thành công'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        // Business rule: Prevent deleting attribute if it is being used in attribute_values
        if ($attribute->values()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xoá thuộc tính này vì đang có các giá trị liên kết.'
            ], 422);
        }

        $attribute->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá thuộc tính thành công'
        ], 200);
    }
}
