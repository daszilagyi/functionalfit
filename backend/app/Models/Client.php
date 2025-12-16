<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Accessors to append to the model's array form.
     */
    protected $appends = ['is_technical_guest'];

    protected $fillable = [
        'user_id',
        'full_name',
        'date_of_birth',
        'emergency_contact_name',
        'emergency_contact_phone',
        'date_of_joining',
        'notes',
        'gdpr_consent_at',
        'unpaid_balance',
        'email_reminder_24h',
        'email_reminder_2h',
        'gcal_sync_enabled',
        'gcal_calendar_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'gdpr_consent_at' => 'datetime',
            'unpaid_balance' => 'decimal:2',
            'email_reminder_24h' => 'boolean',
            'email_reminder_2h' => 'boolean',
            'gcal_sync_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function classRegistrations(): HasMany
    {
        return $this->hasMany(ClassRegistration::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Events where this client is an additional participant.
     * Does not include events where this client is the main client (client_id).
     */
    public function additionalEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_additional_clients')
            ->withTimestamps();
    }

    /**
     * Check if this client is the special "Technical Guest" client.
     * Technical Guest is used for unknown/walk-in participants.
     * This is both an accessor (for $appends) and a regular method.
     */
    public function getIsTechnicalGuestAttribute(): bool
    {
        return $this->id === self::getTechnicalGuestId();
    }

    /**
     * Alias method for convenience.
     */
    public function isTechnicalGuest(): bool
    {
        return $this->getIsTechnicalGuestAttribute();
    }

    /**
     * Get the ID of the technical guest client.
     * Returns null if not configured.
     */
    public static function getTechnicalGuestId(): ?int
    {
        $guestIdValue = DB::table('settings')
            ->where('key', 'technical_guest_client_id')
            ->value('value');

        if ($guestIdValue === null) {
            return null;
        }

        return (int) json_decode($guestIdValue);
    }

    /**
     * Get the technical guest Client instance.
     * Returns null if not found.
     */
    public static function getTechnicalGuest(): ?self
    {
        $id = self::getTechnicalGuestId();

        if ($id === null) {
            return null;
        }

        return self::find($id);
    }

    public function clientClassPricing(): HasMany
    {
        return $this->hasMany(ClientClassPricing::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    /**
     * Price codes for this client (service-type-based pricing).
     */
    public function priceCodes(): HasMany
    {
        return $this->hasMany(ClientPriceCode::class);
    }
}
