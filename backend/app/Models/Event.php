<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'status',
        'attendance_status',
        'checked_in_at',
        'staff_id',
        'client_id',
        'room_id',
        'pricing_id',
        'service_type_id',
        'entry_fee_brutto',
        'trainer_fee_brutto',
        'currency',
        'price_source',
        'starts_at',
        'ends_at',
        'google_event_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Accessors to append to the model's array form.
     */
    protected $appends = ['expanded_additional_clients'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'entry_fee_brutto' => 'integer',
            'trainer_fee_brutto' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Additional clients (participants) for this event.
     * The main client is stored in client_id, additional participants are in this pivot table.
     * Each guest (including multiple technical guests) has a separate row with guest_index.
     */
    public function additionalClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'event_additional_clients')
            ->withPivot(
                'guest_index',
                'quantity',
                'attendance_status',
                'checked_in_at',
                'entry_fee_brutto',
                'trainer_fee_brutto',
                'currency',
                'price_source'
            )
            ->withTimestamps();
    }

    /**
     * Get expanded additional clients, where each client is repeated based on their quantity.
     * This is used to represent multiple Technical Guests as separate instances.
     * Returns a collection of Client models.
     */
    public function getExpandedAdditionalClientsAttribute(): Collection
    {
        $expanded = collect();

        foreach ($this->additionalClients as $client) {
            $quantity = $client->pivot->quantity ?? 1;
            for ($i = 0; $i < $quantity; $i++) {
                $expanded->push($client);
            }
        }

        return $expanded;
    }

    /**
     * Get all clients for this event (main client + additional clients).
     * Returns a collection of Client models with quantities expanded.
     */
    public function allClients(): Collection
    {
        $clients = collect();

        // Add main client if present
        if ($this->client) {
            $clients->push($this->client);
        }

        // Merge with expanded additional clients (respects quantity)
        return $clients->merge($this->expandedAdditionalClients);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(ClassPricingDefault::class, 'pricing_id');
    }

    /**
     * Service type for this event (used for service-type-based pricing).
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function eventChanges(): HasMany
    {
        return $this->hasMany(EventChange::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now())
                     ->where('status', 'scheduled')
                     ->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now())
                     ->orderBy('starts_at', 'desc');
    }

    public function scopeForRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeWithinDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->where('starts_at', '>=', $from)
                     ->where('ends_at', '<=', $to);
    }

    /**
     * Scope: Filter by attendance status
     */
    public function scopeAttended(Builder $query): Builder
    {
        return $query->where('attendance_status', 'attended');
    }

    /**
     * Scope: Filter by no-show status
     */
    public function scopeNoShow(Builder $query): Builder
    {
        return $query->where('attendance_status', 'no_show');
    }

    /**
     * Scope: Filter by service type
     */
    public function scopeForServiceType(Builder $query, int $serviceTypeId): Builder
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope: Filter by site (via room relationship)
     */
    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->whereHas('room', function ($q) use ($siteId) {
            $q->where('site_id', $siteId);
        });
    }

    /**
     * Scope: Calculate total hours for query result
     * Returns: SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0)
     */
    public function scopeWithTotalHours(Builder $query): Builder
    {
        return $query->selectRaw('TIMESTAMPDIFF(MINUTE, starts_at, ends_at) / 60.0 as hours');
    }

    /**
     * Scope: Only individual sessions (exclude BLOCK events)
     */
    public function scopeIndividualOnly(Builder $query): Builder
    {
        return $query->where('type', 'INDIVIDUAL');
    }
}
