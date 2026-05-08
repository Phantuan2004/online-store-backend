<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

class PromptBuilderService
{
    /**
     * Build the system prompt and context
     * 
     * @param string $userMessage
     * @param Collection $products
     * @return string
     */
    public function build(string $userMessage, Collection $products): string
    {
        $systemPrompt = $this->getSystemPrompt();
        $context = $this->formatProductContext($products);
        
        return "{$systemPrompt}\n\nAvailable Products Context:\n{$context}\n\nUser Question: {$userMessage}\n\nAssistant Response:";
    }

    /**
     * Define the system instructions
     */
    protected function getSystemPrompt(): string
    {
        return <<<EOT
You are a helpful and professional AI Shopping Assistant for our ecommerce store.
Your goal is to help users find products, get recommendations, and compare items.

RULES:
1. ONLY recommend products from the "Available Products Context" provided below.
2. NEVER invent products or features that are not in the context.
3. If no suitable products are found in the context, politely say so and ask if they'd like to see other categories.
4. Keep your responses concise, friendly, and focused on helping the user buy.
5. Do NOT use markdown for product links, just describe them or refer to them by name. The system will handle product cards separately.
6. If the user asks general ecommerce questions (shipping, returns), answer them generally but always try to pivot back to products.
7. Avoid long generic AI introductions. Get straight to the point.
EOT;
    }

    /**
     * Format product data into a readable string for the AI
     */
    protected function formatProductContext(Collection $products): string
    {
        if ($products->isEmpty()) {
            return "No products currently matching the user's request in our database.";
        }

        $context = "";
        foreach ($products as $product) {
            $totalStock = $product->variants->sum('stock');
            $context .= "- ID: {$product->id}\n";
            $context .= "  Name: {$product->name}\n";
            $context .= "  Price: $" . number_format($product->price, 2) . "\n";
            $context .= "  Category: " . ($product->category->name ?? 'Uncategorized') . "\n";
            $context .= "  Description: " . strip_tags($product->description) . "\n";
            $context .= "  Status: " . ($totalStock > 0 ? 'In Stock (' . $totalStock . ' units)' : 'Out of Stock') . "\n\n";
        }

        return $context;
    }
}
