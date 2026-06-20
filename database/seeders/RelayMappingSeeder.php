<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\WebhookRoute;
use Illuminate\Database\Seeder;

/**
 * Migrates the original config/user_mapping.php data and the two destination
 * webhooks (DISCORD_WEBHOOK_URL_1/2) into the database. Idempotent.
 */
class RelayMappingSeeder extends Seeder
{
    /**
     * GitHub username => raw Discord snowflake.
     *
     * @var array<string, string>
     */
    private array $github = [
        'phpfour' => '538057585698537506',
        'OmarFaruk-0x01' => '862462774578774047',
        'ajaxray' => '1019936843015405588',
        'mdrabbi97324' => '1225685531141079100',
        'itsmdrabbi' => '1225685531141079100',
        'zobay' => '876622733893591130',
        'rrakibul' => '1052092416221523988',
        'zrshishir' => '531021119487213579',
        'theihasan' => '1102886197916876801',
        'Chy-Zaber-Bin-Zahid' => '471235756690505728',
        'mdzahid-pro' => '1349257244956557334',
        'ashrafiucse' => '1248255735071113348',
    ];

    /**
     * Linear user UUID => raw Discord snowflake.
     *
     * @var array<string, string>
     */
    private array $linear = [
        '0eb350a7-b8e6-4128-8dc4-40ad17681b7a' => '538057585698537506',
        'e0537c16-6efc-427d-abb9-6dd6864d8b36' => '862462774578774047',
        '86e7825c-e015-4495-9bb7-0dcaf934e22b' => '1019936843015405588',
        'd943eac5-279a-4506-8901-1c1b5dbc3838' => '1225685531141079100',
        '25efe406-df9e-4592-9091-c0e83b4d1b1a' => '876622733893591130',
        '6f108966-f4b4-4a42-9762-df15cbb85b2f' => '1052092416221523988',
        '4cf2d9e0-2fc5-46c7-8600-54fd495fb5f4' => '531021119487213579',
    ];

    public function run(): void
    {
        // Derive a display name per Discord ID (first GitHub username that maps to it).
        $names = [];
        foreach ($this->github as $username => $discordId) {
            $names[$discordId] ??= $username;
        }

        // Ensure a member exists per distinct Discord snowflake.
        $members = [];
        $discordIds = array_unique(array_merge(array_values($this->github), array_values($this->linear)));
        foreach ($discordIds as $discordId) {
            $members[$discordId] = Member::updateOrCreate(
                ['discord_user_id' => $discordId],
                ['name' => $names[$discordId] ?? $discordId],
            );
        }

        // Attach identities (unique on source + external_id).
        foreach ($this->github as $username => $discordId) {
            $members[$discordId]->identities()->updateOrCreate(
                ['source' => 'github', 'external_id' => $username],
                [],
            );
        }

        foreach ($this->linear as $uuid => $discordId) {
            $members[$discordId]->identities()->updateOrCreate(
                ['source' => 'linear', 'external_id' => $uuid],
                [],
            );
        }

        // Seed the two global destination routes from config, when present.
        $this->seedGlobalRoute('github', config('services.discord.webhook_url_1'), 'GitHub default');
        $this->seedGlobalRoute('linear', config('services.discord.webhook_url_2'), 'Linear default');
    }

    private function seedGlobalRoute(string $source, ?string $url, string $label): void
    {
        if ($url === null || $url === '') {
            return;
        }

        WebhookRoute::updateOrCreate(
            ['source' => $source, 'scope' => 'global', 'match_value' => null],
            ['discord_webhook_url' => $url, 'label' => $label, 'is_active' => true],
        );
    }
}
