<?php

namespace App\Models;

use Database\Factories\ResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'exam_name', 'subject', 'exam_date', 'full_marks',
    'marks_obtained', 'grade', 'remarks', 'ai_feedback', 'evaluated_at', 'created_by',
])]
class Result extends Model
{
    /** @use HasFactory<ResultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'evaluated_at' => 'datetime',
            'marks_obtained' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Marks obtained expressed as a percentage of the full marks.
     */
    protected function percentage(): Attribute
    {
        return Attribute::get(fn (): float => $this->full_marks > 0
            ? round((float) $this->marks_obtained / $this->full_marks * 100, 2)
            : 0.0);
    }

    /**
     * Map a percentage onto the letter grade used across the system.
     */
    public static function gradeFor(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'A+',
            $percentage >= 70 => 'A',
            $percentage >= 60 => 'A-',
            $percentage >= 50 => 'B',
            $percentage >= 40 => 'C',
            default => 'F',
        };
    }

    /**
     * The Bootstrap contextual colour used when rendering this result's grade.
     */
    public function gradeVariant(): string
    {
        return match ($this->grade) {
            'A+', 'A' => 'success',
            'A-', 'B' => 'primary',
            'C' => 'warning',
            default => 'danger',
        };
    }

    public function isEvaluated(): bool
    {
        return filled($this->ai_feedback);
    }
}
