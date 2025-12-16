<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_key',
        'params',
        'format',
        'status',
        'file_path',
        'file_size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if export is complete and ready for download
     */
    public function isReady(): bool
    {
        return $this->status === 'completed' && $this->file_path !== null;
    }

    /**
     * Check if export has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if export is still processing
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
