<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalendarChangeLog;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarChangeLogFactory extends Factory
{
    protected $model = CalendarChangeLog::class;

    public function definition(): array
    {
        $event = Event::factory()->create();
        $actor = User::factory()->create(['role' => 'admin']);

        $startsAt = fake()->dateTimeBetween('now', '+30 days');
        $endsAt = Carbon::parse($startsAt)->addHour();

        return [
            'changed_at' => now(),
            'action' => fake()->randomElement([
                CalendarChangeLog::ACTION_EVENT_CREATED,
                CalendarChangeLog::ACTION_EVENT_UPDATED,
                CalendarChangeLog::ACTION_EVENT_DELETED,
            ]),
            'entity_type' => CalendarChangeLog::ENTITY_TYPE_EVENT,
            'entity_id' => $event->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => 'admin',
            'site' => $event->room?->site?->name ?? 'SASAD',
            'room_id' => $event->room_id,
            'room_name' => $event->room?->name ?? 'Test Room',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'before_json' => null,
            'after_json' => null,
            'changed_fields' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    /**
     * State: Event creation log
     * before_json is null for creation
     */
    public function created(): static
    {
        return $this->state(function (array $attributes) {
            $snapshot = [
                'id' => $attributes['entity_id'],
                'title' => 'INDIVIDUAL',
                'type' => 'INDIVIDUAL',
                'starts_at' => Carbon::parse($attributes['starts_at'])->toIso8601String(),
                'ends_at' => Carbon::parse($attributes['ends_at'])->toIso8601String(),
                'site' => $attributes['site'],
                'room_id' => $attributes['room_id'],
                'room_name' => $attributes['room_name'],
                'trainer_id' => 1,
                'trainer_name' => 'Test Trainer',
                'client_id' => 1,
                'client_email' => 'client@example.com',
                'service_type_id' => 1,
                'service_type_code' => 'PT',
                'status' => 'scheduled',
                'attendance_status' => null,
                'notes' => null,
                'entry_fee_brutto' => 10000,
                'trainer_fee_brutto' => 7000,
                'currency' => 'HUF',
            ];

            return [
                'action' => CalendarChangeLog::ACTION_EVENT_CREATED,
                'before_json' => null,
                'after_json' => $snapshot,
                'changed_fields' => null,
            ];
        });
    }

    /**
     * State: Event update log
     * Both before_json and after_json populated, with changed_fields array
     */
    public function updated(): static
    {
        return $this->state(function (array $attributes) {
            $baseSnapshot = [
                'id' => $attributes['entity_id'],
                'title' => 'INDIVIDUAL',
                'type' => 'INDIVIDUAL',
                'site' => $attributes['site'],
                'room_id' => $attributes['room_id'],
                'room_name' => $attributes['room_name'],
                'trainer_id' => 1,
                'trainer_name' => 'Test Trainer',
                'client_id' => 1,
                'client_email' => 'client@example.com',
                'service_type_id' => 1,
                'service_type_code' => 'PT',
                'status' => 'scheduled',
                'attendance_status' => null,
                'notes' => null,
                'entry_fee_brutto' => 10000,
                'trainer_fee_brutto' => 7000,
                'currency' => 'HUF',
            ];

            $oldStartsAt = Carbon::parse($attributes['starts_at'])->subHour();
            $oldEndsAt = Carbon::parse($attributes['ends_at'])->subHour();

            $beforeSnapshot = array_merge($baseSnapshot, [
                'starts_at' => $oldStartsAt->toIso8601String(),
                'ends_at' => $oldEndsAt->toIso8601String(),
            ]);

            $afterSnapshot = array_merge($baseSnapshot, [
                'starts_at' => Carbon::parse($attributes['starts_at'])->toIso8601String(),
                'ends_at' => Carbon::parse($attributes['ends_at'])->toIso8601String(),
            ]);

            return [
                'action' => CalendarChangeLog::ACTION_EVENT_UPDATED,
                'before_json' => $beforeSnapshot,
                'after_json' => $afterSnapshot,
                'changed_fields' => ['starts_at', 'ends_at'],
            ];
        });
    }

    /**
     * State: Event deletion log
     * after_json is null for deletion
     */
    public function deleted(): static
    {
        return $this->state(function (array $attributes) {
            $snapshot = [
                'id' => $attributes['entity_id'],
                'title' => 'INDIVIDUAL',
                'type' => 'INDIVIDUAL',
                'starts_at' => Carbon::parse($attributes['starts_at'])->toIso8601String(),
                'ends_at' => Carbon::parse($attributes['ends_at'])->toIso8601String(),
                'site' => $attributes['site'],
                'room_id' => $attributes['room_id'],
                'room_name' => $attributes['room_name'],
                'trainer_id' => 1,
                'trainer_name' => 'Test Trainer',
                'client_id' => 1,
                'client_email' => 'client@example.com',
                'service_type_id' => 1,
                'service_type_code' => 'PT',
                'status' => 'cancelled',
                'attendance_status' => null,
                'notes' => null,
                'entry_fee_brutto' => 10000,
                'trainer_fee_brutto' => 7000,
                'currency' => 'HUF',
            ];

            return [
                'action' => CalendarChangeLog::ACTION_EVENT_DELETED,
                'before_json' => $snapshot,
                'after_json' => null,
                'changed_fields' => null,
            ];
        });
    }

    /**
     * State: For a specific actor user
     */
    public function byActor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role ?? 'staff',
        ]);
    }

    /**
     * State: For a specific room
     */
    public function inRoom(Room $room): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'site' => $room->site?->name ?? $room->site ?? 'SASAD',
        ]);
    }

    /**
     * State: For a specific site
     */
    public function atSite(string $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site' => $site,
        ]);
    }

    /**
     * State: Within a specific date range
     */
    public function changedAt(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'changed_at' => $date,
            'created_at' => $date,
        ]);
    }
}
