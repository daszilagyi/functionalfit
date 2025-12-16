<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarChangeLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'calendar_change_log';

    /**
     * Indicates if the model should be timestamped.
     * Audit logs are immutable - only created_at is used.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Action type constants.
     */
    public const ACTION_EVENT_CREATED = 'EVENT_CREATED';
    public const ACTION_EVENT_UPDATED = 'EVENT_UPDATED';
    public const ACTION_EVENT_DELETED = 'EVENT_DELETED';

    /**
     * Entity type constants.
     */
    public const ENTITY_TYPE_EVENT = 'event';
    public const ENTITY_TYPE_CLASS_OCCURRENCE = 'class_occurrence';

    /**
     * Role constants (for actor_role field).
     */
    public const ROLE_CLIENT = 'client';
    public const ROLE_STAFF = 'staff';
    public const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'changed_at',
        'action',
        'entity_type',
        'entity_id',
        'actor_user_id',
        'actor_name',
        'actor_role',
        'site',
        'room_id',
        'room_name',
        'starts_at',
        'ends_at',
        'before_json',
        'after_json',
        'changed_fields',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * All available action types.
     *
     * @return array<int, string>
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_EVENT_CREATED,
            self::ACTION_EVENT_UPDATED,
            self::ACTION_EVENT_DELETED,
        ];
    }

    /**
     * All available entity types.
     *
     * @return array<int, string>
     */
    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_TYPE_EVENT,
            self::ENTITY_TYPE_CLASS_OCCURRENCE,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'before_json' => 'array',
            'after_json' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Get the room if applicable.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * Scope a query to only include event creations.
     */
    public function scopeCreated(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_EVENT_CREATED);
    }

    /**
     * Scope a query to only include event updates.
     */
    public function scopeUpdated(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_EVENT_UPDATED);
    }

    /**
     * Scope a query to only include event deletions.
     */
    public function scopeDeleted(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_EVENT_DELETED);
    }

    /**
     * Scope: Filter by actor user ID.
     */
    public function scopeByActor(Builder $query, int $actorUserId): Builder
    {
        return $query->where('actor_user_id', $actorUserId);
    }

    /**
     * Scope: Filter by room.
     */
    public function scopeByRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Scope: Filter by site.
     */
    public function scopeBySite(Builder $query, string $site): Builder
    {
        return $query->where('site', $site);
    }

    /**
     * Scope: Filter by action type.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by entity type.
     */
    public function scopeByEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope a query to filter by entity.
     */
    public function scopeByEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeChangedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('changed_at', [$from, $to]);
    }

    /**
     * Scope a query to filter by event time range.
     */
    public function scopeEventTimeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('starts_at', [$from, $to])
                ->orWhereBetween('ends_at', [$from, $to]);
        });
    }

    /**
     * Get a summary of what changed.
     * Returns a human-readable string of changed field names.
     */
    public function getChangeSummary(): ?string
    {
        if ($this->action === self::ACTION_EVENT_CREATED) {
            return 'Event created';
        }

        if ($this->action === self::ACTION_EVENT_DELETED) {
            return 'Event deleted';
        }

        if ($this->action === self::ACTION_EVENT_UPDATED && is_array($this->changed_fields)) {
            if (empty($this->changed_fields)) {
                return 'No changes detected';
            }
            return implode(', ', $this->changed_fields) . ' changed';
        }

        return null;
    }

    /**
     * Check if this is a creation log.
     */
    public function isCreation(): bool
    {
        return $this->action === self::ACTION_EVENT_CREATED;
    }

    /**
     * Check if this is an update log.
     */
    public function isUpdate(): bool
    {
        return $this->action === self::ACTION_EVENT_UPDATED;
    }

    /**
     * Check if this is a deletion log.
     */
    public function isDeletion(): bool
    {
        return $this->action === self::ACTION_EVENT_DELETED;
    }

    /**
     * Check if a specific field was changed.
     */
    public function wasFieldChanged(string $fieldName): bool
    {
        if (!is_array($this->changed_fields)) {
            return false;
        }

        return in_array($fieldName, $this->changed_fields, true);
    }

    /**
     * Get the value of a field before the change.
     */
    public function getBeforeValue(string $fieldName): mixed
    {
        if (!is_array($this->before_json)) {
            return null;
        }

        return $this->before_json[$fieldName] ?? null;
    }

    /**
     * Get the value of a field after the change.
     */
    public function getAfterValue(string $fieldName): mixed
    {
        if (!is_array($this->after_json)) {
            return null;
        }

        return $this->after_json[$fieldName] ?? null;
    }
}
