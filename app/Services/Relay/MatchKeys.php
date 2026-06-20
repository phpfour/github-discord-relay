<?php

namespace App\Services\Relay;

/**
 * Immutable container of the scope match values extracted from an inbound
 * webhook payload. Unused scopes are simply null.
 */
class MatchKeys
{
    /**
     * @param  array<string, string|null>  $values  scope => value
     */
    private function __construct(
        public readonly array $values,
    ) {}

    /**
     * GitHub keys: repo ("owner/repo") and org ("owner").
     */
    public static function github(?string $repo, ?string $org): self
    {
        return new self([
            'repo' => $repo,
            'org' => $org,
            'global' => null,
        ]);
    }

    /**
     * Linear keys: project (id) and team (id).
     */
    public static function linear(?string $project, ?string $team): self
    {
        return new self([
            'project' => $project,
            'team' => $team,
            'global' => null,
        ]);
    }

    public function get(string $scope): ?string
    {
        return $this->values[$scope] ?? null;
    }
}
