<?php

namespace App\Services\Relay;

use App\Models\WebhookRoute;

/**
 * Resolves the destination webhook route for an inbound event using
 * most-specific-match precedence:
 *   - GitHub: repo -> org -> global
 *   - Linear: project -> team -> global
 *
 * Returns null when nothing matches and no global default exists.
 */
class RouteResolver
{
    /**
     * @var array<string, list<string>>
     */
    private const PRECEDENCE = [
        'github' => ['repo', 'org', 'global'],
        'linear' => ['project', 'team', 'global'],
    ];

    public function resolve(string $source, MatchKeys $keys): ?WebhookRoute
    {
        foreach (self::PRECEDENCE[$source] ?? [] as $scope) {
            $value = $keys->get($scope);

            // Non-global scopes need a value to match against.
            if ($scope !== 'global' && ($value === null || $value === '')) {
                continue;
            }

            $route = WebhookRoute::activeMatch($source, $scope, $value);

            if ($route !== null) {
                return $route;
            }
        }

        return null;
    }
}
