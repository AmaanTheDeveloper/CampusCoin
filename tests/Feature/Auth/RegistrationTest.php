<?php

use App\Enums\Role;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register as students', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe(Role::Student);
});

test('registration ignores attempted admin role escalation', function () {
    $this->post('/register', [
        'name' => 'Attacker',
        'email' => 'attacker@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
        'is_admin' => true,
        'admin' => true,
    ]);

    $user = User::where('email', 'attacker@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(Role::Student);
    expect($user->isAdmin())->toBeFalse();
});

test('registration rejects invalid email', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Test User',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email')->assertRedirect('/register');

    $this->assertGuest();
});

test('registration rejects mismatched passwords', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Test User',
        'email' => 'mismatch@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password')->assertRedirect('/register');

    $this->assertGuest();
});
