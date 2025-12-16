<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassOccurrence extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'template_id',
        'trainer_id',
        'room_id',
        'starts_at',
        'ends_at',
        'status',
        'google_event_id',
        'capacity',
    ];

    protected $appends = [
        'available_spots',
        'waitlist_count',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClassTemplate::class, 'template_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'trainer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClassRegistration::class, 'occurrence_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now())
                     ->where('status', 'scheduled')
                     ->orderBy('starts_at');
    }

    public function scopeForRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeWithinDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->where('starts_at', '>=', $from)
                     ->where('ends_at', '<=', $to);
    }

    public function getAvailableCapacityAttribute(): int
    {
        $capacity = $this->capacity;
        $bookedCount = $this->registrations()
            ->whereIn('status', ['booked', 'attended'])
            ->count();

        return max(0, $capacity - $bookedCount);
    }

    public function getAvailableSpotsAttribute(): int
    {
        return $this->available_capacity;
    }

    public function getWaitlistCountAttribute(): int
    {
        return $this->registrations()
            ->where('status', 'waitlist')
            ->count();
    }

    public function clientPricing(): HasMany
    {
        return $this->hasMany(ClientClassPricing::class, 'class_occurrence_id');
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'class_occurrence_id');
    }
}
