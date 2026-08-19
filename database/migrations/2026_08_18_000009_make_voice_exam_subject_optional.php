<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice exams are recorded per student now — the student no longer picks a subject
     * before calling, so neither the session nor the transcript is guaranteed to have one.
     */
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
        });

        Schema::table('exam_transcripts', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
        });

        Schema::table('exam_transcripts', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
        });
    }
};
