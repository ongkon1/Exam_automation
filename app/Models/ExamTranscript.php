<?php

namespace App\Models;

use Database\Factories\ExamTranscriptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'result_id', 'phone', 'subject', 'transcript', 'summary', 'call_result',
    'external_id', 'status', 'failure_reason', 'payload', 'evaluated_at',
])]
class ExamTranscript extends Model
{
    /** @use HasFactory<ExamTranscriptFactory> */
    use HasFactory;

    /** Stored, waiting to be evaluated. */
    public const STATUS_PENDING = 'pending';

    /** Evaluated by the AI and turned into a result. */
    public const STATUS_EVALUATED = 'evaluated';

    /** The callback's phone number matched no student. */
    public const STATUS_UNMATCHED = 'unmatched';

    /** Matched a student, but the AI evaluation could not be completed. */
    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    /**
     * The Bootstrap contextual colour used when rendering this transcript's status.
     */
    public function statusVariant(): string
    {
        return match ($this->status) {
            self::STATUS_EVALUATED => 'success',
            self::STATUS_PENDING => 'secondary',
            self::STATUS_UNMATCHED => 'warning',
            default => 'danger',
        };
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }
}
