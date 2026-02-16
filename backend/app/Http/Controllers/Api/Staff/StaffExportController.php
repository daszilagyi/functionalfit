<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\ClassOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffExportController extends Controller
{
    /**
     * Generate staff payout report (hours worked × rate)
     *
     * GET /api/staff/exports/payout
     * Query params: date_from, date_to, format (json|xlsx)
     */
    public function payout(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['sometimes', 'in:json,xlsx'],
        ]);

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can access payout reports', null, 403);
        }

        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        // Fetch 1:1 events
        $individualEvents = Event::with(['client.user', 'additionalClients', 'room'])
            ->where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at')
            ->get();

        // Fetch group classes
        $groupClasses = ClassOccurrence::with(['template', 'room'])
            ->where('trainer_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at')
            ->get();

        // Calculate hours and earnings
        $totalIndividualHours = $individualEvents->sum(function ($event) {
            return $event->starts_at->diffInMinutes($event->ends_at) / 60;
        });

        $totalGroupHours = $groupClasses->sum(function ($occurrence) {
            return $occurrence->starts_at->diffInMinutes($occurrence->ends_at) / 60;
        });

        $totalHours = $totalIndividualHours + $totalGroupHours;

        // Get billing rate (from staff profile or default)
        $hourlyRate = (float) ($staff->default_hourly_rate ?? 5000);
        $totalEarnings = $totalHours * $hourlyRate;

        // Return XLSX
        if ($request->input('format') === 'xlsx') {
            return $this->generatePayoutXlsx(
                $staff,
                $dateFrom,
                $dateTo,
                $individualEvents,
                $groupClasses,
                $totalIndividualHours,
                $totalGroupHours,
                $totalHours,
                $hourlyRate,
                $totalEarnings
            );
        }

        // Return JSON
        $reportData = [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->user->name,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'summary' => [
                'total_hours' => round($totalHours, 2),
                'individual_hours' => round($totalIndividualHours, 2),
                'group_hours' => round($totalGroupHours, 2),
                'hourly_rate' => $hourlyRate,
                'total_earnings' => round($totalEarnings, 0),
                'currency' => 'HUF',
            ],
            'breakdown' => [
                'individual_events' => $individualEvents->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'client' => $e->client?->user->name,
                    'room' => $e->room?->name,
                    'starts_at' => $e->starts_at->toIso8601String(),
                    'ends_at' => $e->ends_at->toIso8601String(),
                    'hours' => round($e->starts_at->diffInMinutes($e->ends_at) / 60, 2),
                    'status' => $e->attendance_status,
                ]),
                'group_classes' => $groupClasses->map(fn($oc) => [
                    'id' => $oc->id,
                    'class_name' => $oc->template?->name ?? 'Unknown Class',
                    'room' => $oc->room?->name,
                    'participants' => $oc->current_participants,
                    'starts_at' => $oc->starts_at->toIso8601String(),
                    'ends_at' => $oc->ends_at->toIso8601String(),
                    'hours' => round($oc->starts_at->diffInMinutes($oc->ends_at) / 60, 2),
                ]),
            ],
        ];

        return ApiResponse::success($reportData, 'Payout report generated');
    }

    /**
     * Generate XLSX file for payout report
     */
    private function generatePayoutXlsx(
        $staff,
        string $dateFrom,
        string $dateTo,
        $individualEvents,
        $groupClasses,
        float $totalIndividualHours,
        float $totalGroupHours,
        float $totalHours,
        float $hourlyRate,
        float $totalEarnings
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();

        // Summary sheet
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Összesítés');

        // Header
        $sheet->setCellValue('A1', 'Fizetési Jelentés');
        $sheet->setCellValue('A2', 'Edző: ' . $staff->user->name);
        $sheet->setCellValue('A3', 'Időszak: ' . $dateFrom . ' - ' . $dateTo);

        // Summary
        $sheet->setCellValue('A5', 'Összesítés');
        $sheet->setCellValue('A6', 'Személyi edzések száma');
        $sheet->setCellValue('B6', $individualEvents->count());
        $sheet->setCellValue('A7', 'Csoportos órák száma');
        $sheet->setCellValue('B7', $groupClasses->count());
        $sheet->setCellValue('A8', 'Összes esemény');
        $sheet->setCellValue('B8', $individualEvents->count() + $groupClasses->count());
        $sheet->setCellValue('A9', '');
        $sheet->setCellValue('A10', 'Személyi edzések (óra)');
        $sheet->setCellValue('B10', round($totalIndividualHours, 2));
        $sheet->setCellValue('A11', 'Csoportos órák (óra)');
        $sheet->setCellValue('B11', round($totalGroupHours, 2));
        $sheet->setCellValue('A12', 'Összes óra');
        $sheet->setCellValue('B12', round($totalHours, 2));
        $sheet->setCellValue('A13', '');
        $sheet->setCellValue('A14', 'Alapértelmezett óradíj (HUF)');
        $sheet->setCellValue('B14', $hourlyRate);

        // Calculate total entry fees and trainer fees
        $totalEntryFees = $individualEvents->sum('entry_fee_brutto') +
            $groupClasses->sum(fn($c) => (float) ($c->template?->base_price_huf ?? 0) * ($c->current_participants ?? 0));
        $totalTrainerFees = $individualEvents->sum('trainer_fee_brutto') +
            $groupClasses->sum(fn($c) => $c->starts_at->diffInMinutes($c->ends_at) / 60 * $hourlyRate);

        $sheet->setCellValue('A15', 'Összes belépő díj (HUF)');
        $sheet->setCellValue('B15', round($totalEntryFees, 0));
        $sheet->setCellValue('A16', 'Összes edzői díj (HUF)');
        $sheet->setCellValue('B16', round($totalTrainerFees, 0));

        // Style summary
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A8:B8')->getFont()->setBold(true);
        $sheet->getStyle('A12:B12')->getFont()->setBold(true);
        $sheet->getStyle('A15:B16')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);

        // All Events sheet (combined list sorted by date)
        $allEventsSheet = $spreadsheet->createSheet();
        $allEventsSheet->setTitle('Összes esemény');

        // Headers
        $headers = ['Edző', 'Típus', 'Dátum', 'Kezdés', 'Befejezés', 'Időtartam (perc)', 'Terem', 'Vendég/Óra neve', 'Résztvevők', 'Belépő díj (HUF)', 'Edzői díj (HUF)', 'Státusz'];
        $col = 'A';
        foreach ($headers as $header) {
            $allEventsSheet->setCellValue($col . '1', $header);
            $col++;
        }
        $allEventsSheet->getStyle('A1:L1')->getFont()->setBold(true);
        $allEventsSheet->getStyle('A1:L1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');

        // Combine and sort all events
        $allEvents = collect();

        foreach ($individualEvents as $event) {
            $allEvents->push([
                'type' => 'individual',
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'data' => $event,
            ]);
        }

        foreach ($groupClasses as $class) {
            $allEvents->push([
                'type' => 'group',
                'starts_at' => $class->starts_at,
                'ends_at' => $class->ends_at,
                'data' => $class,
            ]);
        }

        $allEvents = $allEvents->sortBy('starts_at');

        $row = 2;
        foreach ($allEvents as $item) {
            $staffName = $staff->user->name;
            $startsAt = $item['starts_at'];
            $endsAt = $item['ends_at'];
            $duration = $startsAt->diffInMinutes($endsAt);

            if ($item['type'] === 'individual') {
                $event = $item['data'];
                $allEventsSheet->setCellValue('A' . $row, $staffName);
                $allEventsSheet->setCellValue('B' . $row, 'Személyi edzés');
                $allEventsSheet->setCellValue('C' . $row, $startsAt->format('Y-m-d'));
                $allEventsSheet->setCellValue('D' . $row, $startsAt->format('H:i'));
                $allEventsSheet->setCellValue('E' . $row, $endsAt->format('H:i'));
                $allEventsSheet->setCellValue('F' . $row, $duration);
                $allEventsSheet->setCellValue('G' . $row, $event->room?->name ?? '-');
                $allEventsSheet->setCellValue('H' . $row, $event->client?->user->name ?? '-');
                $allEventsSheet->setCellValue('I' . $row, 1 + ($event->additionalClients?->sum('pivot.quantity') ?? 0));
                $allEventsSheet->setCellValue('J' . $row, $event->entry_fee_brutto ?? 0);
                $allEventsSheet->setCellValue('K' . $row, $event->trainer_fee_brutto ?? 0);
                $allEventsSheet->setCellValue('L' . $row, $this->translateStatus($event->attendance_status));
            } else {
                $class = $item['data'];
                $classPrice = (float) ($class->template?->base_price_huf ?? 0);
                $participants = $class->current_participants ?? 0;
                $trainerFee = ($duration / 60) * $hourlyRate;

                $allEventsSheet->setCellValue('A' . $row, $staffName);
                $allEventsSheet->setCellValue('B' . $row, 'Csoportos edzés');
                $allEventsSheet->setCellValue('C' . $row, $startsAt->format('Y-m-d'));
                $allEventsSheet->setCellValue('D' . $row, $startsAt->format('H:i'));
                $allEventsSheet->setCellValue('E' . $row, $endsAt->format('H:i'));
                $allEventsSheet->setCellValue('F' . $row, $duration);
                $allEventsSheet->setCellValue('G' . $row, $class->room?->name ?? '-');
                $allEventsSheet->setCellValue('H' . $row, $class->template?->name ?? '-');
                $allEventsSheet->setCellValue('I' . $row, $participants);
                $allEventsSheet->setCellValue('J' . $row, round($classPrice * $participants, 0));
                $allEventsSheet->setCellValue('K' . $row, round($trainerFee, 0));
                $allEventsSheet->setCellValue('L' . $row, $class->is_cancelled ? 'Lemondva' : 'Megtartva');
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $allEventsSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add totals row
        $totalRow = $row + 1;
        $allEventsSheet->setCellValue('A' . $totalRow, 'ÖSSZESEN');
        $allEventsSheet->setCellValue('F' . $totalRow, '=SUM(F2:F' . ($row - 1) . ')');
        $allEventsSheet->setCellValue('I' . $totalRow, '=SUM(I2:I' . ($row - 1) . ')');
        $allEventsSheet->setCellValue('J' . $totalRow, '=SUM(J2:J' . ($row - 1) . ')');
        $allEventsSheet->setCellValue('K' . $totalRow, '=SUM(K2:K' . ($row - 1) . ')');
        $allEventsSheet->getStyle('A' . $totalRow . ':L' . $totalRow)->getFont()->setBold(true);
        $allEventsSheet->getStyle('A' . $totalRow . ':L' . $totalRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF99');

        // Set active sheet to first
        $spreadsheet->setActiveSheetIndex(0);

        // Generate response
        $filename = "fizetes_{$staff->id}_{$dateFrom}_{$dateTo}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Translate attendance status to Hungarian
     */
    private function translateStatus(?string $status): string
    {
        return match ($status) {
            'attended' => 'Megjelent',
            'no_show' => 'Nem jelent meg',
            'cancelled' => 'Lemondva',
            default => 'Tervezett',
        };
    }

    /**
     * Generate staff activity export (XLSX)
     *
     * GET /api/staff/exports/activity
     * Query params: date_from, date_to, client_search (optional), tab (upcoming|history|all)
     */
    public function activityExport(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'client_search' => ['sometimes', 'string', 'max:255'],
            'tab' => ['sometimes', 'in:upcoming,history,all'],
        ]);

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            abort(403, 'Only staff can access activity exports');
        }

        $dateFrom = \Carbon\Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = \Carbon\Carbon::parse($validated['date_to'])->endOfDay();
        $tab = $validated['tab'] ?? 'all';

        $query = Event::with(['client.user', 'additionalClients.user', 'room'])
            ->where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at');

        // Filter by tab (upcoming = future, history = past)
        if ($tab === 'upcoming') {
            $query->where('starts_at', '>=', now());
        } elseif ($tab === 'history') {
            $query->where('starts_at', '<', now());
        }

        // Filter by client name if provided
        if (!empty($validated['client_search'])) {
            $search = $validated['client_search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('client.user', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%");
                })->orWhereHas('additionalClients.user', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%");
                });
            });
        }

        $events = $query->get();

        return $this->generateActivityXlsx($events, $validated['date_from'], $validated['date_to']);
    }

    /**
     * Generate XLSX file for activity export
     */
    private function generateActivityXlsx($events, string $dateFrom, string $dateTo): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Aktivitás');

        // Headers
        $headers = ['Dátum', 'Idő', 'Vendég neve', 'Típus', 'Terem', 'Státusz', 'Időtartam (perc)', 'Megjegyzés'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');

        $row = 2;
        foreach ($events as $event) {
            // Collect all client names (main + additional)
            $clientNames = [];
            if ($event->client?->user?->name) {
                $clientNames[] = $event->client->user->name;
            }
            foreach ($event->additionalClients ?? [] as $ac) {
                if ($ac->user?->name) {
                    $clientNames[] = $ac->user->name;
                }
            }

            $duration = $event->starts_at->diffInMinutes($event->ends_at);
            $typeLabel = $event->type === 'INDIVIDUAL' ? 'Személyi edzés' : 'Csoportos edzés';

            $sheet->setCellValue('A' . $row, $event->starts_at->format('Y-m-d'));
            $sheet->setCellValue('B' . $row, $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'));
            $sheet->setCellValue('C' . $row, implode(', ', $clientNames) ?: '-');
            $sheet->setCellValue('D' . $row, $typeLabel);
            $sheet->setCellValue('E' . $row, $event->room?->name ?? '-');
            $sheet->setCellValue('F' . $row, $this->translateStatus($event->attendance_status ?? $event->status));
            $sheet->setCellValue('G' . $row, $duration);
            $sheet->setCellValue('H' . $row, $event->notes ?? '');
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "aktivitas_{$dateFrom}_{$dateTo}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Get staff's attendance report
     *
     * GET /api/staff/exports/attendance
     */
    public function attendance(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['sometimes', 'in:json,xlsx'],
        ]);

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can access attendance reports', null, 403);
        }

        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        // Fetch all events with attendance data
        $events = Event::with(['client.user', 'room'])
            ->where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at')
            ->get();

        $attended = $events->where('attendance_status', 'attended')->count();
        $noShows = $events->where('attendance_status', 'no_show')->count();
        $cancelled = $events->where('attendance_status', 'cancelled')->count();
        $scheduled = $events->whereNull('attendance_status')->count();

        // Return XLSX
        if ($request->input('format') === 'xlsx') {
            return $this->generateAttendanceXlsx(
                $staff,
                $dateFrom,
                $dateTo,
                $events,
                $attended,
                $noShows,
                $cancelled,
                $scheduled
            );
        }

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_events' => $events->count(),
                'attended' => $attended,
                'no_shows' => $noShows,
                'cancelled' => $cancelled,
                'scheduled' => $scheduled,
                'attendance_rate' => $events->count() > 0
                    ? round(($attended / $events->count()) * 100, 2)
                    : 0,
            ],
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'client' => $e->client?->user->name,
                'room' => $e->room?->name,
                'starts_at' => $e->starts_at->toIso8601String(),
                'attendance_status' => $e->attendance_status,
            ]),
        ], 'Attendance report generated');
    }

    /**
     * Generate XLSX file for attendance report
     */
    private function generateAttendanceXlsx(
        $staff,
        string $dateFrom,
        string $dateTo,
        $events,
        int $attended,
        int $noShows,
        int $cancelled,
        int $scheduled
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Részvételi jelentés');

        // Header
        $sheet->setCellValue('A1', 'Részvételi Jelentés');
        $sheet->setCellValue('A2', 'Edző: ' . $staff->user->name);
        $sheet->setCellValue('A3', 'Időszak: ' . $dateFrom . ' - ' . $dateTo);

        // Summary
        $sheet->setCellValue('A5', 'Összesítés');
        $sheet->setCellValue('A6', 'Összes esemény');
        $sheet->setCellValue('B6', $events->count());
        $sheet->setCellValue('A7', 'Megjelent');
        $sheet->setCellValue('B7', $attended);
        $sheet->setCellValue('A8', 'Nem jelent meg');
        $sheet->setCellValue('B8', $noShows);
        $sheet->setCellValue('A9', 'Lemondva');
        $sheet->setCellValue('B9', $cancelled);
        $sheet->setCellValue('A10', 'Tervezett');
        $sheet->setCellValue('B10', $scheduled);
        $sheet->setCellValue('A11', 'Részvételi arány');
        $sheet->setCellValue('B11', $events->count() > 0
            ? round(($attended / $events->count()) * 100, 1) . '%'
            : '0%');

        // Style
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A11:B11')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);

        // Events list
        $sheet->setCellValue('A13', 'Események részletezése');
        $sheet->getStyle('A13')->getFont()->setBold(true);

        $sheet->setCellValue('A14', 'Dátum');
        $sheet->setCellValue('B14', 'Időpont');
        $sheet->setCellValue('C14', 'Vendég');
        $sheet->setCellValue('D14', 'Terem');
        $sheet->setCellValue('E14', 'Státusz');
        $sheet->getStyle('A14:E14')->getFont()->setBold(true);

        $row = 15;
        foreach ($events as $event) {
            $sheet->setCellValue('A' . $row, $event->starts_at->format('Y-m-d'));
            $sheet->setCellValue('B' . $row, $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'));
            $sheet->setCellValue('C' . $row, $event->client?->user->name ?? '-');
            $sheet->setCellValue('D' . $row, $event->room?->name ?? '-');
            $sheet->setCellValue('E' . $row, $this->translateStatus($event->attendance_status));
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Generate response
        $filename = "reszvetel_{$staff->id}_{$dateFrom}_{$dateTo}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
