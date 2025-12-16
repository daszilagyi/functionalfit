<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_entry_fee_brutto',
        'default_trainer_fee_brutto',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_entry_fee_brutto' => 'integer',
            'default_trainer_fee_brutto' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Client price codes for this service type.
     */
    public function clientPriceCodes(): HasMany
    {
        return $this->hasMany(ClientPriceCode::class);
    }

    /**
     * Events associated with this service type.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Scope to filter by active status.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find by code.
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}
