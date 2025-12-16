<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'site',  // Legacy field (SQLite compatibility)
        'site_id',
        'name',
        'google_calendar_id',
        'color',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'capacity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot method to automatically set the legacy 'site' field
     */
    protected static function booted(): void
    {
        static::saving(function (Room $room) {
            // Auto-populate legacy 'site' field from site_id
            if ($room->site_id && !$room->getAttribute('site')) {
                $site = Site::find($room->site_id);
                if ($site) {
                    $room->setAttribute('site', $site->name);
                }
            }
        });
    }

    /**
     * Relationships
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function classOccurrences(): HasMany
    {
        return $this->hasMany(ClassOccurrence::class);
    }

    public function classTemplates(): HasMany
    {
        return $this->hasMany(ClassTemplate::class);
    }
}
