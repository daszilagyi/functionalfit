<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\RegisterQuickRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'role' => 'client', // Default role for self-registration
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'status' => 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::created([
            'user' => $user,
            'token' => $token,
        ], 'Registration successful');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();

        if ($user->status !== 'active') {
            return ApiResponse::forbidden('Account is not active');
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user->load(['staffProfile', 'client']),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['staffProfile', 'client']);

        return ApiResponse::success($user);
    }

    /**
     * Quick registration - creates both User and Client in one transaction
     * Designed for quick client onboarding without password confirmation
     *
     * POST /api/v1/auth/register-quick
     */
    public function registerQuick(RegisterQuickRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create User account
            $user = User::create([
                'role' => 'client',
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password')),
                'status' => 'active',
            ]);

            // Create associated Client record
            $client = Client::create([
                'user_id' => $user->id,
                'full_name' => $request->input('name'),
                'date_of_joining' => now(),
                'gdpr_consent_at' => now(),
            ]);

            DB::commit();

            // Auto-login: create Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Eager load the client relationship
            $user->load('client');

            return ApiResponse::created([
                'user' => $user,
                'token' => $token,
            ], 'Registration successful');

        } catch (\Exception $e) {
            DB::rollBack();

            // Check if it's a unique constraint violation (duplicate email)
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                return ApiResponse::error('This email address is already registered', null, 409);
            }

            // Log unexpected errors
            \Log::error('Quick registration failed', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Registration failed. Please try again.', null, 500);
        }
    }

    /**
     * Send password reset link
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            \Log::info('Attempting to send password reset link', ['email' => $request->input('email')]);

            // Send password reset link
            $status = Password::sendResetLink(
                $request->only('email')
            );

            \Log::info('Password reset link status', ['status' => $status, 'email' => $request->input('email')]);

            // Always return success for security (don't reveal if email exists)
            // Only log actual errors
            if ($status === Password::RESET_THROTTLED) {
                return ApiResponse::error('Too many requests. Please wait before trying again.', null, 429);
            }

            return ApiResponse::success(null, 'If the email address is registered, you will receive a password reset link.');
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while sending the reset email. Please try again.', null, 500);
        }
    }

    /**
     * Reset password with token
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Password has been reset successfully.');
        }

        return ApiResponse::error('Failed to reset password. The link may have expired.', null, 400);
    }
}
