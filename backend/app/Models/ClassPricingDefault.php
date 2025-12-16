<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPricingDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'class_template_id',
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

    public function classTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassTemplate::class, 'class_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by active status.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by validity at a specific time.
     */
    public function scopeValidAt($query, \Carbon\Carbon $atTime)
    {
        return $query->where('valid_from', '<=', $atTime)
            ->where(function ($q) use ($atTime) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $atTime);
            });
    }
}
