<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSettlementRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\StaffProfile;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {
    }

    /**
     * List all settlements with optional filters.
     *
     * GET /api/v1/admin/settlements
     */
    public function index(Request $request): JsonResponse
    {
        $query = Settlement::with(['trainer', 'items'])
            ->withCount('items');

        // Filter by trainer
        if ($request->has('trainer_id')) {
            $query->where('trainer_id', $request->input('trainer_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('period_start', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('period_end', '<=', $request->input('to'));
        }

        $settlements = $query->orderByDesc('created_at')->get();

        // Transform to include trainer_name and items_count
        $transformedSettlements = $settlements->map(function ($settlement) {
            return [
                'id' => $settlement->id,
                'trainer_id' => $settlement->trainer_id,
                'trainer_name' => $settlement->trainer->name ?? 'Unknown',
                'period_start' => $settlement->period_start->toDateString(),
                'period_end' => $settlement->period_end->toDateString(),
                'total_trainer_fee' => $settlement->total_trainer_fee,
                'total_entry_fee' => $settlement->total_entry_fee,
                'status' => $settlement->status,
                'notes' => $settlement->notes,
                'items_count' => $settlement->items_count,
                'created_at' => $settlement->created_at->toIso8601String(),
                'updated_at' => $settlement->updated_at->toIso8601String(),
            ];
        });

        return ApiResponse::success($transformedSettlements);
    }

    /**
     * Preview settlement for a trainer in a given period.
     *
     * GET /api/v1/admin/settlements/preview?trainer_id={id}&from={date}&to={date}
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $userId = $validated['trainer_id'];
        $periodStart = \Carbon\Carbon::parse($validated['from'])->startOfDay();
        $periodEnd = \Carbon\Carbon::parse($validated['to'])->endOfDay();

        // Convert user_id to staff_profile_id (Event.staff_id and ClassOccurrence.trainer_id reference staff_profiles.id)
        $staffProfile = StaffProfile::where('user_id', $userId)->first();
        $staffProfileId = $staffProfile?->id ?? $userId; // Fallback to userId if no profile found

        // Calculate settlement preview
        $preview = $this->pricingService->calculateSettlementForTrainer(
            $staffProfileId,
            $periodStart,
            $periodEnd
        );

        return ApiResponse::success([
            'trainer_id' => $userId,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_trainer_fee' => $preview['total_trainer_fee'],
            'total_entry_fee' => $preview['total_entry_fee'],
            'items_count' => count($preview['items']),
            'items' => $preview['items'],
        ]);
    }

    /**
     * Generate a settlement for a trainer.
     *
     * POST /api/v1/admin/settlements/generate
     */
    public function generate(GenerateSettlementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $userId = $validated['trainer_id'];
        $periodStart = \Carbon\Carbon::parse($validated['period_start'])->startOfDay();
        $periodEnd = \Carbon\Carbon::parse($validated['period_end'])->endOfDay();

        // Convert user_id to staff_profile_id (Event.staff_id and ClassOccurrence.trainer_id reference staff_profiles.id)
        $staffProfile = StaffProfile::where('user_id', $userId)->first();
        $staffProfileId = $staffProfile?->id ?? $userId; // Fallback to userId if no profile found

        // Calculate settlement data
        $calculation = $this->pricingService->calculateSettlementForTrainer(
            $staffProfileId,
            $periodStart,
            $periodEnd
        );

        // Create settlement within a transaction
        $settlement = DB::transaction(function () use ($validated, $calculation) {
            // Create settlement header
            $settlement = Settlement::create([
                'trainer_id' => $validated['trainer_id'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'total_trainer_fee' => $calculation['total_trainer_fee'],
                'total_entry_fee' => $calculation['total_entry_fee'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $validated['created_by'],
            ]);

            // Create settlement items
            foreach ($calculation['items'] as $item) {
                SettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'class_occurrence_id' => $item['class_occurrence_id'],
                    'client_id' => $item['client_id'],
                    'registration_id' => $item['registration_id'],
                    'entry_fee_brutto' => $item['entry_fee_brutto'],
                    'trainer_fee_brutto' => $item['trainer_fee_brutto'],
                    'currency' => $item['currency'],
                    'status' => $item['status'],
                ]);
            }

            return $settlement;
        });

        // Load relationships for response
        $settlement->load(['trainer', 'items.classOccurrence.template', 'items.client']);

        return ApiResponse::created($settlement, 'Settlement generated successfully');
    }

    /**
     * Get settlement details with all items.
     *
     * GET /api/v1/admin/settlements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $settlement = Settlement::with([
            'trainer',
            'creator',
            'items.classOccurrence.template',
            'items.client',
            'items.registration',
        ])->findOrFail($id);

        // Transform items to include additional information
        $transformedItems = $settlement->items->map(function ($item) {
            return [
                'id' => $item->id,
                'class_occurrence_id' => $item->class_occurrence_id,
                'class_name' => $item->classOccurrence->template->title ?? 'Unknown',
                'class_date' => $item->classOccurrence->starts_at->toIso8601String(),
                'client_id' => $item->client_id,
                'client_name' => $item->client->full_name ?? 'Unknown',
                'registration_id' => $item->registration_id,
                'entry_fee_brutto' => $item->entry_fee_brutto,
                'trainer_fee_brutto' => $item->trainer_fee_brutto,
                'currency' => $item->currency,
                'status' => $item->status,
            ];
        });

        return ApiResponse::success([
            'id' => $settlement->id,
            'trainer_id' => $settlement->trainer_id,
            'trainer_name' => $settlement->trainer->name ?? 'Unknown',
            'period_start' => $settlement->period_start->toDateString(),
            'period_end' => $settlement->period_end->toDateString(),
            'total_trainer_fee' => $settlement->total_trainer_fee,
            'total_entry_fee' => $settlement->total_entry_fee,
            'status' => $settlement->status,
            'notes' => $settlement->notes,
            'created_by' => $settlement->created_by,
            'created_at' => $settlement->created_at->toIso8601String(),
            'updated_at' => $settlement->updated_at->toIso8601String(),
            'items_count' => $settlement->items->count(),
            'items' => $transformedItems,
        ]);
    }

    /**
     * Update settlement status (finalize or mark as paid).
     *
     * PATCH /api/v1/admin/settlements/{id}
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,finalized,paid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $settlement = Settlement::findOrFail($id);

        // Update status and notes
        $settlement->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $settlement->notes,
        ]);

        $settlement->load(['trainer', 'items']);

        return ApiResponse::success($settlement, 'Settlement status updated successfully');
    }
}
