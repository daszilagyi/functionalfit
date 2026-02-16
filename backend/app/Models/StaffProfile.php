<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'bio',
        'specialization',
        'default_hourly_rate',
        'is_available_for_booking',
        'skills',
        'default_site',
        'visibility',
        'email_reminder_24h',
        'email_reminder_2h',
        'daily_schedule_notification',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'visibility' => 'boolean',
            'default_hourly_rate' => 'decimal:2',
            'is_available_for_booking' => 'boolean',
            'email_reminder_24h' => 'boolean',
            'email_reminder_2h' => 'boolean',
            'daily_schedule_notification' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'visibility' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'staff_id');
    }

    public function classTemplates(): HasMany
    {
        return $this->hasMany(ClassTemplate::class, 'trainer_id');
    }

    public function classOccurrences(): HasMany
    {
        return $this->hasMany(ClassOccurrence::class, 'trainer_id');
    }

    public function priceCodes(): HasMany
    {
        return $this->hasMany(StaffPriceCode::class);
    }
}
