<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated verified users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('unverified users are redirected away from the dashboard', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));
});
