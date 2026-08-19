<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks a finished call up on Speaklar.
 *
 * The transcript callback identifies a call only by id, and the browser never learns
 * that id, so this is how a transcript is tied back to a student: the status endpoint
 * returns the CDR, whose `dst` is the number the student called from.
 */
class SpeaklarClient
{
    protected const STATUS_PATH = '/api/ai-bulk-calls/status';

    /**
     * Fetch one call by id.
     *
     * @return array{phone: ?string, transcript: ?string, status: ?string, duration: ?int}|null
     *         null when the call is unknown or the lookup could not be performed
     */
    public function findCall(string $callId): ?array
    {
        $token = config('services.speaklar.token');

        if (blank($token)) {
            Log::warning('SPEAKLAR_API_TOKEN is not set; cannot look up call.', ['call_id' => $callId]);

            return null;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get(rtrim((string) config('services.speaklar.url'), '/').self::STATUS_PATH, [
                'call_id' => $callId,
            ]);

        if ($response->failed()) {
            Log::warning('Speaklar call lookup failed.', [
                'call_id' => $callId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $call = $this->firstCall($response->json() ?? []);

        if (! $call) {
            Log::warning('Speaklar returned no call for that id.', ['call_id' => $callId]);

            return null;
        }

        return [
            // cdr.dst is the number dialled — the student. src/port/extension are all
            // the internal channel (e.g. "770115") and must not be used here.
            'phone' => $this->stringOrNull($call['cdr']['dst'] ?? null),
            'transcript' => $this->stringOrNull($call['transcript'] ?? ($call['ai_data']['transcript'] ?? null)),
            'status' => $this->stringOrNull($call['status'] ?? null),
            'duration' => isset($call['cdr']['billsec']) ? (int) $call['cdr']['billsec'] : null,
        ];
    }

    /**
     * The endpoint has been seen both bare and wrapped in {http_status, body}.
     *
     * @param  array<mixed>  $payload
     * @return array<mixed>|null
     */
    protected function firstCall(array $payload): ?array
    {
        $calls = data_get($payload, 'body.calls', data_get($payload, 'calls'));

        return is_array($calls) && isset($calls[0]) && is_array($calls[0]) ? $calls[0] : null;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
