<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * Read a setting value by key.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Create or update a setting value. A null/empty value clears the setting.
     */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
