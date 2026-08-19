<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A raw log of everything that reaches the webhook endpoint, written before the
     * shared-secret check so a rejected or malformed call still leaves a trace.
     */
    public function up(): void
    {
        Schema::create('webhook_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip', 45)->nullable();
            $table->string('content_type')->nullable();
            $table->json('headers')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_requests');
    }
};
