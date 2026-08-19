<?php

use App\Http\Controllers\Webhooks\WebCallTranscriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Stateless, server-to-server endpoints. These carry the "api" prefix, so the
| callback below is reachable at POST /api/webhooks/webcall/transcript.
|
*/

// Posted by the voice provider when an exam call ends. No session — authenticated
// by the shared secret in the X-Webhook-Secret header.
Route::post('webhooks/webcall/transcript', WebCallTranscriptController::class)
    ->middleware('webcall.secret')
    ->name('webhooks.webcall.transcript');
