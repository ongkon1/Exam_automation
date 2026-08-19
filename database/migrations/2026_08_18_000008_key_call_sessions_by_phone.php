<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The browser never learns the provider's call id, so a session can no longer be
     * created against one. Sessions are now opened with the student's phone number and
     * subject, and the call id is written back once the callback is reconciled.
     */
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('call_id')->nullable()->change();
            $table->string('phone')->nullable()->after('call_id')->index();

            // Set once a transcript has claimed this session, so it is not reused.
            $table->timestamp('matched_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropColumn(['phone', 'matched_at']);
            $table->string('call_id')->nullable(false)->change();
        });
    }
};
