<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotivationalQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotivationalQuoteController extends Controller
{
    public function index(): JsonResponse
    {
        $quotes = MotivationalQuote::orderBy('id')->get();

        return response()->json(['data' => $quotes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:500',
        ]);

        $quote = MotivationalQuote::create($validated);

        return response()->json(['data' => $quote], 201);
    }

    public function update(Request $request, MotivationalQuote $motivationalQuote): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:500',
        ]);

        $motivationalQuote->update($validated);

        return response()->json(['data' => $motivationalQuote]);
    }

    public function destroy(MotivationalQuote $motivationalQuote): JsonResponse
    {
        $motivationalQuote->delete();

        return response()->json(null, 204);
    }
}
