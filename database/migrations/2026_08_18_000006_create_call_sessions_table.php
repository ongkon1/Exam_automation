<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which student started which subject's voice exam under which call id,
     * so the transcript callback can be matched on the call id instead of guessing
     * from the phone number — and so it can learn the subject at all.
     */
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('call_id')->unique();
            $table->string('subject');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
