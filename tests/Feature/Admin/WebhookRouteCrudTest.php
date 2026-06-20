<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WebhookRoute;
use App\Services\Relay\DiscordClient;
use App\Services\Relay\MatchKeys;
use App\Services\Relay\RouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRouteCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_routes_require_authentication(): void
    {
        $this->get('/routes')->assertRedirect(route('login'));
    }

    public function test_admin_creates_a_repo_route_used_by_the_resolver(): void
    {
        $this->actingAs($this->admin())->post('/routes', [
            'source' => 'github',
            'scope' => 'repo',
            'match_value' => 'acme/widgets',
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/a',
            'label' => 'Widgets',
            'is_active' => true,
        ])->assertRedirect('/routes');

        $route = (new RouteResolver)->resolve('github', MatchKeys::github('acme/widgets', 'acme'));
        $this->assertNotNull($route);
        $this->assertSame('acme/widgets', $route->match_value);
    }

    public function test_second_global_route_for_same_source_is_rejected(): void
    {
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->actingAs($this->admin())->post('/routes', [
            'source' => 'github',
            'scope' => 'global',
            'discord_webhook_url' => 'https://discord.com/api/webhooks/2/b',
        ])->assertSessionHasErrors('scope');
    }

    public function test_scope_invalid_for_source_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/routes', [
            'source' => 'github',
            'scope' => 'team',
            'match_value' => 'team-1',
            'discord_webhook_url' => 'https://discord.com/api/webhooks/2/b',
        ])->assertSessionHasErrors('scope');
    }

    public function test_toggling_inactive_removes_route_from_resolution(): void
    {
        $route = WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->create();

        $this->actingAs($this->admin())->put("/routes/{$route->id}", [
            'source' => 'github',
            'scope' => 'repo',
            'match_value' => 'acme/widgets',
            'discord_webhook_url' => $route->discord_webhook_url,
            'is_active' => false,
        ])->assertRedirect('/routes');

        $this->assertNull((new RouteResolver)->resolve('github', MatchKeys::github('acme/widgets', 'acme')));
    }

    public function test_enforcement_picks_up_a_gui_created_route(): void
    {
        $this->app->instance(DiscordClient::class, new class extends DiscordClient
        {
            public int $calls = 0;

            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void
            {
                $this->calls++;
            }
        });

        WebhookRoute::factory()->github()->scope('global')->create();

        $this->postJson('/github/webhook', ['action' => 'ping'])->assertOk();
        $this->assertSame(1, $this->app->make(DiscordClient::class)->calls);
    }
}
