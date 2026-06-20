<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional, per-source inbound signature verification. When a signing secret
 * is configured for the source, the request signature is verified against the
 * raw request body; otherwise the request is accepted unchanged (the original
 * behavior). Invalid/unsigned requests are rejected with 401.
 */
class VerifyWebhookSignature
{
    /**
     * @var array<string, array{setting: string, header: string, prefix: string}>
     */
    private const SOURCES = [
        'github' => [
            'setting' => 'github_webhook_secret',
            'header' => 'X-Hub-Signature-256',
            'prefix' => 'sha256=',
        ],
        'linear' => [
            'setting' => 'linear_webhook_secret',
            'header' => 'Linear-Signature',
            'prefix' => '',
        ],
    ];

    public function handle(Request $request, Closure $next, string $source): Response
    {
        $config = self::SOURCES[$source] ?? null;

        if ($config === null) {
            return $next($request);
        }

        $secret = Setting::get($config['setting']);

        // No secret configured for this source -> accept (current behavior).
        if ($secret === null || $secret === '') {
            return $next($request);
        }

        $provided = (string) $request->header($config['header'], '');
        $expected = $config['prefix'].hash_hmac('sha256', $request->getContent(), $secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
