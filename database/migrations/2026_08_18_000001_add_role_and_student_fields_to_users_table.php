<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['teacher', 'student'])->default('student')->after('password')->index();
            $table->string('roll_number')->nullable()->unique()->after('role');
            $table->string('class_name')->nullable()->after('roll_number');
            $table->string('phone')->nullable()->after('class_name');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->text('address')->nullable()->after('date_of_birth');
            $table->foreignId('created_by')->nullable()->after('address')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'role', 'roll_number', 'class_name', 'phone', 'date_of_birth', 'address',
            ]);
        });
    }
};
