<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return $this->respondWithTokens($user, 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        return $this->respondWithTokens($user);
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tokenModel = PersonalAccessToken::findToken($refreshToken);

        if (!$tokenModel || $tokenModel->name !== 'refresh_token' || $tokenModel->expires_at->isPast()) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn'], 401);
        }

        $user = $tokenModel->tokenable;

        // Xoay vòng token: Xóa token cũ
        $tokenModel->delete();

        return $this->respondWithTokens($user);
    }

    protected function respondWithTokens(User $user, $status = 200)
    {
        // 1. Tạo Access Token (Ngắn hạn - 30 phút)
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(30));

        // 2. Tạo Refresh Token (Dài hạn - 30 ngày)
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(30));

        $cookie = cookie(
            'refresh_token',
            $refreshToken->plainTextToken,
            43200, // 30 ngày tính bằng phút
            null,
            null,
            false, // secure - đổi thành true khi đưa lên môi trường https
            true,  // httpOnly
            false, // raw
            'Lax'  // sameSite
        );

        return response()->json([
            'user' => $user,
            'access_token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 30 * 60, // 30 phút tính bằng giây
        ], $status)->withCookie($cookie);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        $user = auth()->user();

        // Thu hồi token hiện tại
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        // Xóa cookie Refresh Token
        $cookie = Cookie::forget('refresh_token');

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ])->withCookie($cookie);
    }
}
