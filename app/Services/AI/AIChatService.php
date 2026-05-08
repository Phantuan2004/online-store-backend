<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Exception;

class AIChatService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected ProductSearchService $productSearchService,
        protected PromptBuilderService $promptBuilderService
    ) {}

    /**
     * Handle the full AI chat workflow
     * 
     * @param string $message
     * @return array
     */
    public function chat(string $message): array
    {
        try {
            // 1. Search products from database
            $products = $this->productSearchService->search($message);

            // 2. Build the prompt
            $prompt = $this->promptBuilderService->build($message, $products);

            // 3. Get response from Gemini
            $aiResponse = $this->geminiService->generateResponse($prompt);

            // 4. Log the interaction
            Log::info('AI Chat Interaction', [
                'user_message' => $message,
                'ai_response' => $aiResponse,
                'products_found' => $products->pluck('id')->toArray()
            ]);

            // 5. Return structured response
            return [
                'message' => $aiResponse,
                'products' => $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'image' => $product->image->url ?? null,
                        'slug' => \Illuminate\Support\Str::slug($product->name),
                    ];
                })
            ];

        } catch (Exception $e) {
            Log::error('AIChatService Error: ' . $e->getMessage());
            
            return [
                'message' => "I'm sorry, I'm having a bit of trouble connecting to my brain right now. " . 
                            "But I can still help you! Are you looking for something specific?",
                'products' => [],
                'error' => true
            ];
        }
    }
}
