<?php

namespace Tests\Feature\Webhooks;

use App\Models\WebhookRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinearWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_relays_a_matched_event(): void
    {
        Http::fake();
        WebhookRoute::factory()->linear()->scope('global')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/2/b',
        ]);

        $this->postJson('/linear/webhook', [
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ])->assertOk()->assertJson(['message' => 'Webhook processed successfully']);

        Http::assertSentCount(1);
    }

    public function test_team_scoped_route_is_resolved(): void
    {
        Http::fake();
        WebhookRoute::factory()->linear()->scope('team', 'team-1')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/team/x',
        ]);

        $this->postJson('/linear/webhook', [
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'team' => ['id' => 'team-1'], 'project' => ['name' => 'P']],
        ])->assertOk();

        Http::assertSent(fn ($req) => $req->url() === 'https://discord.com/api/webhooks/team/x');
    }

    public function test_no_match_drops_event(): void
    {
        Http::fake();
        WebhookRoute::factory()->linear()->scope('team', 'team-1')->create();

        $this->postJson('/linear/webhook', [
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'team' => ['id' => 'other']],
        ])->assertOk()->assertJson(['message' => 'No matching route; event dropped']);

        Http::assertNothingSent();
    }
}
