<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    /**
     * Email delivery status constants.
     */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Maximum number of retry attempts.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'recipient_email',
        'template_slug',
        'subject',
        'payload',
        'status',
        'error_message',
        'sent_at',
        'attempts',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include queued emails.
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    /**
     * Scope a query to only include sent emails.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope a query to only include failed emails.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to filter by recipient email.
     */
    public function scopeForRecipient(Builder $query, string $email): Builder
    {
        return $query->where('recipient_email', $email);
    }

    /**
     * Scope a query to filter by template slug.
     */
    public function scopeForTemplate(Builder $query, string $slug): Builder
    {
        return $query->where('template_slug', $slug);
    }

    /**
     * Scope a query to find emails eligible for retry.
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED)
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * Mark the email as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the email as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Increment the attempt counter.
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Check if the email can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_QUEUED
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    /**
     * Check if the email was successfully sent.
     */
    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
