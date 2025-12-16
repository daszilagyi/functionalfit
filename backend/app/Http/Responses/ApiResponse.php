<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    public static function error(
        string $message,
        mixed $errors = null,
        int $code = 400
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    public static function created(mixed $data, ?string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    public static function conflict(string $message, array $details = []): JsonResponse
    {
        return self::error($message, $details, 409);
    }

    public static function forbidden(string $message = 'Insufficient permissions'): JsonResponse
    {
        return self::error($message, null, 403);
    }

    public static function locked(string $message = 'Resource is locked'): JsonResponse
    {
        return self::error($message, null, 423);
    }

    public static function unprocessable(string $message, array $errors): JsonResponse
    {
        return self::error($message, $errors, 422);
    }
}
