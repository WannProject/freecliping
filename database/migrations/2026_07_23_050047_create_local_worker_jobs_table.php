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
        Schema::create('local_worker_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_type')->default('youtube');
            $table->string('source_url', 2048);
            $table->string('youtube_video_id', 32)->nullable()->index();
            $table->string('title')->nullable();
            $table->string('channel')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('start_seconds');
            $table->unsignedInteger('end_seconds');
            $table->string('aspect_ratio', 16)->default('original');
            $table->string('quality', 16)->default('source');
            $table->boolean('subtitles_enabled')->default(false);
            $table->boolean('sync_output')->default(false);
            $table->string('status')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('local_output_path')->nullable();
            $table->char('worker_token_hash', 64);
            $table->json('manifest')->nullable();
            $table->text('error_message')->nullable();
            $table->ipAddress('requested_ip')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['youtube_video_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_worker_jobs');
    }
};
