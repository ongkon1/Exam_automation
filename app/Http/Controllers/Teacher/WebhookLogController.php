<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\WebhookRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WebhookLogController extends Controller
{
    public function index(): View
    {
        return view('teacher.webhooks.index', [
            'requests' => WebhookRequest::latest()->paginate(20),
            'endpoint' => route('webhooks.webcall.transcript'),
            'secretConfigured' => filled(config('webcall.webhook_secret')),
        ]);
    }

    public function show(WebhookRequest $webhook): View
    {
        return view('teacher.webhooks.show', ['request' => $webhook]);
    }

    public function destroy(): RedirectResponse
    {
        WebhookRequest::query()->delete();

        return redirect()->route('teacher.webhooks.index')
            ->with('success', 'Webhook log cleared.');
    }
}
