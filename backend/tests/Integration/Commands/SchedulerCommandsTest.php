<?php

declare(strict_types=1);

use App\Jobs\SendClassReminder;
use App\Models\BillingRule;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\ClassTemplate;
use App\Models\Client;
use App\Models\Event;
use App\Models\Payout;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('GenerateRecurringClasses Command', function () {
    beforeEach(function () {
        // Set timezone for consistent date handling
        config(['app.timezone' => 'Europe/Budapest']);

        // Create test data
        $this->room = Room::factory()->create();
        $this->trainer = StaffProfile::factory()->create();
    });

    it('generates class occurrences based on weekly RRULE', function () {
        // Arrange: Create class template with weekly recurrence (Mon, Wed, Fri at 18:00)
        $template = ClassTemplate::factory()->create([
            'title' => 'Evening Yoga',
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR;BYHOUR=18;BYMINUTE=0',
            'duration_minutes' => 60,
            'default_capacity' => 15,
            'default_room_id' => $this->room->id,
            'default_trainer_id' => $this->trainer->id,
            'is_active' => true,
        ]);

        // Act: Run the command to generate occurrences for next 14 days
        $exitCode = Artisan::call('classes:generate-recurring', ['--days' => 14]);

        // Assert: Command executed successfully
        expect($exitCode)->toBe(0);

        // Assert: Class occurrences were created
        // In 14 days, there should be ~6 occurrences (2 weeks × 3 days per week)
        $occurrenceCount = ClassOccurrence::where('class_template_id', $template->id)->count();
        expect($occurrenceCount)->toBeGreaterThanOrEqual(4);
        expect($occurrenceCount)->toBeLessThanOrEqual(8);

        // Assert: Occurrences have correct time (18:00)
        $occurrences = ClassOccurrence::where('class_template_id', $template->id)->get();
        foreach ($occurrences as $occurrence) {
            expect($occurrence->starts_at->hour)->toBe(18);
            expect($occurrence->starts_at->minute)->toBe(0);
            expect($occurrence->max_capacity)->toBe(15);
            expect($occurrence->room_id)->toBe($this->room->id);
            expect($occurrence->trainer_id)->toBe($this->trainer->id);
            expect($occurrence->status)->toBe('scheduled');
        }
    });

    it('prevents duplicate occurrences when run multiple times', function () {
        // Arrange: Create class template
        $template = ClassTemplate::factory()->create([
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO,WE;BYHOUR=10;BYMINUTE=0',
            'duration_minutes' => 45,
            'default_capacity' => 10,
            'default_room_id' => $this->room->id,
            'default_trainer_id' => $this->trainer->id,
            'is_active' => true,
        ]);

        // Act: Run command twice
        Artisan::call('classes:generate-recurring', ['--days' => 7]);
        $firstRunCount = ClassOccurrence::where('class_template_id', $template->id)->count();

        Artisan::call('classes:generate-recurring', ['--days' => 7]);
        $secondRunCount = ClassOccurrence::where('class_template_id', $template->id)->count();

        // Assert: No duplicate occurrences created
        expect($firstRunCount)->toBe($secondRunCount);
        expect($firstRunCount)->toBeGreaterThan(0);
    });

    it('handles timezone correctly for Europe/Budapest', function () {
        // Arrange: Create class template for specific time
        $template = ClassTemplate::factory()->create([
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU;BYHOUR=9;BYMINUTE=30',
            'duration_minutes' => 60,
            'default_room_id' => $this->room->id,
            'default_trainer_id' => $this->trainer->id,
            'is_active' => true,
        ]);

        // Act: Generate occurrences
        Artisan::call('classes:generate-recurring', ['--days' => 14]);

        // Assert: Times are in Europe/Budapest timezone
        $occurrence = ClassOccurrence::where('class_template_id', $template->id)->first();
        expect($occurrence)->not->toBeNull();
        expect($occurrence->starts_at->hour)->toBe(9);
        expect($occurrence->starts_at->minute)->toBe(30);
        expect($occurrence->starts_at->timezone->getName())->toBe('Europe/Budapest');
    });

    it('ignores inactive templates', function () {
        // Arrange: Create inactive class template
        $inactiveTemplate = ClassTemplate::factory()->create([
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR;BYHOUR=18;BYMINUTE=0',
            'is_active' => false,
        ]);

        // Act: Run command
        Artisan::call('classes:generate-recurring', ['--days' => 14]);

        // Assert: No occurrences generated for inactive template
        $count = ClassOccurrence::where('class_template_id', $inactiveTemplate->id)->count();
        expect($count)->toBe(0);
    });
});

describe('SendDailyReminders Command', function () {
    beforeEach(function () {
        Queue::fake();

        // Create test data
        $this->room = Room::factory()->create();
        $this->trainer = StaffProfile::factory()->create();
        $this->template = ClassTemplate::factory()->create();
    });

    it('dispatches reminder jobs for classes 24 hours ahead', function () {
        // Arrange: Create class occurrence exactly 24 hours from now
        $occurrence = ClassOccurrence::factory()->create([
            'template_id' => $this->template->id,
            'trainer_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        // Create confirmed registrations
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->client()->create();
            $client = Client::factory()->create(['user_id' => $user->id]);
            ClassRegistration::factory()->create([
                'occurrence_id' => $occurrence->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
            ]);
        }

        // Act: Run daily reminders command
        $exitCode = Artisan::call('schedule:send-daily-reminders');

        // Assert: Command executed successfully
        expect($exitCode)->toBe(0);

        // Assert: SendClassReminder job was dispatched
        Queue::assertPushed(SendClassReminder::class, function ($job) use ($occurrence) {
            return $job->occurrence->id === $occurrence->id;
        });
    });

    it('does not send reminders for classes outside 24h window', function () {
        // Arrange: Create class occurrence 2 days ahead (outside 24h window)
        $futureOccurrence = ClassOccurrence::factory()->create([
            'template_id' => $this->template->id,
            'trainer_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDays(2),
            'ends_at' => Carbon::now()->addDays(2)->addHour(),
        ]);

        // Create confirmed registration
        $user = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $futureOccurrence->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);

        // Act: Run command
        Artisan::call('schedule:send-daily-reminders');

        // Assert: No reminder job dispatched for future class
        Queue::assertNotPushed(SendClassReminder::class, function ($job) use ($futureOccurrence) {
            return $job->occurrence->id === $futureOccurrence->id;
        });
    });

    it('only sends reminders to confirmed bookings', function () {
        // Arrange: Create occurrence 24h ahead
        $occurrence = ClassOccurrence::factory()->create([
            'template_id' => $this->template->id,
            'trainer_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
        ]);

        // Create different registration statuses
        $confirmedUser = User::factory()->client()->create();
        $confirmedClient = Client::factory()->create(['user_id' => $confirmedUser->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $confirmedClient->id,
            'status' => 'confirmed',
        ]);

        $waitlistUser = User::factory()->client()->create();
        $waitlistClient = Client::factory()->create(['user_id' => $waitlistUser->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $waitlistClient->id,
            'status' => 'waitlist',
        ]);

        $cancelledUser = User::factory()->client()->create();
        $cancelledClient = Client::factory()->create(['user_id' => $cancelledUser->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $cancelledClient->id,
            'status' => 'cancelled',
        ]);

        // Act: Run command
        Artisan::call('schedule:send-daily-reminders');

        // Assert: Only one job dispatched (for confirmed booking)
        Queue::assertPushed(SendClassReminder::class, 1);
    });
});

describe('CalculateMonthlyPayouts Command', function () {
    beforeEach(function () {
        // Set consistent date for testing (middle of a month)
        Carbon::setTestNow(Carbon::parse('2025-11-15 12:00:00'));

        // Create test data
        $this->staff1 = StaffProfile::factory()->create();
        $this->staff2 = StaffProfile::factory()->create();
        $this->room = Room::factory()->create();

        // Create billing rules for staff
        BillingRule::factory()->create([
            'staff_id' => $this->staff1->id,
            'rate_type' => 'hourly',
            'rate_amount' => 5000, // 5000 HUF per hour
        ]);

        BillingRule::factory()->create([
            'staff_id' => $this->staff2->id,
            'rate_type' => 'hourly',
            'rate_amount' => 6000, // 6000 HUF per hour
        ]);
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset time
    });

    it('calculates payouts for previous month events', function () {
        // Arrange: Create completed events in previous month (October 2025)
        // Staff 1: 5 events × 1 hour = 5 hours × 5000 HUF = 25,000 HUF
        for ($i = 0; $i < 5; $i++) {
            $event = Event::factory()->create([
                'staff_id' => $this->staff1->id,
                'room_id' => $this->room->id,
                'type' => 'INDIVIDUAL',
                'status' => 'completed',
                'starts_at' => Carbon::parse('2025-10-' . (5 + $i) . ' 10:00:00'),
                'ends_at' => Carbon::parse('2025-10-' . (5 + $i) . ' 11:00:00'),
            ]);

            // Mark event with check-in (attended)
            $event->update(['checked_in_at' => $event->starts_at]);
        }

        // Staff 2: 3 events × 1.5 hours = 4.5 hours × 6000 HUF = 27,000 HUF
        for ($i = 0; $i < 3; $i++) {
            $event = Event::factory()->create([
                'staff_id' => $this->staff2->id,
                'room_id' => $this->room->id,
                'type' => 'INDIVIDUAL',
                'status' => 'completed',
                'starts_at' => Carbon::parse('2025-10-' . (10 + $i) . ' 14:00:00'),
                'ends_at' => Carbon::parse('2025-10-' . (10 + $i) . ' 15:30:00'),
            ]);

            $event->update(['checked_in_at' => $event->starts_at]);
        }

        // Act: Run payout calculation command
        $exitCode = Artisan::call('schedule:calculate-monthly-payouts');

        // Assert: Command executed successfully
        expect($exitCode)->toBe(0);

        // Assert: Payout records created for October 2025
        $this->assertDatabaseHas('payouts', [
            'staff_id' => $this->staff1->id,
            'period_year' => 2025,
            'period_month' => 10,
            'total_hours' => 5.0,
            'total_amount' => 25000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('payouts', [
            'staff_id' => $this->staff2->id,
            'period_year' => 2025,
            'period_month' => 10,
            'total_hours' => 4.5,
            'total_amount' => 27000,
            'status' => 'pending',
        ]);
    });

    it('prevents duplicate payout calculations for same month', function () {
        // Arrange: Create event in previous month
        $event = Event::factory()->create([
            'staff_id' => $this->staff1->id,
            'room_id' => $this->room->id,
            'status' => 'completed',
            'starts_at' => Carbon::parse('2025-10-15 10:00:00'),
            'ends_at' => Carbon::parse('2025-10-15 11:00:00'),
        ]);
        $event->update(['checked_in_at' => $event->starts_at]);

        // Act: Run command twice
        Artisan::call('schedule:calculate-monthly-payouts');
        $firstRunCount = Payout::where('staff_id', $this->staff1->id)
            ->where('period_year', 2025)
            ->where('period_month', 10)
            ->count();

        Artisan::call('schedule:calculate-monthly-payouts');
        $secondRunCount = Payout::where('staff_id', $this->staff1->id)
            ->where('period_year', 2025)
            ->where('period_month', 10)
            ->count();

        // Assert: No duplicate payout created
        expect($firstRunCount)->toBe(1);
        expect($secondRunCount)->toBe(1);
    });

    it('only calculates payouts for checked-in events', function () {
        // Arrange: Create events in previous month with different statuses
        // Checked-in event (should be counted)
        $checkedInEvent = Event::factory()->create([
            'staff_id' => $this->staff1->id,
            'room_id' => $this->room->id,
            'status' => 'completed',
            'starts_at' => Carbon::parse('2025-10-20 10:00:00'),
            'ends_at' => Carbon::parse('2025-10-20 11:00:00'),
        ]);
        $checkedInEvent->update(['checked_in_at' => $checkedInEvent->starts_at]);

        // Not checked-in event (should NOT be counted)
        Event::factory()->create([
            'staff_id' => $this->staff1->id,
            'room_id' => $this->room->id,
            'status' => 'completed',
            'starts_at' => Carbon::parse('2025-10-21 10:00:00'),
            'ends_at' => Carbon::parse('2025-10-21 11:00:00'),
            'checked_in_at' => null,
        ]);

        // Cancelled event (should NOT be counted)
        Event::factory()->create([
            'staff_id' => $this->staff1->id,
            'room_id' => $this->room->id,
            'status' => 'cancelled',
            'starts_at' => Carbon::parse('2025-10-22 10:00:00'),
            'ends_at' => Carbon::parse('2025-10-22 11:00:00'),
        ]);

        // Act: Run command
        Artisan::call('schedule:calculate-monthly-payouts');

        // Assert: Only checked-in event counted (1 hour × 5000 = 5000)
        $this->assertDatabaseHas('payouts', [
            'staff_id' => $this->staff1->id,
            'period_year' => 2025,
            'period_month' => 10,
            'total_hours' => 1.0,
            'total_amount' => 5000,
        ]);
    });
});
