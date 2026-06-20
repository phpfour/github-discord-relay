<?php

namespace App\Services\Relay;

use App\Models\WebhookRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Faithful port of the original GitHubWebhookController relay behavior:
 * recursively rewrites "@username" -> "<@discordId>" across every string in
 * the payload, then forwards the modified payload to the resolved Discord
 * webhook URL (with "/github" appended) re-proxying the required headers.
 */
class GitHubRelay
{
    /**
     * The GitHub headers re-proxied to Discord, preserved exactly.
     *
     * @var list<string>
     */
    private const FORWARDED_HEADERS = [
        'Accept', 'Content-Type', 'User-Agent',
        'X-GitHub-Delivery', 'X-GitHub-Event', 'X-GitHub-Hook-ID',
        'X-GitHub-Hook-Installation-Target-ID', 'X-GitHub-Hook-Installation-Target-Type',
    ];

    public function __construct(
        private readonly DiscordClient $discord,
        private readonly MentionMapper $mentions,
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function relay(WebhookRoute $route, array $payload, Request $request): void
    {
        $modified = $this->modifyPayload($payload);

        $url = $route->discord_webhook_url.'/github';

        $headers = collect(self::FORWARDED_HEADERS)
            ->mapWithKeys(fn (string $header) => [$header => $request->header($header)])
            ->toArray();

        try {
            $this->discord->postJson($url, $headers, $modified);
        } catch (\Throwable $e) {
            Log::error('Error relaying payload to Discord: '.$e->getMessage());
        }
    }

    /**
     * Recursively replace GitHub @mentions with Discord mentions across every
     * string node. Substring replacement is preserved deliberately from the
     * original implementation (not word-boundary aware).
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function modifyPayload(array $data): array
    {
        $map = $this->mentions->githubMap();

        array_walk_recursive($data, function (&$item) use ($map) {
            if (! is_string($item)) {
                return;
            }

            foreach ($map as $githubUser => $discordUser) {
                $item = str_replace("@$githubUser", $discordUser, $item);
            }
        });

        return $data;
    }
}
