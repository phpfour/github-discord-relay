<?php

namespace App\Services\Relay;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/**
 * Thin wrapper around the HTTP client used to POST to Discord webhook URLs.
 * Isolated so it can be faked in tests.
 */
class DiscordClient
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => 'https://discord.com/api/',
            'timeout' => 2.0,
        ]);
    }

    /**
     * POST a JSON body to the given Discord webhook URL with the given headers.
     *
     * @param  array<string, string|null>  $headers
     * @param  array<mixed>  $json
     */
    public function postJson(string $url, array $headers, array $json): void
    {
        $this->client->request('POST', $url, [
            'headers' => $headers,
            'json' => $json,
        ]);
    }
}
