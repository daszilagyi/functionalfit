<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'key';

    /**
     * The "type" of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Disable created_at timestamp (table only has updated_at)
     */
    public const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            "setting.{$key}",
            3600,
            function () use ($key, $default) {
                $setting = self::where('key', $key)->first();
                return $setting ? $setting->value : $default;
            }
        );
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting.{$key}");
    }

    public static function forget(string $key): void
    {
        self::where('key', $key)->delete();
        Cache::forget("setting.{$key}");
    }
}
