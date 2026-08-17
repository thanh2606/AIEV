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
        Schema::create('tts_voices', function (Blueprint $table) {
            $table->id();
            $table->string('engine'); // gemini, vieneu
            $table->string('name')->unique(); // Identifier for the engine
            $table->string('title'); // Display name
            $table->string('label'); // Extended label
            $table->string('gender')->nullable();
            $table->integer('f0')->default(0);
            $table->string('kind'); // preset, cloned
            $table->string('region')->nullable();
            $table->string('timbre_key')->nullable();
            $table->text('note')->nullable();
            
            // For cloned voices only
            $table->string('ref_file')->nullable();
            $table->float('ref_duration_sec')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tts_voices');
    }
};
