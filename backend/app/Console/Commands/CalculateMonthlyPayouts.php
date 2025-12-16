<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StaffProfile;
use App\Models\Event;
use App\Models\ClassOccurrence;
use App\Models\Payout;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalculateMonthlyPayouts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payouts:calculate-monthly {--month= : Month to calculate (YYYY-MM)} {--dry-run : Run without saving}';

    /**
     * The console command description.
     */
    protected $description = 'Calculate monthly payouts for staff members';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthInput = $this->option('month') ?? now()->subMonth()->format('Y-m');
        $isDryRun = $this->option('dry-run');

        try {
            $date = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Exception $e) {
            $this->error("Invalid month format. Use YYYY-MM");
            return Command::FAILURE;
        }

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        $this->info("Calculating payouts for {$startDate->format('F Y')}");
        $this->info("Period: {$startDate->toDateString()} to {$endDate->toDateString()}");

        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No data will be saved");
        }

        // Get all staff members
        $staffMembers = StaffProfile::with('user')->get();

        if ($staffMembers->isEmpty()) {
            $this->warn('No staff members found');
            return Command::SUCCESS;
        }

        $this->info("Processing {$staffMembers->count()} staff members...");
        $this->newLine();

        $totalPayout = 0;

        foreach ($staffMembers as $staff) {
            $payoutData = $this->calculateStaffPayout($staff, $startDate, $endDate);

            $this->displayPayoutSummary($staff, $payoutData);

            if (!$isDryRun && $payoutData['total_hours'] > 0) {
                $this->savePayoutRecord($staff, $startDate, $endDate, $payoutData);
            }

            $totalPayout += $payoutData['total_amount'];
        }

        $this->newLine();
        $this->info("Total payouts for {$startDate->format('F Y')}: " . number_format($totalPayout, 2) . " HUF");

        return Command::SUCCESS;
    }

    /**
     * Calculate payout for a single staff member
     */
    private function calculateStaffPayout(StaffProfile $staff, Carbon $startDate, Carbon $endDate): array
    {
        // Calculate hours from 1:1 events
        $individualEvents = Event::where('staff_id', $staff->id)
            ->where('attendance_status', 'attended')
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get();

        $individualHours = $individualEvents->sum(function ($event) {
            return $event->starts_at->floatDiffInHours($event->ends_at);
        });

        // Calculate hours from group classes
        $groupClasses = ClassOccurrence::where('trainer_id', $staff->id)
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get();

        $groupHours = $groupClasses->sum(function ($occurrence) {
            return $occurrence->starts_at->floatDiffInHours($occurrence->ends_at);
        });

        $totalHours = $individualHours + $groupHours;
        $hourlyRate = $staff->default_hourly_rate ?? 5000; // Default 5000 HUF/hour
        $totalAmount = $totalHours * $hourlyRate;

        return [
            'individual_hours' => round($individualHours, 2),
            'group_hours' => round($groupHours, 2),
            'total_hours' => round($totalHours, 2),
            'hourly_rate' => $hourlyRate,
            'total_amount' => round($totalAmount, 2),
            'individual_event_count' => $individualEvents->count(),
            'group_class_count' => $groupClasses->count(),
        ];
    }

    /**
     * Display payout summary for a staff member
     */
    private function displayPayoutSummary(StaffProfile $staff, array $payoutData): void
    {
        $this->line("─────────────────────────────────────────────────");
        $this->line("Staff: {$staff->user->name}");
        $this->line("Individual Events: {$payoutData['individual_event_count']} ({$payoutData['individual_hours']}h)");
        $this->line("Group Classes: {$payoutData['group_class_count']} ({$payoutData['group_hours']}h)");
        $this->line("Total Hours: {$payoutData['total_hours']}h @ {$payoutData['hourly_rate']} HUF/h");
        $this->info("Total Payout: " . number_format($payoutData['total_amount'], 2) . " HUF");
    }

    /**
     * Save payout record to database
     */
    private function savePayoutRecord(StaffProfile $staff, Carbon $startDate, Carbon $endDate, array $payoutData): void
    {
        Payout::create([
            'staff_id' => $staff->id,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_hours' => $payoutData['total_hours'],
            'hourly_rate' => $payoutData['hourly_rate'],
            'total_amount' => $payoutData['total_amount'],
            'status' => 'pending',
            'calculated_at' => now(),
        ]);

        $this->line("✓ Payout record saved");
    }
}
