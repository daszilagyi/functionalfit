<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPriceCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_profile_id',
        'staff_email',
        'service_type_id',
        'price_code',
        'entry_fee_brutto',
        'trainer_fee_brutto',
        'currency',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_brutto' => 'integer',
            'trainer_fee_brutto' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The staff profile this price code belongs to.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * The service type this price code is for.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * The user who created this price code.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by active status.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by validity at a specific time.
     */
    public function scopeValidAt(Builder $query, Carbon $atTime): Builder
    {
        return $query->where('valid_from', '<=', $atTime)
            ->where(function ($q) use ($atTime) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $atTime);
            });
    }

    /**
     * Scope to filter by staff profile and service type.
     */
    public function scopeForStaffAndServiceType(Builder $query, int $staffProfileId, int $serviceTypeId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId)
            ->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope to filter by email and service type.
     */
    public function scopeForEmailAndServiceType(Builder $query, string $email, int $serviceTypeId): Builder
    {
        return $query->where('staff_email', $email)
            ->where('service_type_id', $serviceTypeId);
    }
}
