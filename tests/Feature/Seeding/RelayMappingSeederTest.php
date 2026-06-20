<?php

namespace Tests\Feature\Seeding;

use App\Models\Member;
use App\Models\MemberIdentity;
use App\Models\WebhookRoute;
use Database\Seeders\RelayMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelayMappingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_expected_identity_counts(): void
    {
        $this->seed(RelayMappingSeeder::class);

        $this->assertSame(12, MemberIdentity::where('source', 'github')->count());
        $this->assertSame(7, MemberIdentity::where('source', 'linear')->count());
        $this->assertSame(11, Member::count());
    }

    public function test_two_shared_github_usernames_resolve_to_the_same_member(): void
    {
        $this->seed(RelayMappingSeeder::class);

        $a = MemberIdentity::where('external_id', 'mdrabbi97324')->first();
        $b = MemberIdentity::where('external_id', 'itsmdrabbi')->first();

        $this->assertNotNull($a);
        $this->assertSame($a->member_id, $b->member_id);
        $this->assertSame('1225685531141079100', $a->member->discord_user_id);
    }

    public function test_global_routes_seeded_when_config_present(): void
    {
        config([
            'services.discord.webhook_url_1' => 'https://discord.com/api/webhooks/1/a',
            'services.discord.webhook_url_2' => 'https://discord.com/api/webhooks/2/b',
        ]);

        $this->seed(RelayMappingSeeder::class);

        $this->assertSame(2, WebhookRoute::where('scope', 'global')->count());
        $this->assertSame(
            'https://discord.com/api/webhooks/1/a',
            WebhookRoute::where('source', 'github')->where('scope', 'global')->value('discord_webhook_url'),
        );
    }

    public function test_no_route_seeded_when_config_absent(): void
    {
        config([
            'services.discord.webhook_url_1' => null,
            'services.discord.webhook_url_2' => null,
        ]);

        $this->seed(RelayMappingSeeder::class);

        $this->assertSame(0, WebhookRoute::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RelayMappingSeeder::class);
        $this->seed(RelayMappingSeeder::class);

        $this->assertSame(11, Member::count());
        $this->assertSame(19, MemberIdentity::count());
    }
}
