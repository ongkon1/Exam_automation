<?php

namespace App\Models;

use Database\Factories\CallSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'call_id', 'phone', 'subject', 'started_at', 'ended_at', 'matched_at'])]
class CallSession extends Model
{
    /** @use HasFactory<CallSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * The session a just-finished call most likely belongs to: the student's most recent
     * unclaimed one, within the configured window.
     */
    public static function openFor(User $student): ?self
    {
        return static::query()
            ->where('student_id', $student->id)
            ->whereNull('matched_at')
            ->where('started_at', '>=', now()->subMinutes((int) config('webcall.session_window_minutes')))
            ->latest('started_at')
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
