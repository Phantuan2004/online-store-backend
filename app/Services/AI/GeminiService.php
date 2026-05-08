<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * Send a prompt to Gemini API
     * 
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    public function generateResponse(string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                throw new Exception('AI Service is currently unavailable. Please try again later.');
            }

            $data = $response->json();
            
            // Extract the text from response
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($text)) {
                Log::warning('Gemini returned empty response', ['response' => $data]);
                throw new Exception('I couldn\'t generate a response. Please try again.');
            }

            // Log token usage if available (Gemini v1beta returns usageMetadata)
            if (isset($data['usageMetadata'])) {
                Log::info('Gemini Token Usage', $data['usageMetadata']);
            }

            return $text;

        } catch (Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
