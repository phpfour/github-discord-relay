<?php

namespace App\Http\Controllers;

use App\Services\Relay\GitHubRelay;
use App\Services\Relay\MatchKeys;
use App\Services\Relay\RouteResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    public function __construct(
        private readonly RouteResolver $resolver,
        private readonly GitHubRelay $relay,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $keys = $this->matchKeys($payload);
        $route = $this->resolver->resolve('github', $keys);

        if ($route === null) {
            Log::channel('webhooks')->info('No GitHub route matched; dropping event', [
                'repo' => $keys->get('repo'),
                'org' => $keys->get('org'),
            ]);

            return response()->json(['status' => 'No matching route; event dropped']);
        }

        $this->relay->relay($route, $payload, $request);

        return response()->json(['status' => 'Payload relayed to Discord']);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function matchKeys(array $payload): MatchKeys
    {
        $repo = $payload['repository']['full_name'] ?? null;

        $org = $payload['organization']['login']
            ?? $payload['repository']['owner']['login']
            ?? (is_string($repo) ? explode('/', $repo)[0] : null);

        return MatchKeys::github($repo, $org);
    }
}
