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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('exam_name');
            $table->string('subject');
            $table->date('exam_date')->nullable();
            $table->unsignedInteger('full_marks')->default(100);
            $table->decimal('marks_obtained', 5, 2);
            $table->string('grade')->nullable();
            $table->text('remarks')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'subject']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
