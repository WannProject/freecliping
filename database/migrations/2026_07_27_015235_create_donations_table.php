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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name')->nullable();
            $table->unsignedInteger('amount_idr');
            $table->string('platform')->default('manual');
            $table->string('provider_event_id')->nullable()->unique();
            $table->text('message')->nullable();
            $table->boolean('anonymous')->default(false)->index();
            $table->boolean('public_visible')->default(true)->index();
            $table->timestamp('donated_at')->index();
            $table->timestamps();

            $table->index(['public_visible', 'donated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
