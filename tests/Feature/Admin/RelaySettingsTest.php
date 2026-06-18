<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookRoute;
use App\Services\Relay\DiscordClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DiscordClient::class, new class extends DiscordClient
        {
            public function __construct() {}

            public function postJson(string $url, array $headers, array $json): void {}
        });
    }

    public function test_settings_require_authentication(): void
    {
        $this->get('/relay-settings')->assertRedirect(route('login'));
    }

    public function test_setting_a_github_secret_enables_enforcement(): void
    {
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->actingAs($this->admin())->put('/relay-settings', [
            'github_webhook_secret' => 'enforce-me',
            'linear_skip_filter' => json_encode(['issue' => ['update']]),
        ])->assertRedirect('/relay-settings');

        // An unsigned request is now rejected.
        $this->postJson('/github/webhook', ['action' => 'ping'])->assertStatus(401);
    }

    public function test_clearing_a_secret_disables_enforcement(): void
    {
        Setting::set('github_webhook_secret', 'old');
        WebhookRoute::factory()->github()->scope('global')->create();

        $this->actingAs($this->admin())->put('/relay-settings', [
            'github_webhook_secret' => '',
        ])->assertRedirect('/relay-settings');

        $this->assertNull(Setting::get('github_webhook_secret'));
        $this->postJson('/github/webhook', ['action' => 'ping'])->assertOk();
    }

    public function test_secrets_are_never_returned_to_the_client(): void
    {
        Setting::set('github_webhook_secret', 'supersecret');

        $response = $this->actingAs($this->admin())->get('/relay-settings');

        $response->assertInertia(fn ($page) => $page
            ->component('settings/relay')
            ->where('githubSecretConfigured', true)
            ->missing('github_webhook_secret'));

        $this->assertStringNotContainsString('supersecret', $response->getContent());
    }

    public function test_invalid_skip_filter_json_is_rejected(): void
    {
        $this->actingAs($this->admin())->put('/relay-settings', [
            'linear_skip_filter' => '{not json',
        ])->assertSessionHasErrors('linear_skip_filter');
    }
}
