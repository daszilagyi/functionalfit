<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'trainer_id',
        'room_id',
        'weekly_rrule',
        'duration_min',
        'capacity',
        'credits_required',
        'base_price_huf',
        'tags',
        'status',
        'is_public_visible',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'duration_min' => 'integer',
            'capacity' => 'integer',
            'credits_required' => 'integer',
            'base_price_huf' => 'decimal:2',
            'is_public_visible' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'trainer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ClassOccurrence::class, 'template_id');
    }

    public function pricingDefaults(): HasMany
    {
        return $this->hasMany(ClassPricingDefault::class, 'class_template_id');
    }

    public function clientPricing(): HasMany
    {
        return $this->hasMany(ClientClassPricing::class, 'class_template_id');
    }
}
