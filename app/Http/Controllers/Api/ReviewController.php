<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        $reviews = $product->reviews()->with('user')->paginate(10);
        return ReviewResource::collection($reviews);
    }

    public function store(StoreReviewRequest $request)
    {
        $userId = $request->user()->id;
        $productId = $request->validated('product_id');

        // Check if user already reviewed
        $existingReview = \App\Models\Review::where('product_id', $productId)
            ->where('user_id', $userId)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this product'], 400);
        }

        $review = \App\Models\Review::create([
            'product_id' => $productId,
            'user_id' => $userId,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return new ReviewResource($review);
    }
}
