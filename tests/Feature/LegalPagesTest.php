<?php

use Inertia\Testing\AssertableInertia as Assert;

test('privacy page can be rendered', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('privacy'));
});

test('terms page can be rendered', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('terms'));
});
