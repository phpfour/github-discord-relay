<?php

namespace App\Models;

use Database\Factories\WebhookRouteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookRoute extends Model
{
    /** @use HasFactory<WebhookRouteFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'scope',
        'match_value',
        'discord_webhook_url',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to active routes only.
     *
     * @param  Builder<WebhookRoute>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Find the single active route matching a source/scope/value, if any.
     */
    public static function activeMatch(string $source, string $scope, ?string $value): ?self
    {
        return static::query()
            ->active()
            ->where('source', $source)
            ->where('scope', $scope)
            ->when(
                $scope === 'global',
                fn (Builder $q) => $q->whereNull('match_value'),
                fn (Builder $q) => $q->where('match_value', $value),
            )
            ->first();
    }
}
