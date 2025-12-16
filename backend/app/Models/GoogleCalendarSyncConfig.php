<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleCalendarSyncConfig extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'google_calendar_id',
        'room_id',
        'sync_enabled',
        'sync_direction',
        'service_account_json',
        'sync_options',
        'last_import_at',
        'last_export_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_enabled' => 'boolean',
            'sync_options' => 'array',
            'last_import_at' => 'datetime',
            'last_export_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(GoogleCalendarSyncLog::class, 'sync_config_id');
    }

    public function isImportEnabled(): bool
    {
        return $this->sync_enabled && in_array($this->sync_direction, ['import', 'both']);
    }

    public function isExportEnabled(): bool
    {
        return $this->sync_enabled && in_array($this->sync_direction, ['export', 'both']);
    }
}
