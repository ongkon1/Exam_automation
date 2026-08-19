<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The provider's callback carries its own call summary and outcome alongside the
     * transcript, so both are kept rather than buried in the raw payload column.
     */
    public function up(): void
    {
        Schema::table('exam_transcripts', function (Blueprint $table) {
            $table->longText('summary')->nullable()->after('transcript');

            // Named call_result so it does not collide with the result() relation.
            $table->string('call_result')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('exam_transcripts', function (Blueprint $table) {
            $table->dropColumn(['summary', 'call_result']);
        });
    }
};
