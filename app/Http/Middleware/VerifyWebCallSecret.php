<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebCallSecret
{
    public const HEADER = 'X-Webhook-Secret';

    /**
     * The transcript callback is a public URL with no session, so it is authenticated
     * with a shared secret the voice provider sends on every request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webcall.webhook_secret');

        if (blank($expected)) {
            abort(503, 'WEBCALL_WEBHOOK_SECRET is not configured.');
        }

        $provided = $request->header(self::HEADER, '');

        // hash_equals keeps the comparison constant-time.
        abort_unless(is_string($provided) && hash_equals($expected, $provided), 401);

        return $next($request);
    }
}
