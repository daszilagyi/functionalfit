<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'class_occurrence_id',
        'client_id',
        'registration_id',
        'entry_fee_brutto',
        'trainer_fee_brutto',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_brutto' => 'integer',
            'trainer_fee_brutto' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function classOccurrence(): BelongsTo
    {
        return $this->belongsTo(ClassOccurrence::class, 'class_occurrence_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClassRegistration::class, 'registration_id');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
