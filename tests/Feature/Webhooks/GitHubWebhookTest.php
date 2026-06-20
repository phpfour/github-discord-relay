<?php

namespace Tests\Feature\Webhooks;

use App\Models\WebhookRoute;
use App\Services\Relay\DiscordClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    private object $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new class extends DiscordClient
        {
            public int $calls = 0;

            /** @var array<string, mixed> */
            public array $last = [];

            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void
            {
                $this->calls++;
                $this->last = compact('url', 'headers', 'json');
            }
        };

        $this->app->instance(DiscordClient::class, $this->client);
    }

    public function test_endpoint_is_reachable_without_csrf_token(): void
    {
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->postJson('/github/webhook', ['action' => 'ping'])->assertOk();
    }

    public function test_repo_route_is_preferred_then_org_then_global(): void
    {
        $repo = WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/repo/x',
        ]);
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->postJson('/github/webhook', [
            'repository' => ['full_name' => 'acme/widgets', 'owner' => ['login' => 'acme']],
        ])->assertOk();

        $this->assertSame('https://discord.com/api/webhooks/repo/x/github', $this->client->last['url']);
    }

    public function test_no_matching_route_drops_event_with_2xx_and_no_send(): void
    {
        WebhookRoute::factory()->github()->scope('repo', 'acme/widgets')->create();

        $this->postJson('/github/webhook', [
            'repository' => ['full_name' => 'other/repo', 'owner' => ['login' => 'other']],
        ])->assertOk();

        $this->assertSame(0, $this->client->calls);
    }
}
