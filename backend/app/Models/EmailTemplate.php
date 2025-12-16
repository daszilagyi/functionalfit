<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'subject',
        'html_body',
        'fallback_body',
        'updated_by',
        'version',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user who last updated this template.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all versions of this template.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class)->orderBy('version', 'desc');
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to find template by slug.
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Render the HTML body with the provided variables.
     *
     * Replaces {{var.name}} style placeholders with actual values.
     * Supports nested variables like {{user.name}} and simple {{variable}}.
     *
     * @param array<string, mixed> $variables
     */
    public function render(array $variables): string
    {
        return $this->renderTemplate($this->html_body, $variables);
    }

    /**
     * Render the subject with the provided variables.
     *
     * @param array<string, mixed> $variables
     */
    public function renderSubject(array $variables): string
    {
        return $this->renderTemplate($this->subject, $variables);
    }

    /**
     * Render the fallback plain text body with the provided variables.
     *
     * @param array<string, mixed> $variables
     */
    public function renderFallback(array $variables): ?string
    {
        if ($this->fallback_body === null) {
            return null;
        }

        return $this->renderTemplate($this->fallback_body, $variables);
    }

    /**
     * Create a version snapshot of the current state before updating.
     * This should be called before modifying the template.
     */
    public function createVersion(): void
    {
        EmailTemplateVersion::create([
            'email_template_id' => $this->id,
            'version' => $this->version,
            'subject' => $this->subject,
            'html_body' => $this->html_body,
            'fallback_body' => $this->fallback_body,
            'created_by' => auth()->id(),
        ]);

        // Prune old versions (keep only last 2)
        $this->pruneOldVersions();
    }

    /**
     * Restore template to a specific version.
     */
    public function restoreFromVersion(EmailTemplateVersion $version): void
    {
        // Create version snapshot before restore
        $this->createVersion();

        $this->update([
            'subject' => $version->subject,
            'html_body' => $version->html_body,
            'fallback_body' => $version->fallback_body,
            'version' => $this->version + 1,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Increment version number for tracking changes.
     */
    public function incrementVersion(): void
    {
        $this->increment('version');
    }

    /**
     * Replace placeholders in template string.
     *
     * Supports:
     * - {{variable}} - simple variable
     * - {{object.property}} - nested variable (e.g., {{user.name}})
     *
     * @param string $template
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        // Flatten nested variables for simple lookup
        $flatVariables = $this->flattenVariables($variables);

        return preg_replace_callback(
            '/\{\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}\}/',
            function (array $matches) use ($flatVariables): string {
                $key = $matches[1];
                return (string) ($flatVariables[$key] ?? $matches[0]);
            },
            $template
        ) ?? $template;
    }

    /**
     * Flatten nested array variables into dot notation.
     *
     * Example: ['user' => ['name' => 'John']] becomes ['user.name' => 'John']
     *
     * @param array<string, mixed> $variables
     * @param string $prefix
     * @return array<string, mixed>
     */
    private function flattenVariables(array $variables, string $prefix = ''): array
    {
        $result = [];

        foreach ($variables as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !empty($value) && !array_is_list($value)) {
                // Recursively flatten nested arrays
                $result = array_merge($result, $this->flattenVariables($value, $fullKey));
            } else {
                // Handle scalar values and convert to string
                $result[$fullKey] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $result;
    }

    /**
     * Keep only the last 2 versions for storage optimization.
     */
    private function pruneOldVersions(): void
    {
        $versionsToKeep = $this->versions()
            ->orderBy('version', 'desc')
            ->take(2)
            ->pluck('id');

        $this->versions()
            ->whereNotIn('id', $versionsToKeep)
            ->delete();
    }

    /**
     * Get supported template variables for documentation.
     *
     * @return array<string, string>
     */
    public static function getSupportedVariables(): array
    {
        return [
            '{{user.name}}' => 'User full name',
            '{{user.email}}' => 'User email address',
            '{{class.title}}' => 'Class template title',
            '{{class.starts_at}}' => 'Class start time',
            '{{class.ends_at}}' => 'Class end time',
            '{{class.room}}' => 'Room name',
            '{{trainer.name}}' => 'Trainer name',
            '{{cancel_url}}' => 'Booking cancellation URL',
            '{{confirm_url}}' => 'Booking confirmation URL',
            '{{password_reset_url}}' => 'Password reset URL',
            '{{company_name}}' => 'FunctionalFit Egeszsegkozpont',
            '{{support_email}}' => 'Support email address',
            '{{deleted_by}}' => 'Name of person who deleted',
            '{{modified_by}}' => 'Name of person who modified',
            '{{status}}' => 'Booking status (booked/waitlist)',
            '{{old.starts_at}}' => 'Previous start time',
            '{{new.starts_at}}' => 'New start time',
        ];
    }
}
