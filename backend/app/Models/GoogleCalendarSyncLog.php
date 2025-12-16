<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_config_id',
        'operation',
        'status',
        'started_at',
        'completed_at',
        'events_processed',
        'events_created',
        'events_updated',
        'events_skipped',
        'events_failed',
        'conflicts_detected',
        'filters',
        'conflicts',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'events_processed' => 'integer',
            'events_created' => 'integer',
            'events_updated' => 'integer',
            'events_skipped' => 'integer',
            'events_failed' => 'integer',
            'conflicts_detected' => 'integer',
            'filters' => 'array',
            'conflicts' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function syncConfig(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarSyncConfig::class, 'sync_config_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts_detected > 0;
    }
}
