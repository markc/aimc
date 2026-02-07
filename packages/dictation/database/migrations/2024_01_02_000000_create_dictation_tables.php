<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictation_transcriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->text('text');
            $table->string('audio_file')->nullable();
            $table->string('model');
            $table->string('language', 10)->default('en');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->json('segments')->nullable();
            $table->boolean('injected')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dictation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('model')->default('base.en');
            $table->string('language', 10)->default('en');
            $table->string('injector')->default('wtype');
            $table->boolean('auto_inject')->default(true);
            $table->boolean('auto_delete_audio')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictation_settings');
        Schema::dropIfExists('dictation_transcriptions');
    }
};
