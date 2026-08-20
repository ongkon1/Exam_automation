<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompts may now be 20,000 characters. MySQL's TEXT caps at 65,535 *bytes*, and
     * these prompts are written in Bengali (3 bytes per character) — 20,000 characters
     * is 60,000 bytes, and a single emoji pushes it over. MEDIUMTEXT removes the risk.
     */
    public function up(): void
    {
        Schema::table('teacher_settings', function (Blueprint $table) {
            $table->mediumText('system_prompt')->nullable()->change();
            $table->mediumText('evaluation_prompt')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_settings', function (Blueprint $table) {
            $table->text('system_prompt')->nullable()->change();
            $table->text('evaluation_prompt')->nullable()->change();
        });
    }
};
