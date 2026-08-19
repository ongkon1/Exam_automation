<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'role', 'roll_number',
    'class_name', 'phone', 'date_of_birth', 'address', 'created_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Phone numbers double as the identifier a voice-exam callback matches on, so they
     * are stored as digits only no matter how they were typed in.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => PhoneNumber::normalize($value));
    }

    /**
     * The exam results belonging to this user (students only).
     *
     * @return HasMany<Result, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'student_id');
    }

    /**
     * The voice-exam transcripts belonging to this user (students only).
     *
     * @return HasMany<ExamTranscript, $this>
     */
    public function examTranscripts(): HasMany
    {
        return $this->hasMany(ExamTranscript::class, 'student_id');
    }

    /**
     * The prompt settings belonging to this user (teachers only).
     *
     * @return HasOne<TeacherSetting, $this>
     */
    public function teacherSetting(): HasOne
    {
        return $this->hasOne(TeacherSetting::class);
    }

    #[Scope]
    protected function students(Builder $query): void
    {
        $query->where('role', self::ROLE_STUDENT);
    }

    #[Scope]
    protected function teachers(Builder $query): void
    {
        $query->where('role', self::ROLE_TEACHER);
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * The route a user should land on after logging in.
     */
    public function homeRoute(): string
    {
        return $this->isTeacher() ? 'teacher.students.index' : 'student.dashboard';
    }
}
