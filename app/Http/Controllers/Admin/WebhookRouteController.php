<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRouteRequest;
use App\Models\WebhookRoute;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebhookRouteController extends Controller
{
    public function index(): Response
    {
        $routes = WebhookRoute::query()
            ->orderBy('source')
            ->orderBy('scope')
            ->get()
            ->groupBy('source');

        return Inertia::render('routes/index', [
            'routesBySource' => $routes,
            'scopes' => WebhookRouteRequest::SCOPES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('routes/create', [
            'scopes' => WebhookRouteRequest::SCOPES,
        ]);
    }

    public function store(WebhookRouteRequest $request): RedirectResponse
    {
        WebhookRoute::create($request->validatedData());

        return to_route('routes.index');
    }

    public function edit(WebhookRoute $route): Response
    {
        return Inertia::render('routes/edit', [
            'route' => $route,
            'scopes' => WebhookRouteRequest::SCOPES,
        ]);
    }

    public function update(WebhookRouteRequest $request, WebhookRoute $route): RedirectResponse
    {
        $route->update($request->validatedData());

        return to_route('routes.index');
    }

    public function destroy(WebhookRoute $route): RedirectResponse
    {
        $route->delete();

        return to_route('routes.index');
    }
}
