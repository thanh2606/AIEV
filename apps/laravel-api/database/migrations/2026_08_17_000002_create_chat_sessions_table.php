<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiên chat Claude Agent - port từ apps/server/src/db.ts (chat_sessions).
 * Bao gồm tất cả các cột migration đã thêm dần trong Node.js.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->string('sdk_session_id')->nullable();
            $table->string('title')->default('');
            $table->string('project_id')->nullable()->index();
            $table->string('status')->default('idle'); // idle, running, done, error, interrupted
            $table->string('model')->nullable(); // Claude model cho phiên
            $table->string('effort')->nullable(); // low, medium, high
            $table->timestamp('run_started_at')->nullable();
            $table->timestamp('run_finished_at')->nullable();
            $table->boolean('auto_resume')->default(true);
            $table->unsignedInteger('resume_attempts')->default(0);
            $table->string('goal')->nullable(); // 'final' = phiên edit project
            $table->text('progress_mark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
