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
        Schema::table('clips', function (Blueprint $table) {
            $table->string('aspect_ratio', 16)->default('original')->after('end_seconds');
            $table->string('quality', 16)->default('source')->after('aspect_ratio');
            $table->string('custom_file_name')->nullable()->after('output_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropColumn([
                'aspect_ratio',
                'quality',
                'custom_file_name',
            ]);
        });
    }
};
