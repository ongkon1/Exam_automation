<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebCallSecret
{
    public const HEADER = 'X-Webhook-Secret';

    /**
     * Optional shared-secret check for the transcript callback.
     *
     * The endpoint is open by default so a provider that cannot send custom headers can
     * still post to it. Setting WEBCALL_WEBHOOK_SECRET turns the check back on, and then
     * every request must carry it.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webcall.webhook_secret');

        if (blank($expected)) {
            return $next($request);
        }

        $provided = $request->header(self::HEADER, '');

        // hash_equals keeps the comparison constant-time.
        abort_unless(is_string($provided) && hash_equals($expected, $provided), 401);

        return $next($request);
    }
}
