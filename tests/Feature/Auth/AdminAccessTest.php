<?php

use App\Enums\Role;
use App\Models\User;

test('admin login screen can be rendered', function () {
    $this->get(route('admin.login'))->assertOk();
});

test('guests cannot access admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('students cannot access admin dashboard', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('unverified admin cannot access admin dashboard', function () {
    $admin = User::factory()->unverified()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('verified admin can access admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('student credentials cannot authenticate via admin login', function () {
    $student = User::factory()->create([
        'email' => 'student@example.com',
        'role' => Role::Student,
    ]);

    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('admin can authenticate via admin login', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
    ]);

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});

test('admin login always redirects to admin control center even with student intended url', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin-intended@example.com',
    ]);

    $this->withSession(['url.intended' => route('dashboard', absolute: false)])
        ->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});

test('access-admin gate allows only admins', function () {
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($student->can('access-admin'))->toBeFalse();
    expect($admin->can('access-admin'))->toBeTrue();
});
