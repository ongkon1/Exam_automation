<?php

use App\Http\Controllers\Webhooks\WebCallTranscriptController;
use Illuminate\Http\Request;
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
// webhook.log runs first and records the raw request, so a call rejected by the secret
// check — or one that never matches a student — can still be inspected afterwards.
Route::post('webhooks/webcall/transcript', WebCallTranscriptController::class)
    ->middleware(['webhook.log', 'webcall.secret'])
    ->name('webhooks.webcall.transcript');

// Anything else aimed at /api/webhooks/* — wrong path, wrong method — is logged too, so a
// misconfigured callback URL is visible instead of looking like silence.
Route::any('webhooks/{path?}', function (Request $request) {
    return response()->json([
        'status' => 'unknown_endpoint',
        'message' => 'Post transcripts to '.route('webhooks.webcall.transcript').' with the X-Webhook-Secret header.',
        'received' => $request->method().' '.$request->path(),
    ], 404);
})->where('path', '.*')->middleware('webhook.log');
