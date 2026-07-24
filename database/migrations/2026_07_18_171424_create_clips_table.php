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
        Schema::create('clips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_url', 2048);
            $table->string('youtube_video_id', 32)->index();
            $table->string('title')->nullable();
            $table->string('channel')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('start_seconds');
            $table->unsignedInteger('end_seconds');
            $table->string('aspect_ratio', 16)->default('original');
            $table->string('quality', 16)->default('source');
            $table->boolean('subtitles_enabled')->default(false);
            $table->string('subtitle_status')->nullable();
            $table->string('subtitle_style', 24)->default('word-highlight');
            $table->string('status')->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('output_disk')->nullable();
            $table->string('output_path')->nullable();
            $table->string('custom_file_name')->nullable();
            $table->unsignedBigInteger('output_size_bytes')->nullable();
            $table->timestamp('output_expires_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->ipAddress('requested_ip')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clips');
    }
};
