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

        $rememberMe = $request->boolean('remember_me', false);

        return $this->respondWithTokens($user, 200, $rememberMe);
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

    protected function respondWithTokens(User $user, $status = 200, bool $rememberMe = false)
    {
        // 1. Tạo Access Token (Ngắn hạn - 30 phút) - luôn cấp
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(30));

        $responseData = [
            'user'         => $user,
            'access_token' => $accessToken->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 30 * 60, // 30 phút tính bằng giây
        ];

        if ($rememberMe) {
            // 2. Chỉ cấp Refresh Token (Dài hạn - 30 ngày) khi remember_me = true
            $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(30));

            $cookie = cookie(
                'refresh_token',
                $refreshToken->plainTextToken,
                43200, // 30 ngày tính bằng phút
                '/',
                null,
                false, // secure - đổi thành true khi đưa lên môi trường https
                true,  // httpOnly
                false, // raw
                'Lax'  // sameSite
            );

            return response()->json($responseData, $status)->withCookie($cookie);
        }

        // Không remember: xóa refresh token cookie cũ nếu có (trường hợp re-login)
        $clearCookie = cookie('refresh_token', '', -1, '/', null, false, true, false, 'Lax');

        return response()->json($responseData, $status)->withCookie($clearCookie);
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

        // Xóa cookie Refresh Token bằng cách set thời gian hết hạn trong quá khứ
        $cookie = cookie('refresh_token', '', -1, '/', null, false, true, false, 'Lax');

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ])->withCookie($cookie);
    }
}
