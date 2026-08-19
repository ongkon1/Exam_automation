<?php

namespace App\Http\Middleware;

use App\Models\WebhookRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every request that reaches the webhook endpoint.
 *
 * This runs before the shared-secret check, so a call rejected with 401 — or one whose
 * payload fails validation — still leaves a trace to look at. Without it, a provider
 * sending the wrong header looks identical to a provider never calling at all.
 */
class LogWebhookRequest
{
    /** Header values that are masked rather than stored in full. */
    protected const SENSITIVE = ['authorization', 'x-webhook-secret', 'cookie', 'x-api-key'];

    /** Bodies larger than this are truncated. */
    protected const MAX_BODY = 65000;

    /**
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $log = $this->record($request);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->finish($log, $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500, $e->getMessage());

            throw $e;
        }

        $this->finish($log, $response->getStatusCode(), $this->responseBody($response));

        return $response;
    }

    protected function record(Request $request): ?WebhookRequest
    {
        try {
            return WebhookRequest::create([
                'method' => $request->method(),
                'path' => $request->fullUrl(),
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'headers' => $this->headers($request),
                'body' => mb_substr((string) $request->getContent(), 0, self::MAX_BODY),
            ]);
        } catch (Throwable) {
            // Logging must never be the reason a callback fails.
            return null;
        }
    }

    protected function finish(?WebhookRequest $log, int $status, ?string $response): void
    {
        try {
            $log?->update([
                'status_code' => $status,
                'response' => $response === null ? null : mb_substr($response, 0, 2000),
            ]);
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return array<string, string>
     */
    protected function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $value = implode(', ', $values);

            // Keep enough to tell whether the header arrived, without storing the secret.
            $headers[$name] = in_array(strtolower($name), self::SENSITIVE, true)
                ? '['.strlen($value).' chars] '.mb_substr($value, 0, 4).'…'
                : $value;
        }

        return $headers;
    }

    protected function responseBody(Response $response): ?string
    {
        return method_exists($response, 'getContent') ? ($response->getContent() ?: null) : null;
    }
}
