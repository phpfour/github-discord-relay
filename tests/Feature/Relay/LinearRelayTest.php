<?php

namespace Tests\Feature\Relay;

use App\Models\Setting;
use App\Models\WebhookRoute;
use App\Services\Relay\LinearRelay;
use App\Services\Relay\MentionMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinearRelayTest extends TestCase
{
    use RefreshDatabase;

    private function relay(): LinearRelay
    {
        return new LinearRelay(new MentionMapper);
    }

    private function route(): WebhookRoute
    {
        return WebhookRoute::factory()->linear()->scope('global')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/2/b',
        ]);
    }

    private function request(): Request
    {
        return Request::create('/linear/webhook', 'POST');
    }

    public function test_issue_create_is_sent(): void
    {
        Http::fake();
        $payload = [
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ];

        $result = $this->relay()->handle($this->route(), $payload, $this->request());

        $this->assertSame(LinearRelay::RESULT_PROCESSED, $result);
        Http::assertSent(fn ($req) => $req->url() === 'https://discord.com/api/webhooks/2/b');
    }

    public function test_duplicate_event_within_24h_is_not_resent(): void
    {
        Http::fake();
        $payload = [
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ];

        $first = $this->relay()->handle($this->route(), $payload, $this->request());
        $second = $this->relay()->handle($this->route(), $payload, $this->request());

        $this->assertSame(LinearRelay::RESULT_PROCESSED, $first);
        $this->assertSame(LinearRelay::RESULT_DUPLICATE, $second);
        Http::assertSentCount(1);
    }

    public function test_issue_update_is_skipped_and_not_marked_processed(): void
    {
        Http::fake();
        $relay = $this->relay();
        $route = $this->route();
        $payload = [
            'type' => 'Issue', 'action' => 'update', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ];

        $relay->handle($route, $payload, $this->request());

        // Nothing sent (skip filter), and the event is not marked processed.
        Http::assertNothingSent();
        $this->assertFalse($relay->isEventProcessed($relay->generateEventId($payload)));
    }

    public function test_skip_filter_is_configurable_via_settings(): void
    {
        Http::fake();
        Setting::set('linear_skip_filter', json_encode(['project' => ['remove']]));

        // issue/update now allowed because the filter only skips project/remove.
        $payload = [
            'type' => 'Issue', 'action' => 'update', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ];

        $this->relay()->handle($this->route(), $payload, $this->request());

        Http::assertSentCount(1);
    }
}
