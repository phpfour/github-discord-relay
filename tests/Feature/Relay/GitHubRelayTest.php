<?php

namespace Tests\Feature\Relay;

use App\Models\Member;
use App\Models\WebhookRoute;
use App\Services\Relay\DiscordClient;
use App\Services\Relay\GitHubRelay;
use App\Services\Relay\MentionMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class GitHubRelayTest extends TestCase
{
    use RefreshDatabase;

    private function recordingClient(): object
    {
        return new class extends DiscordClient
        {
            /** @var array<string, mixed> */
            public array $last = [];

            public int $calls = 0;

            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void
            {
                $this->calls++;
                $this->last = compact('url', 'headers', 'json');
            }
        };
    }

    private function makeMember(string $github, string $discordId): void
    {
        Member::factory()->create(['discord_user_id' => $discordId])
            ->identities()->create(['source' => 'github', 'external_id' => $github]);
    }

    public function test_mentions_are_rewritten_recursively(): void
    {
        $this->makeMember('phpfour', '538057585698537506');
        $client = $this->recordingClient();
        $relay = new GitHubRelay($client, new MentionMapper);
        $route = WebhookRoute::factory()->github()->scope('global')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/a',
        ]);

        $payload = [
            'action' => 'opened',
            'sender' => ['login' => 'phpfour'],
            'comment' => ['body' => 'cc @phpfour please review', 'count' => 5, 'flag' => true],
            'nested' => ['deep' => ['text' => 'ping @phpfour']],
        ];

        $relay->relay($route, $payload, Request::create('/github/webhook', 'POST'));

        $body = $client->last['json'];
        $this->assertSame('cc <@538057585698537506> please review', $body['comment']['body']);
        $this->assertSame('ping <@538057585698537506>', $body['nested']['deep']['text']);
        // Non-string nodes untouched.
        $this->assertSame(5, $body['comment']['count']);
        $this->assertTrue($body['comment']['flag']);
    }

    public function test_destination_url_has_github_suffix_and_headers_forwarded(): void
    {
        $client = $this->recordingClient();
        $relay = new GitHubRelay($client, new MentionMapper);
        $route = WebhookRoute::factory()->github()->scope('global')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/a',
        ]);

        $request = Request::create('/github/webhook', 'POST', server: [
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'abc-123',
            'HTTP_USER_AGENT' => 'GitHub-Hookshot/1',
        ]);

        $relay->relay($route, ['action' => 'x'], $request);

        $this->assertSame('https://discord.com/api/webhooks/1/a/github', $client->last['url']);
        $this->assertSame('push', $client->last['headers']['X-GitHub-Event']);
        $this->assertSame('abc-123', $client->last['headers']['X-GitHub-Delivery']);
        $this->assertSame('GitHub-Hookshot/1', $client->last['headers']['User-Agent']);
        // Absent header forwards as null.
        $this->assertNull($client->last['headers']['X-GitHub-Hook-ID']);
    }

    public function test_unmapped_username_is_left_as_literal(): void
    {
        $client = $this->recordingClient();
        $relay = new GitHubRelay($client, new MentionMapper);
        $route = WebhookRoute::factory()->github()->scope('global')->create();

        $relay->relay($route, ['text' => 'hi @nobody'], Request::create('/github/webhook', 'POST'));

        $this->assertSame('hi @nobody', $client->last['json']['text']);
    }

    public function test_discord_failure_is_swallowed(): void
    {
        $throwing = new class extends DiscordClient
        {
            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $relay = new GitHubRelay($throwing, new MentionMapper);
        $route = WebhookRoute::factory()->github()->scope('global')->create();

        // Should not throw.
        $relay->relay($route, ['text' => 'x'], Request::create('/github/webhook', 'POST'));

        $this->assertTrue(true);
    }
}
