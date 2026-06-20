<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/relay', [
            // Never send secrets to the client; only whether they are configured.
            'githubSecretConfigured' => Setting::get('github_webhook_secret') !== null,
            'linearSecretConfigured' => Setting::get('linear_webhook_secret') !== null,
            'linearSkipFilter' => Setting::get('linear_skip_filter', '{"issue":["update"]}'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'github_webhook_secret' => ['nullable', 'string', 'max:255'],
            'linear_webhook_secret' => ['nullable', 'string', 'max:255'],
            'linear_skip_filter' => ['nullable', 'string', 'json'],
        ]);

        // A blank secret clears it (disabling enforcement); a non-blank value
        // replaces it. Secrets are never echoed back to the client.
        if ($request->has('github_webhook_secret')) {
            Setting::set('github_webhook_secret', $validated['github_webhook_secret'] ?? null);
        }

        if ($request->has('linear_webhook_secret')) {
            Setting::set('linear_webhook_secret', $validated['linear_webhook_secret'] ?? null);
        }

        if ($request->has('linear_skip_filter')) {
            Setting::set('linear_skip_filter', $validated['linear_skip_filter'] ?? null);
        }

        return to_route('relay-settings.edit');
    }
}
