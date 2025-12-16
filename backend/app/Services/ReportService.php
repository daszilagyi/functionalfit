<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Report Service - High-performance SQL aggregations for reporting endpoints
 *
 * TIMEZONE: All dates/times use Europe/Budapest timezone
 * PERFORMANCE: All queries use database-level aggregations and existing indexes
 * SECURITY: All inputs validated by calling controller
 */
class ReportService
{
    /**
     * Trainer Summary Aggregation
     *
     * Groups by: trainer_id + site/room
     * Outputs: total_trainer_fee_brutto, total_entry_fee_brutto, total_hours, total_sessions
     * Filters: from, to, trainer_id, site, room_id, service_type_id
     *
     * @param array $filters ['from' => Carbon, 'to' => Carbon, 'trainer_id' => int, 'site_id' => int, 'room_id' => int, 'service_type_id' => int]
     * @return Collection
     */
    public function trainerSummary(array $filters): Collection
    {
        $from = $filters['from'];
        $to = $filters['to'];

        // INDIVIDUAL EVENTS aggregation
        $eventsQuery = Event::query()
            ->select([
                'staff_id as trainer_id',
                'room_id',
                DB::raw('SUM(trainer_fee_brutto) as total_trainer_fee_brutto'),
                DB::raw('SUM(entry_fee_brutto) as total_entry_fee_brutto'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0) as total_hours'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('"INDIVIDUAL" as source_type'),
            ])
            ->whereBetween('starts_at', [$from, $to])
            ->where('attendance_status', 'attended') // Only count attended sessions
            ->groupBy('staff_id', 'room_id');

        // Apply filters - trainer_id
        if (isset($filters['trainer_id'])) {
            $eventsQuery->where('staff_id', $filters['trainer_id']);
        }

        // Apply filters - room_id
        if (isset($filters['room_id'])) {
            $eventsQuery->where('room_id', $filters['room_id']);
        }

        // Apply filters - service_type_id
        if (isset($filters['service_type_id'])) {
            $eventsQuery->where('service_type_id', $filters['service_type_id']);
        }

        // Apply filters - site (via room relationship)
        if (isset($filters['site_id'])) {
            $eventsQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // CLASS OCCURRENCES aggregation (group classes)
        $classesQuery = ClassOccurrence::query()
            ->select([
                'trainer_id',
                'room_id',
                DB::raw('SUM(COALESCE(si.trainer_fee_brutto, 0)) as total_trainer_fee_brutto'),
                DB::raw('SUM(COALESCE(si.entry_fee_brutto, 0)) as total_entry_fee_brutto'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, class_occurrences.starts_at, class_occurrences.ends_at) / 60.0 * COALESCE(attended_count, 0)) as total_hours'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('"CLASS" as source_type'),
            ])
            ->leftJoin('settlement_items as si', 'class_occurrences.id', '=', 'si.class_occurrence_id')
            ->leftJoin(
                DB::raw('(SELECT occurrence_id, COUNT(*) as attended_count FROM class_registrations WHERE status = "attended" GROUP BY occurrence_id) as attended_regs'),
                'class_occurrences.id',
                '=',
                'attended_regs.occurrence_id'
            )
            ->whereBetween('class_occurrences.starts_at', [$from, $to])
            ->where('si.status', 'attended') // Only count attended registrations
            ->groupBy('trainer_id', 'room_id');

        // Apply filters - trainer_id
        if (isset($filters['trainer_id'])) {
            $classesQuery->where('trainer_id', $filters['trainer_id']);
        }

        // Apply filters - room_id
        if (isset($filters['room_id'])) {
            $classesQuery->where('class_occurrences.room_id', $filters['room_id']);
        }

        // Apply filters - site (via room relationship)
        if (isset($filters['site_id'])) {
            $classesQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // UNION both datasets
        $combined = $eventsQuery->get()->concat($classesQuery->get());

        // Group by trainer + room and sum totals
        return $combined->groupBy(function ($item) {
            return $item->trainer_id . '_' . $item->room_id;
        })->map(function ($group) {
            $first = $group->first();
            return [
                'trainer_id' => $first->trainer_id,
                'room_id' => $first->room_id,
                'total_trainer_fee_brutto' => $group->sum('total_trainer_fee_brutto'),
                'total_entry_fee_brutto' => $group->sum('total_entry_fee_brutto'),
                'total_hours' => round($group->sum('total_hours'), 2),
                'total_sessions' => $group->sum('total_sessions'),
            ];
        })->values();
    }

    /**
     * Site-Client-List Aggregation
     *
     * Groups by: client_id, client_email
     * Outputs: client name/email, total_entry_fee_brutto, total_hours, services_breakdown
     * Filters: from, to, site_id, room_id
     *
     * @param array $filters ['from' => Carbon, 'to' => Carbon, 'site_id' => int, 'room_id' => int]
     * @return Collection
     */
    public function siteClientList(array $filters): Collection
    {
        $from = $filters['from'];
        $to = $filters['to'];

        // INDIVIDUAL EVENTS by client
        $eventsQuery = Event::query()
            ->select([
                'client_id',
                'service_type_id',
                DB::raw('SUM(entry_fee_brutto) as total_entry_fee_brutto'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0) as total_hours'),
                DB::raw('COUNT(*) as total_sessions'),
            ])
            ->whereBetween('starts_at', [$from, $to])
            ->where('attendance_status', 'attended')
            ->whereNotNull('client_id')
            ->groupBy('client_id', 'service_type_id');

        // Apply filters - room_id
        if (isset($filters['room_id'])) {
            $eventsQuery->where('room_id', $filters['room_id']);
        }

        // Apply filters - site_id (via room relationship)
        if (isset($filters['site_id'])) {
            $eventsQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // CLASS REGISTRATIONS by client
        $registrationsQuery = ClassRegistration::query()
            ->select([
                'client_id',
                DB::raw('NULL as service_type_id'),
                DB::raw('SUM(COALESCE(si.entry_fee_brutto, 0)) as total_entry_fee_brutto'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, co.starts_at, co.ends_at) / 60.0) as total_hours'),
                DB::raw('COUNT(*) as total_sessions'),
            ])
            ->join('class_occurrences as co', 'class_registrations.occurrence_id', '=', 'co.id')
            ->leftJoin('settlement_items as si', 'class_registrations.id', '=', 'si.registration_id')
            ->whereBetween('co.starts_at', [$from, $to])
            ->where('class_registrations.status', 'attended')
            ->groupBy('client_id');

        // Apply filters - room_id
        if (isset($filters['room_id'])) {
            $registrationsQuery->where('co.room_id', $filters['room_id']);
        }

        // Apply filters - site_id (via room relationship)
        if (isset($filters['site_id'])) {
            $registrationsQuery->join('rooms as r', 'co.room_id', '=', 'r.id')
                ->where('r.site_id', $filters['site_id']);
        }

        // Combine both data sources
        $eventsData = $eventsQuery->get();
        $registrationsData = $registrationsQuery->get();

        // Load service type names for breakdown
        $serviceTypes = ServiceType::all()->keyBy('id');

        // Group by client_id and aggregate with service breakdown
        $clientIds = $eventsData->pluck('client_id')
            ->merge($registrationsData->pluck('client_id'))
            ->unique();

        $clients = Client::with('user')->whereIn('id', $clientIds)->get()->keyBy('id');

        return $clientIds->map(function ($clientId) use ($eventsData, $registrationsData, $clients, $serviceTypes) {
            $client = $clients->get($clientId);
            if (!$client) {
                return null;
            }

            $clientEventsData = $eventsData->where('client_id', $clientId);
            $clientRegistrationsData = $registrationsData->where('client_id', $clientId);

            // Service breakdown (from events only, as classes don't have service_type_id)
            $servicesBreakdown = $clientEventsData->groupBy('service_type_id')->map(function ($group, $serviceTypeId) use ($serviceTypes) {
                $serviceType = $serviceTypes->get($serviceTypeId);
                return [
                    'service_type_id' => $serviceTypeId,
                    'service_type_name' => $serviceType ? $serviceType->name : 'Unknown',
                    'total_entry_fee_brutto' => $group->sum('total_entry_fee_brutto'),
                    'total_hours' => round($group->sum('total_hours'), 2),
                    'total_sessions' => $group->sum('total_sessions'),
                ];
            })->values();

            // Add group classes as separate service type
            if ($clientRegistrationsData->isNotEmpty()) {
                $servicesBreakdown->push([
                    'service_type_id' => null,
                    'service_type_name' => 'Csoportos órák',
                    'total_entry_fee_brutto' => $clientRegistrationsData->sum('total_entry_fee_brutto'),
                    'total_hours' => round($clientRegistrationsData->sum('total_hours'), 2),
                    'total_sessions' => $clientRegistrationsData->sum('total_sessions'),
                ]);
            }

            return [
                'client_id' => $clientId,
                'client_name' => $client->user->name,
                'client_email' => $client->user->email,
                'total_entry_fee_brutto' => $clientEventsData->sum('total_entry_fee_brutto') + $clientRegistrationsData->sum('total_entry_fee_brutto'),
                'total_hours' => round($clientEventsData->sum('total_hours') + $clientRegistrationsData->sum('total_hours'), 2),
                'total_sessions' => $clientEventsData->sum('total_sessions') + $clientRegistrationsData->sum('total_sessions'),
                'services_breakdown' => $servicesBreakdown,
            ];
        })->filter()->sortByDesc('total_entry_fee_brutto')->values();
    }

    /**
     * Trends Report - Time-series KPIs
     *
     * Granularity: week or month
     * KPIs: sessions, hours, entry_fee, trainer_fee, no_show_ratio
     * Filters: from, to, granularity (week|month), trainer_id, site_id
     *
     * @param array $filters ['from' => Carbon, 'to' => Carbon, 'granularity' => 'week'|'month', 'trainer_id' => int, 'site_id' => int]
     * @return Collection
     */
    public function trends(array $filters): Collection
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $granularity = $filters['granularity'] ?? 'week'; // week or month

        // Determine date format for grouping (Europe/Budapest timezone)
        $dateFormat = match ($granularity) {
            'month' => '%Y-%m',
            'week' => '%Y-%u', // ISO week number
            default => '%Y-%m-%d',
        };

        // INDIVIDUAL EVENTS time-series
        $eventsQuery = Event::query()
            ->select([
                DB::raw("DATE_FORMAT(starts_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0) as total_hours'),
                DB::raw('SUM(entry_fee_brutto) as total_entry_fee'),
                DB::raw('SUM(trainer_fee_brutto) as total_trainer_fee'),
                DB::raw('SUM(CASE WHEN attendance_status = "no_show" THEN 1 ELSE 0 END) as no_show_count'),
                DB::raw('SUM(CASE WHEN attendance_status = "attended" THEN 1 ELSE 0 END) as attended_count'),
            ])
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('attendance_status', ['attended', 'no_show'])
            ->groupBy('period')
            ->orderBy('period');

        // Apply filters - trainer_id
        if (isset($filters['trainer_id'])) {
            $eventsQuery->where('staff_id', $filters['trainer_id']);
        }

        // Apply filters - site_id (via room relationship)
        if (isset($filters['site_id'])) {
            $eventsQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // CLASS REGISTRATIONS time-series
        $registrationsQuery = ClassRegistration::query()
            ->select([
                DB::raw("DATE_FORMAT(co.starts_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, co.starts_at, co.ends_at) / 60.0) as total_hours'),
                DB::raw('SUM(COALESCE(si.entry_fee_brutto, 0)) as total_entry_fee'),
                DB::raw('SUM(COALESCE(si.trainer_fee_brutto, 0)) as total_trainer_fee'),
                DB::raw('SUM(CASE WHEN class_registrations.status = "no_show" THEN 1 ELSE 0 END) as no_show_count'),
                DB::raw('SUM(CASE WHEN class_registrations.status = "attended" THEN 1 ELSE 0 END) as attended_count'),
            ])
            ->join('class_occurrences as co', 'class_registrations.occurrence_id', '=', 'co.id')
            ->leftJoin('settlement_items as si', 'class_registrations.id', '=', 'si.registration_id')
            ->whereBetween('co.starts_at', [$from, $to])
            ->whereIn('class_registrations.status', ['attended', 'no_show'])
            ->groupBy('period')
            ->orderBy('period');

        // Apply filters - trainer_id
        if (isset($filters['trainer_id'])) {
            $registrationsQuery->where('co.trainer_id', $filters['trainer_id']);
        }

        // Apply filters - site_id (via room relationship)
        if (isset($filters['site_id'])) {
            $registrationsQuery->join('rooms as r', 'co.room_id', '=', 'r.id')
                ->where('r.site_id', $filters['site_id']);
        }

        // Combine and aggregate by period
        $eventsData = $eventsQuery->get();
        $registrationsData = $registrationsQuery->get();

        $allPeriods = $eventsData->pluck('period')
            ->merge($registrationsData->pluck('period'))
            ->unique()
            ->sort()
            ->values();

        return $allPeriods->map(function ($period) use ($eventsData, $registrationsData) {
            $eventsPeriod = $eventsData->firstWhere('period', $period);
            $registrationsPeriod = $registrationsData->firstWhere('period', $period);

            $totalSessions = ($eventsPeriod->total_sessions ?? 0) + ($registrationsPeriod->total_sessions ?? 0);
            $noShowCount = ($eventsPeriod->no_show_count ?? 0) + ($registrationsPeriod->no_show_count ?? 0);
            $attendedCount = ($eventsPeriod->attended_count ?? 0) + ($registrationsPeriod->attended_count ?? 0);

            return [
                'period' => $period,
                'total_sessions' => $totalSessions,
                'total_hours' => round(($eventsPeriod->total_hours ?? 0) + ($registrationsPeriod->total_hours ?? 0), 2),
                'total_entry_fee' => ($eventsPeriod->total_entry_fee ?? 0) + ($registrationsPeriod->total_entry_fee ?? 0),
                'total_trainer_fee' => ($eventsPeriod->total_trainer_fee ?? 0) + ($registrationsPeriod->total_trainer_fee ?? 0),
                'no_show_count' => $noShowCount,
                'attended_count' => $attendedCount,
                'no_show_ratio' => $totalSessions > 0 ? round(($noShowCount / $totalSessions) * 100, 2) : 0,
                'attendance_rate' => $totalSessions > 0 ? round(($attendedCount / $totalSessions) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Drilldown: Trainer → Site → Client → Item-level list
     *
     * Returns detailed sessions for a specific trainer, optionally filtered by site and client
     * Filters: from, to, trainer_id (required), site_id (optional), client_id (optional)
     *
     * @param array $filters ['from' => Carbon, 'to' => Carbon, 'trainer_id' => int, 'site_id' => int, 'client_id' => int]
     * @return Collection
     */
    public function drilldownTrainerSessions(array $filters): Collection
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $trainerId = $filters['trainer_id']; // Required

        // INDIVIDUAL EVENTS
        $eventsQuery = Event::query()
            ->with(['client.user', 'room.site', 'serviceType'])
            ->select([
                'id',
                'type',
                'client_id',
                'room_id',
                'service_type_id',
                'starts_at',
                'ends_at',
                'attendance_status',
                'entry_fee_brutto',
                'trainer_fee_brutto',
                'currency',
                DB::raw('TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0 as hours'),
                DB::raw('"INDIVIDUAL" as session_type'),
            ])
            ->where('staff_id', $trainerId)
            ->whereBetween('starts_at', [$from, $to])
            ->where('attendance_status', 'attended');

        // Apply optional filters - site_id
        if (isset($filters['site_id'])) {
            $eventsQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // Apply optional filters - client_id
        if (isset($filters['client_id'])) {
            $eventsQuery->where('client_id', $filters['client_id']);
        }

        // CLASS OCCURRENCES with registrations
        $classesQuery = ClassOccurrence::query()
            ->with(['template', 'room.site', 'registrations.client.user'])
            ->select('class_occurrences.*')
            ->where('trainer_id', $trainerId)
            ->whereBetween('starts_at', [$from, $to]);

        // Apply optional filters - site_id
        if (isset($filters['site_id'])) {
            $classesQuery->whereHas('room', function ($q) use ($filters) {
                $q->where('site_id', $filters['site_id']);
            });
        }

        // Apply optional filters - client_id
        if (isset($filters['client_id'])) {
            $classesQuery->whereHas('registrations', function ($q) use ($filters) {
                $q->where('client_id', $filters['client_id'])
                  ->where('status', 'attended');
            });
        }

        $events = $eventsQuery->get();
        $classes = $classesQuery->get();

        // Transform INDIVIDUAL events
        $eventsTransformed = $events->map(function ($event) {
            return [
                'id' => 'event_' . $event->id,
                'session_type' => 'INDIVIDUAL',
                'date' => $event->starts_at->format('Y-m-d'),
                'time' => $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'),
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'client_name' => $event->client ? $event->client->user->name : 'N/A',
                'client_email' => $event->client ? $event->client->user->email : 'N/A',
                'site_name' => $event->room && $event->room->site ? $event->room->site->name : 'N/A',
                'room_name' => $event->room ? $event->room->name : 'N/A',
                'service_type_name' => $event->serviceType ? $event->serviceType->name : 'N/A',
                'hours' => round($event->hours, 2),
                'entry_fee_brutto' => $event->entry_fee_brutto,
                'trainer_fee_brutto' => $event->trainer_fee_brutto,
                'currency' => $event->currency ?? 'HUF',
            ];
        });

        // Transform CLASS OCCURRENCES (expand by registrations)
        $classesTransformed = $classes->flatMap(function ($occurrence) use ($filters) {
            return $occurrence->registrations
                ->filter(function ($registration) use ($filters) {
                    // Filter by client_id if provided
                    if (isset($filters['client_id']) && $registration->client_id !== $filters['client_id']) {
                        return false;
                    }
                    return $registration->status === 'attended';
                })
                ->map(function ($registration) use ($occurrence) {
                    $hours = $occurrence->starts_at->diffInMinutes($occurrence->ends_at) / 60.0;

                    // Get pricing from settlement_items if available
                    $settlementItem = $registration->settlementItems->first();

                    return [
                        'id' => 'class_' . $occurrence->id . '_reg_' . $registration->id,
                        'session_type' => 'GROUP_CLASS',
                        'date' => $occurrence->starts_at->format('Y-m-d'),
                        'time' => $occurrence->starts_at->format('H:i') . ' - ' . $occurrence->ends_at->format('H:i'),
                        'starts_at' => $occurrence->starts_at->toIso8601String(),
                        'ends_at' => $occurrence->ends_at->toIso8601String(),
                        'class_name' => $occurrence->template ? $occurrence->template->name : 'N/A',
                        'client_name' => $registration->client ? $registration->client->user->name : 'N/A',
                        'client_email' => $registration->client ? $registration->client->user->email : 'N/A',
                        'site_name' => $occurrence->room && $occurrence->room->site ? $occurrence->room->site->name : 'N/A',
                        'room_name' => $occurrence->room ? $occurrence->room->name : 'N/A',
                        'service_type_name' => 'Csoportos óra',
                        'hours' => round($hours, 2),
                        'entry_fee_brutto' => $settlementItem ? $settlementItem->entry_fee_brutto : 0,
                        'trainer_fee_brutto' => $settlementItem ? $settlementItem->trainer_fee_brutto : 0,
                        'currency' => $settlementItem ? $settlementItem->currency : 'HUF',
                    ];
                });
        });

        // Merge and sort by date descending
        return $eventsTransformed->concat($classesTransformed)
            ->sortByDesc('starts_at')
            ->values();
    }

    // =========================================================================
    // ADMIN REPORT METHODS
    // =========================================================================

    /**
     * Generate Admin Trainer Summary Report
     *
     * Groups by: site or room (based on groupBy param)
     * Outputs: trainer info, total fees, hours, sessions per group
     *
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $groupBy 'site' or 'room'
     * @param int|null $trainerId Filter by specific trainer
     * @param string|null $serviceType Filter by service type
     * @param int|null $siteId Filter by site
     * @return array
     */
    public function generateAdminTrainerSummary(
        string $from,
        string $to,
        string $groupBy,
        ?int $trainerId = null,
        ?string $serviceType = null,
        ?int $siteId = null
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Build filters for existing method
        $filters = [
            'from' => $fromDate,
            'to' => $toDate,
        ];

        if ($trainerId) {
            $filters['trainer_id'] = $trainerId;
        }

        if ($siteId) {
            $filters['site_id'] = $siteId;
        }

        // Get raw data from existing method
        $rawData = $this->trainerSummary($filters);

        // Load trainers and rooms for enrichment
        $trainerIds = $rawData->pluck('trainer_id')->unique()->filter();
        $roomIds = $rawData->pluck('room_id')->unique()->filter();

        $trainers = StaffProfile::with('user')->whereIn('id', $trainerIds)->get()->keyBy('id');
        $rooms = \App\Models\Room::with('site')->whereIn('id', $roomIds)->get()->keyBy('id');

        // Transform and group data
        $result = $rawData->map(function ($row) use ($trainers, $rooms) {
            $trainer = $trainers->get($row['trainer_id']);
            $room = $rooms->get($row['room_id']);

            return [
                'trainer_id' => $row['trainer_id'],
                'trainer_name' => $trainer?->user?->name ?? 'Unknown',
                'room_id' => $row['room_id'],
                'room_name' => $room?->name ?? 'Unknown',
                'site_id' => $room?->site_id,
                'site_name' => $room?->site?->name ?? 'Unknown',
                'total_trainer_fee' => (float) $row['total_trainer_fee_brutto'],
                'total_entry_fee' => (float) $row['total_entry_fee_brutto'],
                'total_hours' => (float) $row['total_hours'],
                'total_sessions' => (int) $row['total_sessions'],
            ];
        });

        // Group by trainer, then by site or room
        $grouped = $result->groupBy('trainer_id')->map(function ($trainerRows) use ($groupBy) {
            $first = $trainerRows->first();

            $breakdown = $trainerRows->groupBy($groupBy === 'site' ? 'site_id' : 'room_id')
                ->map(function ($groupRows) use ($groupBy) {
                    $groupFirst = $groupRows->first();
                    return [
                        'id' => $groupBy === 'site' ? $groupFirst['site_id'] : $groupFirst['room_id'],
                        'name' => $groupBy === 'site' ? $groupFirst['site_name'] : $groupFirst['room_name'],
                        'total_trainer_fee' => $groupRows->sum('total_trainer_fee'),
                        'total_entry_fee' => $groupRows->sum('total_entry_fee'),
                        'total_hours' => round($groupRows->sum('total_hours'), 2),
                        'total_sessions' => $groupRows->sum('total_sessions'),
                    ];
                })->values();

            return [
                'trainer_id' => $first['trainer_id'],
                'trainer_name' => $first['trainer_name'],
                'total_trainer_fee' => $trainerRows->sum('total_trainer_fee'),
                'total_entry_fee' => $trainerRows->sum('total_entry_fee'),
                'total_hours' => round($trainerRows->sum('total_hours'), 2),
                'total_sessions' => $trainerRows->sum('total_sessions'),
                'breakdown' => $breakdown,
            ];
        })->values();

        // Summary totals
        $summary = [
            'total_trainer_fee' => $result->sum('total_trainer_fee'),
            'total_entry_fee' => $result->sum('total_entry_fee'),
            'total_hours' => round($result->sum('total_hours'), 2),
            'total_sessions' => $result->sum('total_sessions'),
            'trainer_count' => $grouped->count(),
            'currency' => 'HUF',
        ];

        return [
            'summary' => $summary,
            'trainers' => $grouped,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
                'trainer_id' => $trainerId,
                'site_id' => $siteId,
            ],
        ];
    }

    /**
     * Generate Admin Site Client List Report
     *
     * Returns client list for a specific site with activity breakdown
     *
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param int $siteId Site ID (required)
     * @param int|null $roomId Filter by specific room
     * @return array
     */
    public function generateAdminSiteClientList(
        string $from,
        string $to,
        int $siteId,
        ?int $roomId = null
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $filters = [
            'from' => $fromDate,
            'to' => $toDate,
            'site_id' => $siteId,
        ];

        if ($roomId) {
            $filters['room_id'] = $roomId;
        }

        // Get raw data from existing method
        $clients = $this->siteClientList($filters);

        // Load site info
        $site = \App\Models\Site::find($siteId);

        // Summary totals
        $summary = [
            'site_id' => $siteId,
            'site_name' => $site?->name ?? 'Unknown',
            'total_clients' => $clients->count(),
            'total_entry_fee' => $clients->sum('total_entry_fee_brutto'),
            'total_hours' => round($clients->sum('total_hours'), 2),
            'total_sessions' => $clients->sum('total_sessions'),
            'currency' => 'HUF',
        ];

        return [
            'summary' => $summary,
            'clients' => $clients,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'site_id' => $siteId,
                'room_id' => $roomId,
            ],
        ];
    }

    /**
     * Generate Admin Finance Overview Report
     *
     * Time-series financial overview grouped by day/week/month
     *
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $groupBy 'day', 'week', or 'month'
     * @return array
     */
    public function generateAdminFinanceOverview(
        string $from,
        string $to,
        string $groupBy
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Map groupBy to granularity for trends method
        $granularity = $groupBy === 'day' ? 'day' : $groupBy;

        $filters = [
            'from' => $fromDate,
            'to' => $toDate,
            'granularity' => $granularity,
        ];

        // Get trends data
        $trendsData = $this->trends($filters);

        // Transform to finance-focused output
        $periods = $trendsData->map(function ($row) {
            $netIncome = ($row['total_entry_fee'] ?? 0) - ($row['total_trainer_fee'] ?? 0);

            return [
                'period' => $row['period'],
                'total_entry_fee' => (float) ($row['total_entry_fee'] ?? 0),
                'total_trainer_fee' => (float) ($row['total_trainer_fee'] ?? 0),
                'net_income' => $netIncome,
                'total_sessions' => (int) ($row['total_sessions'] ?? 0),
                'total_hours' => (float) ($row['total_hours'] ?? 0),
                'attendance_rate' => (float) ($row['attendance_rate'] ?? 0),
            ];
        });

        // Summary totals
        $summary = [
            'total_entry_fee' => $periods->sum('total_entry_fee'),
            'total_trainer_fee' => $periods->sum('total_trainer_fee'),
            'net_income' => $periods->sum('net_income'),
            'total_sessions' => $periods->sum('total_sessions'),
            'total_hours' => round($periods->sum('total_hours'), 2),
            'average_attendance_rate' => $periods->count() > 0
                ? round($periods->avg('attendance_rate'), 2)
                : 0,
            'currency' => 'HUF',
        ];

        return [
            'summary' => $summary,
            'periods' => $periods,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
            ],
        ];
    }

    // =========================================================================
    // STAFF REPORT METHODS (Scoped to authenticated staff member)
    // =========================================================================

    /**
     * Generate Staff My Summary Report
     *
     * Summary for authenticated staff member grouped by site/room/service_type
     *
     * @param int $staffId Staff profile ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $groupBy 'site', 'room', or 'service_type'
     * @return array
     */
    public function generateStaffMySummary(
        int $staffId,
        string $from,
        string $to,
        string $groupBy
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Individual events
        $eventsQuery = Event::query()
            ->where('staff_id', $staffId)
            ->whereBetween('starts_at', [$fromDate, $toDate])
            ->where('attendance_status', 'attended');

        // Group classes
        $classesQuery = ClassOccurrence::query()
            ->where('trainer_id', $staffId)
            ->whereBetween('starts_at', [$fromDate, $toDate]);

        // Get events with related data
        $events = $eventsQuery->with(['room.site', 'serviceType'])->get();
        $classes = $classesQuery->with(['room.site', 'template'])->get();

        // Transform events
        $eventsData = $events->map(function ($event) {
            $hours = $event->starts_at->diffInMinutes($event->ends_at) / 60.0;
            return [
                'type' => 'INDIVIDUAL',
                'site_id' => $event->room?->site_id,
                'site_name' => $event->room?->site?->name ?? 'Unknown',
                'room_id' => $event->room_id,
                'room_name' => $event->room?->name ?? 'Unknown',
                'service_type_id' => $event->service_type_id,
                'service_type_name' => $event->serviceType?->name ?? 'Unknown',
                'hours' => $hours,
                'trainer_fee' => (float) ($event->trainer_fee_brutto ?? 0),
                'entry_fee' => (float) ($event->entry_fee_brutto ?? 0),
            ];
        });

        // Transform classes
        $classesData = $classes->map(function ($occurrence) {
            $hours = $occurrence->starts_at->diffInMinutes($occurrence->ends_at) / 60.0;
            $attendedCount = $occurrence->registrations()->where('status', 'attended')->count();

            return [
                'type' => 'GROUP_CLASS',
                'site_id' => $occurrence->room?->site_id,
                'site_name' => $occurrence->room?->site?->name ?? 'Unknown',
                'room_id' => $occurrence->room_id,
                'room_name' => $occurrence->room?->name ?? 'Unknown',
                'service_type_id' => null,
                'service_type_name' => 'Csoportos óra',
                'hours' => $hours,
                'trainer_fee' => 0, // Would need settlement_items lookup
                'entry_fee' => 0,
                'attended_count' => $attendedCount,
            ];
        });

        // Combine data
        $allData = $eventsData->concat($classesData);

        // Group by requested dimension
        $groupKey = match ($groupBy) {
            'site' => 'site_id',
            'room' => 'room_id',
            'service_type' => 'service_type_id',
            default => 'site_id',
        };

        $nameKey = match ($groupBy) {
            'site' => 'site_name',
            'room' => 'room_name',
            'service_type' => 'service_type_name',
            default => 'site_name',
        };

        $breakdown = $allData->groupBy($groupKey)->map(function ($group) use ($groupKey, $nameKey) {
            $first = $group->first();
            return [
                'id' => $first[$groupKey],
                'name' => $first[$nameKey],
                'total_hours' => round($group->sum('hours'), 2),
                'total_sessions' => $group->count(),
                'individual_sessions' => $group->where('type', 'INDIVIDUAL')->count(),
                'group_sessions' => $group->where('type', 'GROUP_CLASS')->count(),
                'total_trainer_fee' => $group->sum('trainer_fee'),
                'total_entry_fee' => $group->sum('entry_fee'),
            ];
        })->values();

        // Summary
        $summary = [
            'total_hours' => round($allData->sum('hours'), 2),
            'total_sessions' => $allData->count(),
            'individual_sessions' => $eventsData->count(),
            'group_sessions' => $classesData->count(),
            'total_trainer_fee' => $allData->sum('trainer_fee'),
            'total_entry_fee' => $allData->sum('entry_fee'),
            'currency' => 'HUF',
        ];

        return [
            'summary' => $summary,
            'breakdown' => $breakdown,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
            ],
        ];
    }

    /**
     * Generate Staff My Clients Report
     *
     * List of clients who had sessions with this staff member
     *
     * @param int $staffId Staff profile ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @return array
     */
    public function generateStaffMyClients(
        int $staffId,
        string $from,
        string $to
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Get clients from individual events
        $eventClients = Event::query()
            ->where('staff_id', $staffId)
            ->whereBetween('starts_at', [$fromDate, $toDate])
            ->whereNotNull('client_id')
            ->with(['client.user'])
            ->get()
            ->groupBy('client_id')
            ->map(function ($events) {
                $first = $events->first();
                $attended = $events->where('attendance_status', 'attended')->count();
                $noShow = $events->where('attendance_status', 'no_show')->count();

                return [
                    'client_id' => $first->client_id,
                    'client_name' => $first->client?->user?->name ?? 'Unknown',
                    'client_email' => $first->client?->user?->email ?? 'Unknown',
                    'individual_sessions' => $events->count(),
                    'individual_attended' => $attended,
                    'individual_no_show' => $noShow,
                    'group_sessions' => 0,
                    'group_attended' => 0,
                ];
            });

        // Get clients from class registrations
        $classClients = ClassRegistration::query()
            ->whereHas('occurrence', function ($q) use ($staffId, $fromDate, $toDate) {
                $q->where('trainer_id', $staffId)
                  ->whereBetween('starts_at', [$fromDate, $toDate]);
            })
            ->with(['client.user'])
            ->get()
            ->groupBy('client_id')
            ->map(function ($registrations) {
                $first = $registrations->first();
                $attended = $registrations->where('status', 'attended')->count();

                return [
                    'client_id' => $first->client_id,
                    'client_name' => $first->client?->user?->name ?? 'Unknown',
                    'client_email' => $first->client?->user?->email ?? 'Unknown',
                    'individual_sessions' => 0,
                    'individual_attended' => 0,
                    'individual_no_show' => 0,
                    'group_sessions' => $registrations->count(),
                    'group_attended' => $attended,
                ];
            });

        // Merge client data
        $allClientIds = $eventClients->keys()->merge($classClients->keys())->unique();

        $clients = $allClientIds->map(function ($clientId) use ($eventClients, $classClients) {
            $eventData = $eventClients->get($clientId, [
                'individual_sessions' => 0,
                'individual_attended' => 0,
                'individual_no_show' => 0,
            ]);

            $classData = $classClients->get($clientId, [
                'group_sessions' => 0,
                'group_attended' => 0,
            ]);

            // Determine client info from whichever source has it
            $clientInfo = $eventClients->get($clientId) ?? $classClients->get($clientId);

            return [
                'client_id' => $clientId,
                'client_name' => $clientInfo['client_name'] ?? 'Unknown',
                'client_email' => $clientInfo['client_email'] ?? 'Unknown',
                'individual_sessions' => $eventData['individual_sessions'] ?? 0,
                'individual_attended' => $eventData['individual_attended'] ?? 0,
                'individual_no_show' => $eventData['individual_no_show'] ?? 0,
                'group_sessions' => $classData['group_sessions'] ?? 0,
                'group_attended' => $classData['group_attended'] ?? 0,
                'total_sessions' => ($eventData['individual_sessions'] ?? 0) + ($classData['group_sessions'] ?? 0),
            ];
        })->sortByDesc('total_sessions')->values();

        // Summary
        $summary = [
            'total_clients' => $clients->count(),
            'total_sessions' => $clients->sum('total_sessions'),
            'total_individual' => $clients->sum('individual_sessions'),
            'total_group' => $clients->sum('group_sessions'),
        ];

        return [
            'summary' => $summary,
            'clients' => $clients,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * Generate Staff My Trends Report
     *
     * Time-series activity for this staff member
     *
     * @param int $staffId Staff profile ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $granularity 'week' or 'month'
     * @return array
     */
    public function generateStaffMyTrends(
        int $staffId,
        string $from,
        string $to,
        string $granularity
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $filters = [
            'from' => $fromDate,
            'to' => $toDate,
            'granularity' => $granularity,
            'trainer_id' => $staffId,
        ];

        // Use existing trends method with trainer filter
        $trendsData = $this->trends($filters);

        // Summary
        $summary = [
            'total_sessions' => $trendsData->sum('total_sessions'),
            'total_hours' => round($trendsData->sum('total_hours'), 2),
            'total_entry_fee' => $trendsData->sum('total_entry_fee'),
            'total_trainer_fee' => $trendsData->sum('total_trainer_fee'),
            'average_attendance_rate' => $trendsData->count() > 0
                ? round($trendsData->avg('attendance_rate'), 2)
                : 0,
            'currency' => 'HUF',
        ];

        return [
            'summary' => $summary,
            'periods' => $trendsData,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'granularity' => $granularity,
            ],
        ];
    }

    // =========================================================================
    // CLIENT REPORT METHODS (Scoped to authenticated client)
    // =========================================================================

    /**
     * Generate Client My Activity Report
     *
     * Activity summary for authenticated client grouped by service_type or month
     *
     * @param int $clientId Client ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $groupBy 'service_type' or 'month'
     * @return array
     */
    public function generateClientMyActivity(
        int $clientId,
        string $from,
        string $to,
        string $groupBy
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Get individual events for this client
        $events = Event::query()
            ->where('client_id', $clientId)
            ->whereBetween('starts_at', [$fromDate, $toDate])
            ->with(['serviceType', 'room', 'staff.user'])
            ->get();

        // Get class registrations for this client
        $registrations = ClassRegistration::query()
            ->where('client_id', $clientId)
            ->whereHas('occurrence', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('starts_at', [$fromDate, $toDate]);
            })
            ->with(['occurrence.template', 'occurrence.room', 'occurrence.trainer.user'])
            ->get();

        // Transform events
        $eventsData = $events->map(function ($event) {
            return [
                'type' => 'INDIVIDUAL',
                'service_type_id' => $event->service_type_id,
                'service_type_name' => $event->serviceType?->name ?? 'Unknown',
                'month' => $event->starts_at->format('Y-m'),
                'attendance_status' => $event->attendance_status,
                'trainer_name' => $event->staff?->user?->name ?? 'Unknown',
                'credits_used' => 1, // Simplified
            ];
        });

        // Transform registrations
        $registrationsData = $registrations->map(function ($reg) {
            return [
                'type' => 'GROUP_CLASS',
                'service_type_id' => null,
                'service_type_name' => 'Csoportos óra',
                'month' => $reg->occurrence?->starts_at?->format('Y-m') ?? 'Unknown',
                'attendance_status' => $reg->status,
                'trainer_name' => $reg->occurrence?->trainer?->user?->name ?? 'Unknown',
                'credits_used' => 1,
            ];
        });

        // Combine
        $allData = $eventsData->concat($registrationsData);

        // Group by requested dimension
        $groupKey = $groupBy === 'month' ? 'month' : 'service_type_id';
        $nameKey = $groupBy === 'month' ? 'month' : 'service_type_name';

        $breakdown = $allData->groupBy($groupKey)->map(function ($group) use ($groupKey, $nameKey) {
            $first = $group->first();
            $attended = $group->where('attendance_status', 'attended')->count();
            $noShow = $group->where('attendance_status', 'no_show')->count();

            return [
                'id' => $first[$groupKey],
                'name' => $first[$nameKey],
                'total_sessions' => $group->count(),
                'attended' => $attended,
                'no_show' => $noShow,
                'attendance_rate' => $group->count() > 0
                    ? round(($attended / $group->count()) * 100, 2)
                    : 0,
                'credits_used' => $attended, // Credits only consumed when attended
            ];
        })->values();

        // Summary
        $totalAttended = $allData->where('attendance_status', 'attended')->count();
        $totalNoShow = $allData->where('attendance_status', 'no_show')->count();
        $totalSessions = $allData->count();

        $summary = [
            'total_sessions' => $totalSessions,
            'attended' => $totalAttended,
            'no_show' => $totalNoShow,
            'attendance_rate' => $totalSessions > 0
                ? round(($totalAttended / $totalSessions) * 100, 2)
                : 0,
            'total_credits_used' => $totalAttended,
        ];

        return [
            'summary' => $summary,
            'breakdown' => $breakdown,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
            ],
        ];
    }

    /**
     * Generate Client My Finance Report
     *
     * Finance summary for authenticated client (passes, credits, payments)
     *
     * @param int $clientId Client ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $groupBy 'month'
     * @return array
     */
    public function generateClientMyFinance(
        int $clientId,
        string $from,
        string $to,
        string $groupBy
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // Get passes purchased in this period
        $passes = \App\Models\Pass::query()
            ->where('client_id', $clientId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        // Get credit usage from events
        $creditUsage = Event::query()
            ->where('client_id', $clientId)
            ->where('attendance_status', 'attended')
            ->whereBetween('starts_at', [$fromDate, $toDate])
            ->selectRaw('DATE_FORMAT(starts_at, "%Y-%m") as month, COUNT(*) as credits_used')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Get credit usage from class registrations
        $classUsage = ClassRegistration::query()
            ->where('client_id', $clientId)
            ->where('status', 'attended')
            ->whereHas('occurrence', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('starts_at', [$fromDate, $toDate]);
            })
            ->join('class_occurrences', 'class_registrations.occurrence_id', '=', 'class_occurrences.id')
            ->selectRaw('DATE_FORMAT(class_occurrences.starts_at, "%Y-%m") as month, COUNT(*) as credits_used')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Group passes by month
        $passesByMonth = $passes->groupBy(function ($pass) {
            return $pass->created_at->format('Y-m');
        })->map(function ($monthPasses) {
            return [
                'passes_purchased' => $monthPasses->count(),
                'amount_spent' => $monthPasses->sum('price'),
                'credits_purchased' => $monthPasses->sum('total_credits'),
            ];
        });

        // Build monthly breakdown
        $allMonths = collect();

        // Collect all months from various sources
        foreach ($passesByMonth->keys() as $month) {
            $allMonths->push($month);
        }
        foreach ($creditUsage->keys() as $month) {
            $allMonths->push($month);
        }
        foreach ($classUsage->keys() as $month) {
            $allMonths->push($month);
        }

        $allMonths = $allMonths->unique()->sort()->values();

        $breakdown = $allMonths->map(function ($month) use ($passesByMonth, $creditUsage, $classUsage) {
            $passData = $passesByMonth->get($month, [
                'passes_purchased' => 0,
                'amount_spent' => 0,
                'credits_purchased' => 0,
            ]);

            $eventCredits = $creditUsage->get($month)?->credits_used ?? 0;
            $classCredits = $classUsage->get($month)?->credits_used ?? 0;

            return [
                'month' => $month,
                'passes_purchased' => $passData['passes_purchased'] ?? 0,
                'amount_spent' => $passData['amount_spent'] ?? 0,
                'credits_purchased' => $passData['credits_purchased'] ?? 0,
                'credits_used' => $eventCredits + $classCredits,
            ];
        });

        // Summary
        $summary = [
            'total_passes_purchased' => $passes->count(),
            'total_amount_spent' => $passes->sum('price'),
            'total_credits_purchased' => $passes->sum('total_credits'),
            'total_credits_used' => $breakdown->sum('credits_used'),
            'currency' => 'HUF',
        ];

        // Current pass status
        $activePasses = \App\Models\Pass::query()
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->get()
            ->map(function ($pass) {
                return [
                    'id' => $pass->id,
                    'name' => $pass->name ?? 'Pass #' . $pass->id,
                    'total_credits' => $pass->total_credits,
                    'remaining_credits' => $pass->remaining_credits,
                    'expires_at' => $pass->expires_at?->toIso8601String(),
                ];
            });

        return [
            'summary' => $summary,
            'breakdown' => $breakdown,
            'active_passes' => $activePasses,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
            ],
        ];
    }
}
