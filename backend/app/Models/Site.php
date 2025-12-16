<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'description',
        'opening_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot method - auto-generate slug from name
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($site) {
            if (empty($site->slug)) {
                $site->slug = Str::slug($site->name);
            }
        });

        static::updating(function ($site) {
            if ($site->isDirty('name') && empty($site->slug)) {
                $site->slug = Str::slug($site->name);
            }
        });
    }

    /**
     * Scope: Active sites only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relationships
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
