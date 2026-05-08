<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIChatController extends Controller
{
    public function __construct(
        protected AIChatService $aiChatService
    ) {}

    /**
     * Handle AI Chat requests
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation error',
                'messages' => $validator->errors()
            ], 422);
        }

        $response = $this->aiChatService->chat($request->message);

        return response()->json($response);
    }
}
