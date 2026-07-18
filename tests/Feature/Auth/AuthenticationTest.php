<?php

use App\Models\User;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

test('login and register routes are not registered', function () {
    expect(fn () => route('login'))->toThrow(RouteNotFoundException::class);
    expect(fn () => route('login.store'))->toThrow(RouteNotFoundException::class);
    expect(fn () => route('register'))->toThrow(RouteNotFoundException::class);
    expect(fn () => route('register.store'))->toThrow(RouteNotFoundException::class);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});
