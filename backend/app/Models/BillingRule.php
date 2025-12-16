<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'rate_type',
        'rate_value',
        'applies_to',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'rate_value' => 'decimal:2',
            'effective_from' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
