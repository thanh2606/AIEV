<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng jobs chính của AIEV - quản lý render queue.
 * Port từ apps/server/src/db.ts (CREATE TABLE jobs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiev_jobs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('project_id')->index();
            $table->string('type'); // scene-draft, scene-final, assemble-draft, assemble-final, image-gen, auto-cut, auto-trim, text-to-video, translate-video
            $table->string('scene_id')->nullable();
            $table->string('status')->default('queued'); // queued, running, done, failed, canceled
            $table->unsignedInteger('progress')->default(0);
            $table->longText('step')->nullable();
            $table->string('output_path')->nullable();
            $table->longText('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiev_jobs');
    }
};
