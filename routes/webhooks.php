<?php

use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\LinearWebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound Webhook Routes
|--------------------------------------------------------------------------
|
| Machine-to-machine endpoints for GitHub and Linear. These are registered
| outside the "web" middleware group so they carry no CSRF/session handling.
| External paths are preserved from the original application.
|
*/

Route::post('/github/webhook', [GitHubWebhookController::class, 'handle'])
    ->middleware(VerifyWebhookSignature::class.':github');

Route::post('/linear/webhook', [LinearWebhookController::class, 'handle'])
    ->middleware(VerifyWebhookSignature::class.':linear');
