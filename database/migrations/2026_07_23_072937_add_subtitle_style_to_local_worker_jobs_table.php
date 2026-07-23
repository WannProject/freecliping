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
        Schema::table('local_worker_jobs', function (Blueprint $table) {
            $table->string('subtitle_style', 24)->default('word-highlight')->after('subtitles_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_worker_jobs', function (Blueprint $table) {
            $table->dropColumn('subtitle_style');
        });
    }
};
