<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'system_prompt', 'evaluation_prompt'])]
class TeacherSetting extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Both prompts must be filled in before an AI evaluation can run.
     */
    public function isReadyForEvaluation(): bool
    {
        return filled($this->system_prompt) && filled($this->evaluation_prompt);
    }
}
