<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $modifiedPayload = $this->modifyPayload($request->all());

        $this->relayToDiscord($modifiedPayload, $request);

        return response()->json(['status' => 'Payload relayed to Discord']);
    }

    private function modifyPayload(array $data)
    {
        $githubToDiscordMap = config('github_discord.github_to_discord_map');

        array_walk_recursive($data, function (&$item) use ($githubToDiscordMap) {
            if (! is_string($item)) {
                return;
            }

            foreach ($githubToDiscordMap as $githubUser => $discordUser) {
                $item = str_replace("@$githubUser", $discordUser, $item);
            }
        });

        return $data;
    }

    private function relayToDiscord($data, Request $request)
    {
        $discordWebhookUrl = config('github_discord.discord_webhook_url');

        $requiredHeaders = [
            'Accept', 'Content-Type', 'User-Agent',
            'X-GitHub-Delivery', 'X-GitHub-Event', 'X-GitHub-Hook-ID',
            'X-GitHub-Hook-Installation-Target-ID', 'X-GitHub-Hook-Installation-Target-Type',
        ];

        $headers = collect($requiredHeaders)
            ->mapWithKeys(fn($header) => [$header => $request->header($header)])
            ->toArray();

        $client = new Client([
            'base_uri' => 'https://discord.com/api/',
            'timeout'  => 2.0,
        ]);

        try {
            $client->post($discordWebhookUrl, [
                'headers' => $headers,
                'json' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error relaying payload to Discord: ' . $e->getMessage());
        }
    }
}
