<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientClassPricing extends Model
{
    use HasFactory;

    protected $table = 'client_class_pricing';

    protected $fillable = [
        'client_id',
        'class_template_id',
        'class_occurrence_id',
        'entry_fee_brutto',
        'trainer_fee_brutto',
        'currency',
        'valid_from',
        'valid_until',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_brutto' => 'integer',
            'trainer_fee_brutto' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function classTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassTemplate::class, 'class_template_id');
    }

    public function classOccurrence(): BelongsTo
    {
        return $this->belongsTo(ClassOccurrence::class, 'class_occurrence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /**
     * Scope to filter by client and occurrence.
     */
    public function scopeForClientAndOccurrence($query, int $clientId, int $occurrenceId)
    {
        return $query->where('client_id', $clientId)
            ->where('class_occurrence_id', $occurrenceId);
    }

    /**
     * Scope to filter by client and template.
     */
    public function scopeForClientAndTemplate($query, int $clientId, int $templateId)
    {
        return $query->where('client_id', $clientId)
            ->where('class_template_id', $templateId);
    }
}
