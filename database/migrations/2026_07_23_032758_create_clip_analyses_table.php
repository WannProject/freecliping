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
        Schema::create('clip_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_url', 2048);
            $table->string('youtube_video_id')->index();
            $table->string('title');
            $table->string('channel');
            $table->unsignedInteger('duration_seconds');
            $table->text('thumbnail_url')->nullable();
            $table->string('status')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('transcript_language')->nullable();
            $table->json('recommendations')->nullable();
            $table->text('error_message')->nullable();
            $table->ipAddress('requested_ip')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['youtube_video_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clip_analyses');
    }
};
