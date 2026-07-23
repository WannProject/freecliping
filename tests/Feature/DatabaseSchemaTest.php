<?php

use Illuminate\Support\Facades\Schema;

test('sessions table exists with the columns database sessions need', function () {
    expect(Schema::hasTable('sessions'))->toBeTrue();

    foreach (['id', 'ip_address', 'user_agent', 'payload', 'last_activity'] as $column) {
        expect(Schema::hasColumn('sessions', $column))->toBeTrue();
    }
});

test('legacy auth tables are not created on a fresh database', function () {
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});
