<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_transcripts', function (Blueprint $table) {
            $table->id();

            // Null when the callback's phone number matched no student — the transcript is
            // still kept so a teacher can reconcile it instead of it being silently dropped.
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();

            // The result this transcript produced, once the AI evaluation succeeds.
            $table->foreignId('result_id')->nullable()->constrained('results')->nullOnDelete();

            $table->string('phone');
            $table->string('subject');
            $table->longText('transcript');

            // The provider's own call identifier, used to make repeated callbacks idempotent.
            $table->string('external_id')->nullable()->unique();

            $table->string('status')->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_transcripts');
    }
};
