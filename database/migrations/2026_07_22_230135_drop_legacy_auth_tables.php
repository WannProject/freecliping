<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore the sessions table and remove the legacy auth tables.
 *
 * The original 0001_01_01_000000 migration created users, password_reset_tokens
 * and sessions together. It was removed during the auth cleanup, which left
 * SESSION_DRIVER=database without a sessions table on fresh installs. This
 * migration recreates sessions (without the user relation, since the app is
 * login-less) and safely drops any users/password_reset_tokens left over on
 * databases created before the cleanup.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        $this->dropSafely('password_reset_tokens');
        $this->dropSafely('users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }

    private function dropSafely(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::drop($table);
        Schema::enableForeignKeyConstraints();
    }
};
