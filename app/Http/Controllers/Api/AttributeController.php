<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\StoreAttributeValueRequest;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\AttributeValueResource;

class AttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::with('values')->get();
        return AttributeResource::collection($attributes);
    }

    public function store(StoreAttributeRequest $request)
    {
        $attribute = Attribute::create($request->validated());
        $attribute->load('values');
        
        return new AttributeResource($attribute);
    }

    public function addValue(StoreAttributeValueRequest $request, Attribute $attribute)
    {
        $value = $attribute->values()->create($request->validated());
        return new AttributeValueResource($value);
    }
}
