<?php

namespace App\Http\Controllers;

use App\Services\Relay\LinearRelay;
use App\Services\Relay\MatchKeys;
use App\Services\Relay\RouteResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LinearWebhookController extends Controller
{
    public function __construct(
        private readonly RouteResolver $resolver,
        private readonly LinearRelay $relay,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            $keys = $this->matchKeys($payload);
            $route = $this->resolver->resolve('linear', $keys);

            if ($route === null) {
                Log::channel('webhooks')->info('No Linear route matched; dropping event', [
                    'project' => $keys->get('project'),
                    'team' => $keys->get('team'),
                ]);

                return response()->json(['message' => 'No matching route; event dropped']);
            }

            $result = $this->relay->handle($route, $payload, $request);

            if ($result === LinearRelay::RESULT_DUPLICATE) {
                return response()->json(['message' => 'Event already processed'], 200);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Throwable $e) {
            Log::error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'An error occurred while processing the webhook'], 500);
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function matchKeys(array $payload): MatchKeys
    {
        $data = $payload['data'] ?? [];

        $project = $data['project']['id'] ?? $data['projectId'] ?? null;
        $team = $data['team']['id'] ?? $data['teamId'] ?? null;

        return MatchKeys::linear($project, $team);
    }
}
