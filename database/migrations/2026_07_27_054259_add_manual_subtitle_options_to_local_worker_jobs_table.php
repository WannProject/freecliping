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
            $table->string('subtitle_font_family', 32)->default('dejavu-sans')->after('subtitle_style');
            $table->string('subtitle_font_size', 16)->default('medium')->after('subtitle_font_family');
            $table->string('subtitle_position', 16)->default('bottom')->after('subtitle_font_size');
            $table->string('subtitle_color', 16)->default('white')->after('subtitle_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_worker_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'subtitle_font_family',
                'subtitle_font_size',
                'subtitle_position',
                'subtitle_color',
            ]);
        });
    }
};
