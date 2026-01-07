<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Get current user's profile data.
     *
     * GET /api/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $profileData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
        ];

        // Add client-specific fields if user is a client
        if ($user->role === 'client' && $user->client) {
            $profileData['date_of_birth'] = $user->client->date_of_birth?->format('Y-m-d');
            $profileData['emergency_contact_name'] = $user->client->emergency_contact_name;
            $profileData['emergency_contact_phone'] = $user->client->emergency_contact_phone;
        }

        // Add staff-specific fields if user is staff
        if ($user->role === 'staff' && $user->staffProfile) {
            $profileData['bio'] = $user->staffProfile->bio;
            $profileData['specialization'] = $user->staffProfile->specialization;
        }

        return ApiResponse::success($profileData);
    }

    /**
     * Update current user's profile data.
     *
     * PATCH /api/profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];

        // Add client-specific validation rules
        if ($user->role === 'client') {
            $rules['date_of_birth'] = ['nullable', 'date', 'before:today'];
            $rules['emergency_contact_name'] = ['nullable', 'string', 'max:255'];
            $rules['emergency_contact_phone'] = ['nullable', 'string', 'max:20'];
        }

        // Add staff-specific validation rules
        if ($user->role === 'staff') {
            $rules['bio'] = ['nullable', 'string', 'max:1000'];
            $rules['specialization'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        try {
            return DB::transaction(function () use ($user, $validated) {
                // Update user fields
                $userFields = [];
                if (isset($validated['name'])) {
                    $userFields['name'] = $validated['name'];
                }
                if (isset($validated['email'])) {
                    $userFields['email'] = $validated['email'];
                }
                if (array_key_exists('phone', $validated)) {
                    $userFields['phone'] = $validated['phone'];
                }

                if (!empty($userFields)) {
                    $user->update($userFields);
                }

                // Update client-specific fields
                if ($user->role === 'client' && $user->client) {
                    $clientFields = [];
                    if (isset($validated['name'])) {
                        $clientFields['full_name'] = $validated['name'];
                    }
                    if (array_key_exists('date_of_birth', $validated)) {
                        $clientFields['date_of_birth'] = $validated['date_of_birth'];
                    }
                    if (array_key_exists('emergency_contact_name', $validated)) {
                        $clientFields['emergency_contact_name'] = $validated['emergency_contact_name'];
                    }
                    if (array_key_exists('emergency_contact_phone', $validated)) {
                        $clientFields['emergency_contact_phone'] = $validated['emergency_contact_phone'];
                    }

                    if (!empty($clientFields)) {
                        $user->client->update($clientFields);
                    }
                }

                // Update staff-specific fields
                if ($user->role === 'staff' && $user->staffProfile) {
                    $staffFields = [];
                    if (array_key_exists('bio', $validated)) {
                        $staffFields['bio'] = $validated['bio'];
                    }
                    if (array_key_exists('specialization', $validated)) {
                        $staffFields['specialization'] = $validated['specialization'];
                    }

                    if (!empty($staffFields)) {
                        $user->staffProfile->update($staffFields);
                    }
                }

                $user->refresh();

                // Build response
                $profileData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ];

                if ($user->role === 'client' && $user->client) {
                    $profileData['date_of_birth'] = $user->client->date_of_birth?->format('Y-m-d');
                    $profileData['emergency_contact_name'] = $user->client->emergency_contact_name;
                    $profileData['emergency_contact_phone'] = $user->client->emergency_contact_phone;
                }

                if ($user->role === 'staff' && $user->staffProfile) {
                    $profileData['bio'] = $user->staffProfile->bio;
                    $profileData['specialization'] = $user->staffProfile->specialization;
                }

                return ApiResponse::success($profileData, 'Profil sikeresen frissítve');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Hiba a profil frissítésekor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Change current user's password.
     *
     * POST /api/profile/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error('A jelenlegi jelszó helytelen', ['current_password' => ['A jelenlegi jelszó helytelen']], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return ApiResponse::success(null, 'Jelszó sikeresen megváltoztatva');
    }
}
