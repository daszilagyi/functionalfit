<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'occurrence_id',
        'client_id',
        'status',
        'booked_at',
        'cancelled_at',
        'credits_used',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ClassOccurrence::class, 'occurrence_id');
    }

    /**
     * Alias for occurrence relationship (used by SendDailyReminders command)
     */
    public function classOccurrence(): BelongsTo
    {
        return $this->belongsTo(ClassOccurrence::class, 'occurrence_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'registration_id');
    }

    /**
     * Scope: Filter by attendance status
     */
    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    /**
     * Scope: Filter by no-show status
     */
    public function scopeNoShow($query)
    {
        return $query->where('status', 'no_show');
    }

    /**
     * Scope: Filter by cancelled status
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Filter by booked status (confirmed or attended)
     */
    public function scopeBooked($query)
    {
        return $query->whereIn('status', ['booked', 'attended']);
    }

    /**
     * Scope: Filter registrations within a date range (via occurrence relationship)
     */
    public function scopeWithinDateRange($query, string $from, string $to)
    {
        return $query->whereHas('occurrence', function ($q) use ($from, $to) {
            $q->whereBetween('starts_at', [$from, $to]);
        });
    }

    /**
     * Scope: Filter by site (via occurrence → room relationship)
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->whereHas('occurrence.room', function ($q) use ($siteId) {
            $q->where('site_id', $siteId);
        });
    }

    /**
     * Scope: Filter by room (via occurrence relationship)
     */
    public function scopeForRoom($query, int $roomId)
    {
        return $query->whereHas('occurrence', function ($q) use ($roomId) {
            $q->where('room_id', $roomId);
        });
    }

    /**
     * Scope: Filter by trainer (via occurrence relationship)
     */
    public function scopeForTrainer($query, int $trainerId)
    {
        return $query->whereHas('occurrence', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        });
    }
}
