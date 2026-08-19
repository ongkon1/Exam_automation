<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['method', 'path', 'ip', 'content_type', 'headers', 'body', 'status_code', 'response'])]
class WebhookRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
        ];
    }

    public function wasAccepted(): bool
    {
        return $this->status_code !== null && $this->status_code < 300;
    }

    /**
     * The Bootstrap contextual colour for this request's outcome.
     */
    public function statusVariant(): string
    {
        return match (true) {
            $this->status_code === null => 'secondary',
            $this->status_code < 300 => 'success',
            $this->status_code === 401 || $this->status_code === 403 => 'danger',
            $this->status_code === 422 => 'warning',
            default => 'danger',
        };
    }

    /**
     * A short explanation of what happened, for the log screen.
     */
    public function outcome(): string
    {
        return match ($this->status_code) {
            null => 'No response recorded',
            200 => 'Duplicate callback (already stored)',
            202 => 'Accepted',
            401 => 'Rejected — wrong or missing X-Webhook-Secret header',
            403 => 'Forbidden',
            422 => 'Rejected — payload failed validation',
            503 => 'Rejected — WEBCALL_WEBHOOK_SECRET is not configured',
            default => 'HTTP '.$this->status_code,
        };
    }

    /**
     * Pretty-print the body when it is JSON.
     */
    public function prettyBody(): string
    {
        $decoded = json_decode((string) $this->body, true);

        return json_last_error() === JSON_ERROR_NONE
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : (string) $this->body;
    }
}
