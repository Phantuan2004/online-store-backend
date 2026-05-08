<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductSearchService
{
    /**
     * Search products based on user message
     * 
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function search(string $query, int $limit = 5): Collection
    {
        // Simple keyword-based search for demonstration
        // In production, consider using Laravel Scout or full-text search
        $keywords = explode(' ', $query);
        
        $products = Product::query()
            ->with(['category', 'image', 'variants'])
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) < 3) continue;
                    
                    $q->orWhere('name', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%")
                      ->orWhereHas('category', function ($catQuery) use ($keyword) {
                          $catQuery->where('name', 'like', "%{$keyword}%");
                      });
                }
            })
            ->limit($limit)
            ->get();

        return $products;
    }
}
