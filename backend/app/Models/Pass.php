<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pass extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'type',
        'total_credits',
        'credits_left',
        'valid_from',
        'valid_until',
        'source',
        'status',
        'woo_order_id',
        'stripe_payment_id',
        'external_order_id',
        'external_reference',
        'pass_type',
        'price',
        'purchased_at',
        'expires_at',
        'credits_total',
        'credits_remaining',
    ];

    protected function casts(): array
    {
        return [
            'total_credits' => 'integer',
            'credits_left' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('credits_left', '>', 0)
                     ->where('valid_from', '<=', now())
                     ->where(function ($q) {
                         $q->whereNull('valid_until')
                           ->orWhere('valid_until', '>=', now());
                     });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where(function ($q) {
                         $q->where('credits_left', '<=', 0)
                           ->orWhere('valid_until', '<', now());
                     });
    }

    public function isActive(): bool
    {
        return $this->status === 'active' 
            && $this->credits_left > 0 
            && $this->valid_from <= now()
            && (is_null($this->valid_until) || $this->valid_until >= now());
    }
}
