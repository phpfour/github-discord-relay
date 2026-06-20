<?php

namespace Tests\Feature\Webhooks;

use App\Models\Setting;
use App\Models\WebhookRoute;
use App\Services\Relay\DiscordClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignatureVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Swallow GitHub outbound calls.
        $this->app->instance(DiscordClient::class, new class extends DiscordClient
        {
            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void {}
        });

        Http::fake();
    }

    public function test_github_unsigned_request_accepted_when_no_secret(): void
    {
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->postJson('/github/webhook', ['action' => 'ping'])->assertOk();
    }

    public function test_github_wrong_signature_rejected_with_401(): void
    {
        Setting::set('github_webhook_secret', 'topsecret');
        WebhookRoute::factory()->github()->scope('global')->create();

        $body = json_encode(['action' => 'ping']);
        $bad = 'sha256='.hash_hmac('sha256', $body, 'wrongsecret');

        $this->call('POST', '/github/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $bad,
        ], $body)->assertStatus(401);
    }

    public function test_github_correct_signature_accepted(): void
    {
        Setting::set('github_webhook_secret', 'topsecret');
        WebhookRoute::factory()->github()->scope('global')->create();

        $body = json_encode(['action' => 'ping']);
        $good = 'sha256='.hash_hmac('sha256', $body, 'topsecret');

        $this->call('POST', '/github/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $good,
        ], $body)->assertOk();
    }

    public function test_signature_is_computed_against_raw_bytes(): void
    {
        Setting::set('github_webhook_secret', 'topsecret');
        WebhookRoute::factory()->github()->scope('global')->create();

        // Signature over the exact raw bytes; a re-encoded body would differ.
        $body = '{"action":"ping","spacing":"  preserved  "}';
        $good = 'sha256='.hash_hmac('sha256', $body, 'topsecret');

        $this->call('POST', '/github/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $good,
        ], $body)->assertOk();
    }

    public function test_linear_invalid_signature_rejected_and_valid_accepted(): void
    {
        Setting::set('linear_webhook_secret', 'lin-secret');
        WebhookRoute::factory()->linear()->scope('global')->create();

        $body = json_encode([
            'type' => 'Issue', 'action' => 'create', 'createdAt' => '2026-06-18T00:00:00Z',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P']],
        ]);

        $this->call('POST', '/linear/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_LINEAR_SIGNATURE' => hash_hmac('sha256', $body, 'wrong'),
        ], $body)->assertStatus(401);

        $this->call('POST', '/linear/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_LINEAR_SIGNATURE' => hash_hmac('sha256', $body, 'lin-secret'),
        ], $body)->assertOk();
    }
}
