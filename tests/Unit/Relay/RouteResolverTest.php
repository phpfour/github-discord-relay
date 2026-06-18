<?php

namespace Tests\Unit\Relay;

use App\Models\WebhookRoute;
use App\Services\Relay\MatchKeys;
use App\Services\Relay\RouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteResolverTest extends TestCase
{
    use RefreshDatabase;

    private RouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RouteResolver;
    }

    public function test_github_repo_route_beats_org_and_global(): void
    {
        $repo = WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->create();
        WebhookRoute::factory()->github()->scope('org', 'acme')->create();
        WebhookRoute::factory()->github()->scope('global')->create();

        $route = $this->resolver->resolve('github', MatchKeys::github('acme/widgets', 'acme'));

        $this->assertSame($repo->id, $route->id);
    }

    public function test_github_falls_back_to_org_then_global(): void
    {
        $org = WebhookRoute::factory()->github()->scope('org', 'acme')->create();
        $global = WebhookRoute::factory()->github()->scope('global')->create();

        $this->assertSame(
            $org->id,
            $this->resolver->resolve('github', MatchKeys::github('acme/other', 'acme'))->id,
        );

        $this->assertSame(
            $global->id,
            $this->resolver->resolve('github', MatchKeys::github('unknown/repo', 'unknown'))->id,
        );
    }

    public function test_github_returns_null_when_no_match_and_no_global(): void
    {
        WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->create();

        $this->assertNull($this->resolver->resolve('github', MatchKeys::github('foo/bar', 'foo')));
    }

    public function test_linear_project_beats_team_and_global(): void
    {
        $project = WebhookRoute::factory()->linear()->scope('project', 'proj-1')->create();
        WebhookRoute::factory()->linear()->scope('team', 'team-1')->create();
        WebhookRoute::factory()->linear()->scope('global')->create();

        $route = $this->resolver->resolve('linear', MatchKeys::linear('proj-1', 'team-1'));

        $this->assertSame($project->id, $route->id);
    }

    public function test_linear_team_fallback_then_global(): void
    {
        $team = WebhookRoute::factory()->linear()->scope('team', 'team-1')->create();
        $global = WebhookRoute::factory()->linear()->scope('global')->create();

        $this->assertSame(
            $team->id,
            $this->resolver->resolve('linear', MatchKeys::linear('proj-x', 'team-1'))->id,
        );

        $this->assertSame(
            $global->id,
            $this->resolver->resolve('linear', MatchKeys::linear('proj-x', 'team-x'))->id,
        );
    }

    public function test_inactive_routes_are_skipped_even_on_exact_match(): void
    {
        WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->inactive()->create();
        $global = WebhookRoute::factory()->github()->scope('global')->create();

        $route = $this->resolver->resolve('github', MatchKeys::github('acme/widgets', 'acme'));

        $this->assertSame($global->id, $route->id);
    }

    public function test_routes_of_other_source_are_ignored(): void
    {
        WebhookRoute::factory()->linear()->scope('global')->create();

        $this->assertNull($this->resolver->resolve('github', MatchKeys::github('acme/widgets', 'acme')));
    }
}
